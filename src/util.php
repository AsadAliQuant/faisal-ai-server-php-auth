<?php
function log_error(string $context, string $message, array $extra = []): void {
  $dir = __DIR__ . '/../storage/logs';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $line = date('Y-m-d H:i:s') . " [$context] $message";
  if ($extra) $line .= ' ' . json_encode($extra);
  @file_put_contents($dir . '/error.log', $line . PHP_EOL, FILE_APPEND);
}

function read_json() {
  $raw = file_get_contents('php://input');
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

function json_response($data, $status = 200) {
  header('Content-Type: application/json');
  http_response_code($status);
  echo json_encode($data);
}

function require_fields($arr, $fields) {
  foreach ($fields as $f) {
    if (!isset($arr[$f]) || $arr[$f] === '') {
      json_response(['error' => 'Missing field: '.$f], 422);
      exit;
    }
  }
}
