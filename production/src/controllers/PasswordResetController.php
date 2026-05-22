<?php
require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/PasswordResetRepository.php';
require_once __DIR__ . '/../RefreshTokenRepository.php';
require_once __DIR__ . '/../services/EmailService.php';

class PasswordResetController {
  private static function resolveLocation(string $ip): string {
    // Skip private/loopback IPs (localhost dev)
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
      return 'Local network';
    }
    try {
      $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
      $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,regionName,country,status", false, $ctx);
      if ($json) {
        $data = json_decode($json, true);
        if (($data['status'] ?? '') === 'success') {
          $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
          return implode(', ', $parts) ?: 'Unknown';
        }
      }
    } catch (\Exception $e) {}
    return 'Unknown';
  }

  public static function request(): void {
    PasswordResetRepository::pruneExpired();

    $data = read_json();
    require_fields($data, ['email']);
    $email = strtolower(trim($data['email']));

    // Rate limit: max 3 requests per 15 minutes per email
    if (PasswordResetRepository::countRecentRequests($email, 15) >= 3) {
      json_response(['error' => 'Too many reset requests. Please wait before trying again.'], 429);
      return;
    }

    // Generic response regardless of whether email exists (prevents enumeration)
    $user = UserRepository::findByEmail($email);
    if ($user && $user['status'] === 'active') {
      $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $otpHash = password_hash($otp, PASSWORD_DEFAULT);
      $ip = $_SERVER['REMOTE_ADDR'] ?? null;
      PasswordResetRepository::create($email, $otpHash, $ip);
      try {
        EmailService::sendOtp($email, $user['name'] ?? '', $otp);
      } catch (\Exception $e) {
        json_response(['error' => 'Failed to send email. Please try again later.'], 500);
        return;
      }
    }

    json_response(['message' => 'If this email is registered, you will receive an OTP shortly.']);
  }

  public static function verify(): void {
    $data = read_json();
    require_fields($data, ['email', 'otp']);
    $email = strtolower(trim($data['email']));
    $otp   = trim($data['otp']);

    $reset = PasswordResetRepository::findPending($email);
    if (!$reset) {
      json_response(['error' => 'Invalid or expired OTP. Please request a new one.'], 400);
      return;
    }

    if ((int)$reset['attempts'] >= 5) {
      json_response(['error' => 'Too many failed attempts. Please request a new OTP.'], 429);
      return;
    }

    if (!password_verify($otp, $reset['otp_hash'])) {
      PasswordResetRepository::incrementAttempts((int)$reset['id']);
      $remaining = 4 - (int)$reset['attempts'];
      json_response(['error' => 'Invalid OTP.', 'attempts_remaining' => max(0, $remaining)], 400);
      return;
    }

    // OTP correct — issue a one-time reset token
    $plainToken  = bin2hex(random_bytes(32));
    $cfg         = app_config();
    $tokenHash   = hash_hmac('sha256', $plainToken, $cfg['refresh_secret']);
    PasswordResetRepository::markVerified((int)$reset['id'], $tokenHash);

    json_response(['reset_token' => $plainToken, 'message' => 'OTP verified.']);
  }

  public static function confirm(): void {
    $data = read_json();
    require_fields($data, ['email', 'reset_token', 'new_password']);
    $email      = strtolower(trim($data['email']));
    $plainToken = trim($data['reset_token']);
    $newPass    = $data['new_password'];

    if (strlen($newPass) < 8) {
      json_response(['error' => 'Password must be at least 8 characters.'], 422);
      return;
    }

    $cfg       = app_config();
    $tokenHash = hash_hmac('sha256', $plainToken, $cfg['refresh_secret']);
    $reset     = PasswordResetRepository::findByResetToken($tokenHash);

    if (!$reset || $reset['email'] !== $email) {
      json_response(['error' => 'Invalid or expired reset token. Please start over.'], 400);
      return;
    }

    $user = UserRepository::findByEmail($email);
    if (!$user) {
      json_response(['error' => 'User not found.'], 404);
      return;
    }

    UserRepository::setPassword((int)$user['id'], $newPass);
    RefreshTokenRepository::revokeAllForUser((int)$user['id']);
    PasswordResetRepository::markUsed((int)$reset['id']);

    // Send confirmation email with IP location (best-effort, don't fail the reset if email fails)
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $location = self::resolveLocation($ip);
    try {
      EmailService::sendPasswordChanged($email, $user['name'] ?? '', $ip, $location);
    } catch (\Exception $e) {
      // Confirmation email failure is non-fatal — password was already changed
    }

    json_response(['message' => 'Password reset successful. You can now sign in.']);
  }
}
