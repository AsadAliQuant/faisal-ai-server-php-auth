<?php
require_once __DIR__ . '/../db.php';

class PasswordResetRepository {
  public static function countRecentRequests(string $email, int $minutes = 15): int {
    $stmt = pdo()->prepare(
      'SELECT COUNT(*) AS c FROM password_resets WHERE email = ? AND created_at >= NOW() - INTERVAL ? MINUTE'
    );
    $stmt->execute([$email, $minutes]);
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function create(string $email, string $otpHash, ?string $ip): void {
    $stmt = pdo()->prepare(
      'INSERT INTO password_resets (email, otp_hash, expires_at, ip_address) VALUES (?, ?, NOW() + INTERVAL 10 MINUTE, ?)'
    );
    $stmt->execute([$email, $otpHash, $ip]);
  }

  public static function findPending(string $email): ?array {
    $stmt = pdo()->prepare(
      'SELECT * FROM password_resets WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function incrementAttempts(int $id): void {
    $stmt = pdo()->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?');
    $stmt->execute([$id]);
  }

  public static function markVerified(int $id, string $resetTokenHash): void {
    $stmt = pdo()->prepare(
      'UPDATE password_resets SET reset_token_hash = ?, expires_at = NOW() + INTERVAL 15 MINUTE WHERE id = ?'
    );
    $stmt->execute([$resetTokenHash, $id]);
  }

  public static function findByResetToken(string $tokenHash): ?array {
    $stmt = pdo()->prepare(
      'SELECT * FROM password_resets WHERE reset_token_hash = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function markUsed(int $id): void {
    $stmt = pdo()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
    $stmt->execute([$id]);
  }

  public static function pruneExpired(): void {
    pdo()->exec('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 HOUR');
  }
}
