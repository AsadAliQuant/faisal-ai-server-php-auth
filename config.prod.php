<?php
// PRODUCTION CONFIG — for cPanel / GoDaddy deployment
// To deploy: copy this file to config.php on the server
return [
  'db' => [
    'host'    => 'localhost',
    'port'    => 3306,
    'name'    => 'nupthasa_aiauth',
    'user'    => 'nupthasa_aiauth',
    'pass'    => 'N,=AI-TlChs@%[Dq',
    'charset' => 'utf8mb4'
  ],
  'jwt' => [
    'secret'             => 'f98e6638bb392ad17f57efb3762886f557c41af6e48e9917ff328f0eb9e540cb',
    'alg'                => 'HS256',
    'issuer'             => 'ai-auth-api',
    'access_ttl_seconds' => 900
  ],
  'refresh_secret'      => '28d817a2405251014a55fb229c97c3e1d973c1b81e64f8972b70ebe16e4fc41f',
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
