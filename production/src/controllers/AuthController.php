<?php
require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../RefreshTokenRepository.php';

class AuthController {
  private static function issueTokens(array $user): array {
    $cfg = app_config();
    $now = time();
    $payload = [
      'iss' => $cfg['jwt']['issuer'] ?? 'ai-auth-api',
      'sub' => (int)$user['id'],
      'email' => $user['email'],
      'role' => $user['role'],
      'iat' => $now,
      'exp' => $now + ($cfg['jwt']['access_ttl_seconds'] ?? 900)
    ];
    $access = jwt_encode($payload, $cfg['jwt']['secret']);

    $refreshPlain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    RefreshTokenRepository::create((int)$user['id'], $refreshPlain, (int)($cfg['refresh_ttl_seconds'] ?? 2592000));

    return [
      'access_token' => $access,
      'access_expires_in' => $payload['exp'] - $now,
      'refresh_token' => $refreshPlain,
      'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'avatar' => $user['avatar'] ?? null,
        'role' => $user['role'],
        'status' => $user['status']
      ]
    ];
  }

  public static function google(): void {
    $body = read_json();
    $idToken = trim($body['idToken'] ?? '');
    if (!$idToken) { json_response(['error' => 'idToken required'], 400); return; }

    $ch = curl_init('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $raw = curl_exec($ch);
    curl_close($ch);
    $payload = json_decode($raw, true);

    if (empty($payload['email']) || empty($payload['email_verified'])) {
      json_response(['error' => 'Invalid Google token'], 401); return;
    }
    $iss = $payload['iss'] ?? '';
    if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
      json_response(['error' => 'Token issuer mismatch'], 401); return;
    }

    $email = $payload['email'];
    // Prefer values sent by the client (Firebase populates displayName/photoURL reliably);
    // fall back to whatever Google's tokeninfo response includes.
    $bodyName    = isset($body['name'])    && $body['name']    !== '' ? $body['name']    : null;
    $bodyPicture = isset($body['picture']) && $body['picture'] !== '' ? $body['picture'] : null;
    $name    = $bodyName    ?? ($payload['name']    ?? null);
    $picture = $bodyPicture ?? ($payload['picture'] ?? null);

    $user = UserRepository::findByEmail($email);
    if (!$user) {
      $user = UserRepository::create($email, bin2hex(random_bytes(16)), $name, 'user', 'active');
      if ($user && $picture) {
        UserRepository::updateAvatar((int)$user['id'], $picture);
        $user['avatar'] = $picture;
      }
    } else {
      // Backfill name/avatar from Google when missing (e.g. users created before this fix)
      if (empty($user['name']) && $name) {
        UserRepository::updateName((int)$user['id'], $name);
        $user['name'] = $name;
      }
      if (empty($user['avatar']) && $picture) {
        UserRepository::updateAvatar((int)$user['id'], $picture);
        $user['avatar'] = $picture;
      }
    }

    if ($user['status'] !== 'active') {
      json_response(['error' => 'Account disabled'], 403); return;
    }

    UserRepository::updateLastLogin((int)$user['id']);
    json_response(self::issueTokens($user));
  }

  public static function login() {
    $data = read_json();
    require_fields($data, ['email','password']);
    $user = UserRepository::verify($data['email'], $data['password']);
    if (!$user) { json_response(['error' => 'Invalid credentials'], 401); return; }
    if ($user['status'] !== 'active') { json_response(['error' => 'User disabled'], 403); return; }
    UserRepository::updateLastLogin((int)$user['id']);
    json_response(self::issueTokens($user));
  }

  public static function refresh() {
    $data = read_json();
    require_fields($data, ['refresh_token']);
    // Look up refresh token globally (by hash) and ensure not expired
    $rt = RefreshTokenRepository::findValidByPlainGlobal($data['refresh_token']);
    if (!$rt) { json_response(['error' => 'Invalid or expired refresh token'], 401); return; }
    $user = UserRepository::findById((int)$rt['user_id']);
    if (!$user || $user['status'] !== 'active') { json_response(['error' => 'User not active'], 403); return; }
    json_response(self::issueTokens($user));
  }

  public static function logout() {
    $data = read_json();
    if (isset($data['all']) && $data['all'] === true) {
      $user = require_auth();
      RefreshTokenRepository::revokeAllForUser((int)$user['id']);
      json_response(['ok' => true]);
      return;
    }
    require_fields($data, ['refresh_token']);
    $user = require_auth();
    RefreshTokenRepository::revokeByPlain((int)$user['id'], $data['refresh_token']);
    json_response(['ok' => true]);
  }

  public static function me() {
    $user = require_auth();
    json_response([
      'id' => (int)$user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'avatar' => $user['avatar'] ?? null,
      'role' => $user['role'],
      'status' => $user['status'],
      'created_at' => $user['created_at'],
      'last_login' => $user['last_login']
    ]);
  }
}
