<?php
return [
  'db' => [
    'host'    => '127.0.0.1',
    'port'    => 3306,
    'name'    => 'ai_auth',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4'
  ],
  'jwt' => [
    'secret'             => 'change_this_in_production',
    'alg'                => 'HS256',
    'issuer'             => 'ai-auth-api',
    'access_ttl_seconds' => 900
  ],
  'refresh_secret'     => 'change_this_refresh_secret',
  'refresh_ttl_seconds' => 2592000,
  'initial_admin_email' => 'admin@example.com',
  'seed_token'          => 'change_this_seed_token',
  'smtp' => [
    'host'      => 'mail.yourdomain.com',
    'port'      => 465,
    'username'  => 'noreply@yourdomain.com',
    'password'  => 'your_email_password',
    'from'      => 'noreply@yourdomain.com',
    'from_name' => 'Your App Name'
  ]
];
