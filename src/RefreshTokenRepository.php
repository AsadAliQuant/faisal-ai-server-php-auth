<?php
require_once __DIR__ . '/db.php';

class RefreshTokenRepository {
  public static function hashToken(string $plain): string {
    $c = app_config();
    return hash_hmac('sha256', $plain, $c['refresh_secret']);
  }

  public static function create(int $user_id, string $plain, int $ttl_seconds): array {
    $hash = self::hashToken($plain);
    $expires_at = date('Y-m-d H:i:s', time() + $ttl_seconds);
    $stmt = pdo()->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $hash, $expires_at]);
    return ['hash' => $hash, 'expires_at' => $expires_at];
  }

  public static function findValidByPlain(int $user_id, string $plain) {
    $hash = self::hashToken($plain);
    $stmt = pdo()->prepare('SELECT * FROM refresh_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$user_id, $hash]);
    return $stmt->fetch();
  }

  public static function findValidByPlainGlobal(string $plain) {
    $hash = self::hashToken($plain);
    $stmt = pdo()->prepare('SELECT * FROM refresh_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$hash]);
    return $stmt->fetch();
  }

  public static function revokeByPlain(int $user_id, string $plain): void {
    $hash = self::hashToken($plain);
    $stmt = pdo()->prepare('DELETE FROM refresh_tokens WHERE user_id = ? AND token_hash = ?');
    $stmt->execute([$user_id, $hash]);
  }

  public static function revokeAllForUser(int $user_id): void {
    $stmt = pdo()->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
    $stmt->execute([$user_id]);
  }

  public static function pruneExpired(): void {
    pdo()->exec('DELETE FROM refresh_tokens WHERE expires_at <= NOW()');
  }
}

