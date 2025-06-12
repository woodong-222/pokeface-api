<?php
require_once __DIR__ . '/env_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['message' => 'Authorization header missing or invalid']);
    exit;
}

$jwt = $matches[1];

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    $userId = $decoded->sub;

    $stmt = $pdo->prepare('SELECT id, user_name, user_id, created_at, profile_pokemon_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['message' => 'User not found']);
        exit;
    }

    $currentUser = $user;

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid or expired token', 'error' => $e->getMessage()]);
    exit;
}
?>