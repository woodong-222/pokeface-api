<?php
date_default_timezone_set('Asia/Seoul');

header('Content-Type: application/json; charset=utf-8');

$allowedOrigins = [
    'https://pokeface.kro.kr',
    'http://localhost:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/env_config.php';
require_once __DIR__ . '/../db/db.php';
?>