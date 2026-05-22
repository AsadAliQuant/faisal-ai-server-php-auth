<?php
require_once __DIR__ . '/../db.php';

class UserRepository {
  public static function findByEmail(string $email) {
    $stmt = pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch();
  }

  public static function findById(int $id) {
    $stmt = pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch();
  }

  public static function create(string $email, string $password, ?string $name = null, string $role = 'user', string $status = 'active') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = pdo()->prepare('INSERT INTO users (email, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, $role, $status]);
    return self::findById((int)pdo()->lastInsertId());
  }

  public static function verify(string $email, string $password) {
    $user = self::findByEmail($email);
    if (!$user) return null;
    if (!password_verify($password, $user['password_hash'])) return null;
    return $user;
  }

  public static function updateLastLogin(int $id) {
    $stmt = pdo()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$id]);
  }

  public static function listAll() {
    $stmt = pdo()->query('SELECT id, email, name, avatar, role, status, created_at, last_login FROM users ORDER BY id DESC');
    return $stmt->fetchAll();
  }

  public static function setPassword(int $id, string $newPassword) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $id]);
  }

  public static function updateStatus(int $id, string $status) {
    $stmt = pdo()->prepare('UPDATE users SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
  }

  public static function updateName(int $id, ?string $name) {
    $stmt = pdo()->prepare('UPDATE users SET name = ? WHERE id = ?');
    $stmt->execute([$name, $id]);
  }

  public static function updateAvatar(int $id, ?string $path) {
    $stmt = pdo()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    $stmt->execute([$path, $id]);
  }

  public static function countAdmins(): int {
    $stmt = pdo()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function delete(int $id) {
    $stmt = pdo()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
  }

  public static function countByRole(string $role): int {
    $stmt = pdo()->prepare('SELECT COUNT(*) AS c FROM users WHERE role = ?');
    $stmt->execute([$role]);
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function countByStatus(string $status): int {
    $stmt = pdo()->prepare('SELECT COUNT(*) AS c FROM users WHERE status = ?');
    $stmt->execute([$status]);
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function countAll(): int {
    $stmt = pdo()->query('SELECT COUNT(*) AS c FROM users');
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function upsertAdmin(string $email, string $password, ?string $name = 'Administrator') {
    $existing = self::findByEmail($email);
    if ($existing) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = pdo()->prepare("UPDATE users SET role = 'admin', status = 'active', password_hash = ?, name = ? WHERE id = ?");
      $stmt->execute([$hash, $name, (int)$existing['id']]);
      return self::findById((int)$existing['id']);
    }
    return self::create($email, $password, $name, 'admin', 'active');
  }
}

