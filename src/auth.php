<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/repositories/UserRepository.php';

function get_bearer_token(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
  if (!$h && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) $h = $headers['Authorization'];
  }
  if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) {
    return trim($m[1]);
  }
  return null;
}

function require_auth(): array {
  $cfg = app_config();
  $token = get_bearer_token();
  if (!$token) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Missing Authorization header']);
    exit;
  }
  $payload = jwt_decode($token, $cfg['jwt']['secret']);
  if (!$payload) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
  }
  $user = UserRepository::findById((int)$payload['sub']);
  if (!$user || $user['status'] !== 'active') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'User not active']);
    exit;
  }
  return $user;
}

function require_admin(): array {
  $user = require_auth();
  if ($user['role'] !== 'admin') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Admin required']);
    exit;
  }
  return $user;
}
