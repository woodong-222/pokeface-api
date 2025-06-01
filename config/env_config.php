<?php
$env = parse_ini_file(__DIR__ . '/../.env');

define('JWT_SECRET', $env['JWT_SECRET']);

define('DB_HOST', $env['DB_HOST']);
define('DB_PORT', $env['DB_PORT']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
