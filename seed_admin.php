<?php
// One-time admin seeding script. Protect with seed_token in config.php
// Usage: http://localhost/ai-auth-api/seed_admin.php?token=YOUR_TOKEN
// Optional POST/GET: email, password, name

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/repositories/UserRepository.php';

header('Content-Type: application/json');

try {
  $cfg = app_config();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Missing or invalid config.php']);
  exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!$token || !isset($cfg['seed_token']) || $token !== $cfg['seed_token']) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

$email = $_POST['email'] ?? $_GET['email'] ?? ($cfg['initial_admin_email'] ?? null);
$name = $_POST['name'] ?? $_GET['name'] ?? 'Administrator';
$password = $_POST['password'] ?? $_GET['password'] ?? null;

if (!$email) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing email']);
  exit;
}

function random_pass($len = 14) {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
  $out = '';
  for ($i=0; $i<$len; $i++) { $out .= $chars[random_int(0, strlen($chars)-1)]; }
  return $out;
}

if (!$password) { $password = random_pass(); }

try {
  $user = UserRepository::upsertAdmin($email, $password, $name);
  echo json_encode([
    'ok' => true,
    'admin' => [
      'id' => (int)$user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'role' => $user['role'],
      'status' => $user['status']
    ],
    'password' => $password,
    'note' => 'Store this password securely and then delete/rotate seed_token in config.php.'
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to seed admin', 'detail' => $e->getMessage()]);
}
