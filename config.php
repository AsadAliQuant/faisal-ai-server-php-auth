<?php
// LOCAL DEV CONFIG — XAMPP / MariaDB
// For production: use config.prod.php values
return [
  'db' => [
    'host'    => '127.0.0.1',
    'port'    => 3600,
    'name'    => 'ai_auth',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4'
  ],
  'jwt' => [
    'secret'             => 'S0UKOGTBEZKSeEcsR2TzmaJ4pVo6waFl',
    'alg'                => 'HS256',
    'issuer'             => 'ai-auth-api',
    'access_ttl_seconds' => 900
  ],
  'refresh_secret'      => 'EYeZ6sbNQo6A51Glgx2rn63plea4nQiT',
  'refresh_ttl_seconds' => 2592000,
  'initial_admin_email' => 'admin@localhost.com',
  'seed_token'          => '4e70567761ee766e407bfef9ce124a68',
  'smtp' => [
    'host'       => 'mail.ssma.sa',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'noreply@ssma.sa',
    'password'   => '}07**$g4JIE6~sL^',
    'from'       => 'noreply@ssma.sa',
    'from_name'  => 'AI Document Analyzer',
  ],
];
