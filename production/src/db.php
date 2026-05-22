<?php
function app_config() {
  $path = __DIR__ . '/../config.php';
  if (!file_exists($path)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Missing config.php']);
    exit;
  }
  return require $path;
}

function pdo() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;
  $c = app_config();
  $dsn = 'mysql:host='.$c['db']['host'].';port='.($c['db']['port'] ?? 3306).';dbname='.$c['db']['name'].';charset='.$c['db']['charset'];
  $pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  return $pdo;
}
