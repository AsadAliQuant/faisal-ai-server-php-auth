<?php
require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/LicenseRepository.php';

class MeController {
  private static function profileResponse(array $user): array {
    return [
      'id' => (int)$user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'avatar' => $user['avatar'] ?? null,
      'role' => $user['role'],
      'status' => $user['status'],
      'created_at' => $user['created_at'],
      'last_login' => $user['last_login']
    ];
  }

  public static function licenses(): void {
    $user = require_auth();
    $licenses = LicenseRepository::listForEmail($user['email']);
    json_response(['licenses' => $licenses]);
  }

  public static function licenseActivity(): void {
    $user = require_auth();
    $activity = LicenseRepository::listActivityForEmail($user['email']);
    json_response(['activity' => $activity]);
  }

  public static function updateProfile(): void {
    $user = require_auth();
    $data = read_json();
    // Treat empty string as "clear name", null as "no change"
    if (!array_key_exists('name', $data)) {
      json_response(['error' => 'Missing field: name'], 422);
      return;
    }
    $name = $data['name'];
    if ($name !== null) {
      $name = trim((string)$name);
      if ($name === '') $name = null;
      if ($name !== null && mb_strlen($name) > 190) {
        json_response(['error' => 'Name too long (max 190 chars)'], 422);
        return;
      }
    }
    UserRepository::updateName((int)$user['id'], $name);
    $fresh = UserRepository::findById((int)$user['id']);
    json_response(['user' => self::profileResponse($fresh)]);
  }

  public static function changePassword(): void {
    $user = require_auth();
    $data = read_json();
    require_fields($data, ['current_password', 'new_password']);
    if (strlen((string)$data['new_password']) < 8) {
      json_response(['error' => 'New password must be at least 8 characters'], 422);
      return;
    }
    $verified = UserRepository::verify($user['email'], $data['current_password']);
    if (!$verified) {
      json_response(['error' => 'Current password is incorrect'], 401);
      return;
    }
    UserRepository::setPassword((int)$user['id'], $data['new_password']);
    json_response(['ok' => true]);
  }

  public static function uploadAvatar(): void {
    $user = require_auth();
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
      json_response(['error' => 'No file uploaded (expected field "file")'], 422);
      return;
    }
    $f = $_FILES['file'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      json_response(['error' => 'Upload failed (code ' . (int)$f['error'] . ')'], 400);
      return;
    }
    if ((int)$f['size'] > 2 * 1024 * 1024) {
      json_response(['error' => 'File too large (max 2MB)'], 413);
      return;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']) ?: '';
    $extMap = [
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
      json_response(['error' => 'Unsupported file type. Use JPEG, PNG, or WebP.'], 415);
      return;
    }
    $ext = $extMap[$mime];

    $appRoot = dirname(dirname(__DIR__)); // src/controllers/ → src/ → app root
    $dir = (is_dir($appRoot . '/public') ? $appRoot . '/public' : $appRoot) . '/uploads/avatars';
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        json_response(['error' => 'Server upload directory not writable'], 500);
        return;
      }
    }

    // Remove any previous avatar for this user, regardless of extension
    foreach (['jpg', 'png', 'webp'] as $oldExt) {
      $old = $dir . '/' . (int)$user['id'] . '.' . $oldExt;
      if (file_exists($old)) @unlink($old);
    }

    $filename = (int)$user['id'] . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
      json_response(['error' => 'Failed to save uploaded file'], 500);
      return;
    }

    // Public path served by Apache (relative to backend root)
    $publicPath = '/uploads/avatars/' . $filename;
    UserRepository::updateAvatar((int)$user['id'], $publicPath);

    $fresh = UserRepository::findById((int)$user['id']);
    json_response(['user' => self::profileResponse($fresh)]);
  }

  public static function deleteAvatar(): void {
    $user = require_auth();
    $appRoot = dirname(dirname(__DIR__));
    $dir = (is_dir($appRoot . '/public') ? $appRoot . '/public' : $appRoot) . '/uploads/avatars';
    foreach (['jpg', 'png', 'webp'] as $ext) {
      $path = $dir . '/' . (int)$user['id'] . '.' . $ext;
      if (file_exists($path)) @unlink($path);
    }
    UserRepository::updateAvatar((int)$user['id'], null);
    $fresh = UserRepository::findById((int)$user['id']);
    json_response(['user' => self::profileResponse($fresh)]);
  }

  public static function requestRenewal(): void {
    $user = require_auth();
    $data = read_json();
    require_fields($data, ['license_key']);
    $key = trim((string)$data['license_key']);
    if ($key === '') {
      json_response(['error' => 'license_key required'], 422);
      return;
    }
    LicenseRepository::logActivity(
      'Renewal requested',
      $key,
      $user['email'],
      'User requested a license renewal'
    );
    json_response(['ok' => true]);
  }
}
