<?php
// Resolve src path for both deployments where index.php is under /public or root
$srcA = __DIR__ . '/../src';
$srcB = __DIR__ . '/src';
$SRC = is_dir($srcA) ? $srcA : $srcB;

require_once $SRC . '/util.php';
require_once $SRC . '/db.php';
require_once $SRC . '/jwt.php';
require_once $SRC . '/auth.php';
require_once $SRC . '/repositories/UserRepository.php';
require_once $SRC . '/RefreshTokenRepository.php';
require_once $SRC . '/controllers/AuthController.php';
require_once $SRC . '/controllers/AdminController.php';
require_once $SRC . '/repositories/LicenseRepository.php';
require_once $SRC . '/controllers/LicenseController.php';
require_once $SRC . '/repositories/PasswordResetRepository.php';
require_once $SRC . '/controllers/PasswordResetController.php';
require_once $SRC . '/controllers/MeController.php';

$method = $_SERVER['REQUEST_METHOD'];
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$route = '/' . ltrim(substr($uriPath, strlen($base)), '/');
$route = preg_replace('#^/api#', '', $route);

try {

if ($route === '/health' && $method === 'GET') {
  json_response(['status' => 'ok']);
  exit;
}

if ($route === '/login' && $method === 'POST') { AuthController::login(); exit; }
if ($route === '/auth/google' && $method === 'POST') { AuthController::google(); exit; }
if ($route === '/token/refresh' && $method === 'POST') { AuthController::refresh(); exit; }
if ($route === '/logout' && $method === 'POST') { AuthController::logout(); exit; }
if ($route === '/me' && $method === 'GET') { AuthController::me(); exit; }

if ($route === '/register') { json_response(['error' => 'Registration disabled'], 403); exit; }

if ($route === '/password-reset/request' && $method === 'POST') { PasswordResetController::request(); exit; }
if ($route === '/password-reset/verify'  && $method === 'POST') { PasswordResetController::verify();  exit; }
if ($route === '/password-reset/confirm' && $method === 'POST') { PasswordResetController::confirm(); exit; }

// Self-service endpoints (any authenticated user)
if ($route === '/me'                  && $method === 'PATCH') { MeController::updateProfile(); exit; }
if ($route === '/me/licenses'         && $method === 'GET')   { MeController::licenses();      exit; }
if ($route === '/me/avatar'           && $method === 'POST')   { MeController::uploadAvatar();  exit; }
if ($route === '/me/avatar'           && $method === 'DELETE') { MeController::deleteAvatar();  exit; }
if ($route === '/me/password'         && $method === 'POST')  { MeController::changePassword(); exit; }
if ($route === '/me/renewal-request'  && $method === 'POST')  { MeController::requestRenewal(); exit; }
if ($route === '/me/license-activity' && $method === 'GET')   { MeController::licenseActivity(); exit; }

if ($route === '/admin/stats' && $method === 'GET') { AdminController::stats(); exit; }
if ($route === '/admin/users' && $method === 'GET') { AdminController::listUsers(); exit; }
if ($route === '/admin/users' && $method === 'POST') { AdminController::createUser(); exit; }
if (preg_match('#^/admin/users/(\d+)$#', $route, $m) && $method === 'DELETE') { $_GET['id'] = (int)$m[1]; AdminController::deleteUser(); exit; }
if (preg_match('#^/admin/users/(\d+)/reset-password$#', $route, $m) && $method === 'POST') { $_GET['id'] = (int)$m[1]; AdminController::resetPassword(); exit; }
if (preg_match('#^/admin/users/(\d+)/status$#', $route, $m) && $method === 'POST') { $_GET['id'] = (int)$m[1]; AdminController::updateStatus(); exit; }

if ($route === '/admin/licenses' && $method === 'GET') { LicenseController::listLicenses(); exit; }
if ($route === '/admin/licenses' && $method === 'POST') { LicenseController::createLicense(); exit; }
if ($route === '/admin/activity' && $method === 'GET') { LicenseController::getActivity(); exit; }
if ($route === '/admin/licenses/bulk-reactivate' && $method === 'POST') { LicenseController::bulkReactivate(); exit; }
if ($route === '/admin/licenses/bulk-revoke' && $method === 'POST') { LicenseController::bulkRevoke(); exit; }
if ($route === '/admin/licenses/bulk-delete' && $method === 'POST') { LicenseController::bulkDelete(); exit; }
if (preg_match('#^/admin/licenses/(\d+)/assign$#', $route, $m) && $method === 'POST') { $_GET['id'] = (int)$m[1]; LicenseController::assignLicense(); exit; }
if (preg_match('#^/admin/licenses/(\d+)/reactivate$#', $route, $m) && $method === 'POST') { $_GET['id'] = (int)$m[1]; LicenseController::reactivateLicense(); exit; }
if (preg_match('#^/admin/licenses/(\d+)/revoke$#', $route, $m) && $method === 'POST') { $_GET['id'] = (int)$m[1]; LicenseController::revokeLicense(); exit; }
if (preg_match('#^/admin/licenses/(\d+)$#', $route, $m) && $method === 'DELETE') { $_GET['id'] = (int)$m[1]; LicenseController::deleteLicense(); exit; }

json_response(['error' => 'Not found'], 404);

} catch (PDOException $e) {
  log_error('PDO', $e->getMessage(), ['code' => $e->getCode(), 'route' => $route]);
  json_response(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Throwable $e) {
  log_error('Fatal', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'route' => $route]);
  json_response(['error' => $e->getMessage()], 500);
}
