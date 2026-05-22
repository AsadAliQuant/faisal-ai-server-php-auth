<?php
require_once __DIR__ . '/../db.php';

class LicenseRepository {
  private static function row(array $r): array {
    $deleted = !empty($r['deleted_at']);
    return [
      'id'            => (int)$r['id'],
      'key'           => $r['key'],
      'type'          => $r['type'],
      'status'        => $deleted ? 'deleted' : $r['status'],
      'assigned_to'   => $r['assigned_to'],
      'assigned_name' => $r['assigned_name'],
      'notes'         => $r['notes'] ?? '',
      'expires_at'    => $r['expires_at'],
      'created_at'    => $r['created_at'],
      'deleted_at'    => $r['deleted_at'] ?? null,
    ];
  }

  public static function listAll(): array {
    $pdo = pdo();
    // Auto-expire non-deleted licenses whose time has passed
    $pdo->exec("UPDATE licenses SET status='expired' WHERE status IN ('active','revoked') AND expires_at < NOW() AND deleted_at IS NULL");
    $stmt = $pdo->query("SELECT * FROM licenses WHERE deleted_at IS NULL ORDER BY created_at DESC");
    return array_map([self::class, 'row'], $stmt->fetchAll());
  }

  public static function listForEmail(string $email): array {
    $pdo = pdo();
    // Auto-expire non-deleted licenses first so the user sees fresh statuses
    $pdo->exec("UPDATE licenses SET status='expired' WHERE status IN ('active','revoked') AND expires_at < NOW() AND deleted_at IS NULL");
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE assigned_to = ? ORDER BY created_at DESC");
    $stmt->execute([$email]);
    return array_map([self::class, 'row'], $stmt->fetchAll());
  }

  public static function findById(int $id): ?array {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ? self::row($r) : null;
  }

  public static function create(string $key, string $type, ?string $assignedTo, ?string $assignedName, ?string $notes, string $expiresAt): array {
    $pdo = pdo();
    $stmt = $pdo->prepare(
      "INSERT INTO licenses (`key`, type, assigned_to, assigned_name, notes, expires_at) VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([$key, $type, $assignedTo, $assignedName, $notes, $expiresAt]);
    return self::findById((int)$pdo->lastInsertId());
  }

  public static function assign(int $id, ?string $email, ?string $name, ?string $notes): bool {
    $pdo = pdo();
    $stmt = $pdo->prepare(
      "UPDATE licenses SET assigned_to=?, assigned_name=?, notes=? WHERE id=?"
    );
    return $stmt->execute([$email, $name, $notes, $id]);
  }

  public static function reactivate(int $id, string $type, ?string $expires_at = null): bool {
    $pdo = pdo();
    if ($expires_at) {
      $stmt = $pdo->prepare("UPDATE licenses SET status='active', type=?, expires_at=? WHERE id=?");
      return $stmt->execute([$type, $expires_at, $id]);
    }
    $days = $type === 'trial' ? 7 : 365;
    $stmt = $pdo->prepare(
      "UPDATE licenses SET status='active', type=?, expires_at=DATE_ADD(NOW(), INTERVAL $days DAY) WHERE id=?"
    );
    return $stmt->execute([$type, $id]);
  }

  public static function revoke(int $id): bool {
    $pdo = pdo();
    $stmt = $pdo->prepare("UPDATE licenses SET status='revoked' WHERE id=? AND status != 'revoked'");
    return $stmt->execute([$id]);
  }

  public static function delete(int $id): bool {
    $pdo = pdo();
    $stmt = $pdo->prepare("UPDATE licenses SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
  }

  public static function bulkReactivate(array $ids): int {
    if (!$ids) return 0;
    $pdo = pdo();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
      "UPDATE licenses SET status='active', expires_at=DATE_ADD(NOW(), INTERVAL CASE WHEN type='trial' THEN 7 ELSE 365 END DAY) WHERE id IN ($placeholders) AND status = 'revoked'"
    );
    $stmt->execute(array_values($ids));
    return $stmt->rowCount();
  }

  public static function bulkRevoke(array $ids): int {
    if (!$ids) return 0;
    $pdo = pdo();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
      "UPDATE licenses SET status='revoked' WHERE id IN ($placeholders) AND status = 'active'"
    );
    $stmt->execute(array_values($ids));
    return $stmt->rowCount();
  }

  public static function bulkDelete(array $ids): int {
    if (!$ids) return 0;
    $pdo = pdo();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE licenses SET deleted_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute(array_values($ids));
    return $stmt->rowCount();
  }

  public static function listActivityForEmail(string $email): array {
    $pdo = pdo();
    $stmt = $pdo->prepare(
      "SELECT la.* FROM license_activity la
       JOIN licenses l ON la.license_key = l.key
       WHERE l.assigned_to = ?
       ORDER BY la.created_at DESC"
    );
    $stmt->execute([$email]);
    return array_map(function($r) {
      return [
        'id'          => (int)$r['id'],
        'action'      => $r['action'],
        'license_key' => $r['license_key'],
        'by_user'     => $r['by_user'],
        'detail'      => $r['detail'] ?? '',
        'created_at'  => $r['created_at'],
      ];
    }, $stmt->fetchAll());
  }

  public static function logActivity(string $action, string $licenseKey, string $byUser, string $detail): void {
    try {
      $pdo = pdo();
      $stmt = $pdo->prepare(
        "INSERT INTO license_activity (action, license_key, by_user, detail) VALUES (?,?,?,?)"
      );
      $stmt->execute([$action, $licenseKey, $byUser, $detail]);
    } catch (PDOException $e) {
      log_error('logActivity', $e->getMessage(), ['action' => $action, 'key' => $licenseKey]);
      // Never let activity log failure block the main operation
    }
  }

  public static function getActivity(int $limit = 100): array {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT * FROM license_activity ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return array_map(function($r) {
      return [
        'id'          => (int)$r['id'],
        'action'      => $r['action'],
        'license_key' => $r['license_key'],
        'by_user'     => $r['by_user'],
        'detail'      => $r['detail'] ?? '',
        'created_at'  => $r['created_at'],
      ];
    }, $stmt->fetchAll());
  }
}
