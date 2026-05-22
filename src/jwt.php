<?php
function b64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode($data) {
  return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, string $secret): string {
  $header = ['typ' => 'JWT', 'alg' => 'HS256'];
  $segments = [b64url_encode(json_encode($header)), b64url_encode(json_encode($payload))];
  $signing_input = implode('.', $segments);
  $signature = hash_hmac('sha256', $signing_input, $secret, true);
  $segments[] = b64url_encode($signature);
  return implode('.', $segments);
}

function jwt_decode(string $jwt, string $secret): ?array {
  $parts = explode('.', $jwt);
  if (count($parts) !== 3) return null;
  [$h64, $p64, $s64] = $parts;
  $signing_input = $h64.'.'.$p64;
  $sig = b64url_decode($s64);
  $calc = hash_hmac('sha256', $signing_input, $secret, true);
  if (!hash_equals($calc, $sig)) return null;
  $payload = json_decode(b64url_decode($p64), true);
  if (!$payload) return null;
  if (isset($payload['exp']) && time() >= $payload['exp']) return null;
  return $payload;
}
