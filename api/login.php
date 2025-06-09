<?php
require_once __DIR__ . '/../config/header.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? '';
$password = $input['user_pw'] ?? '';

if (empty($user_id) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => '아이디와 비밀번호를 모두 입력해주세요.']);
    exit;
}

try {
    require_once __DIR__ . '/../db/db.php';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB 연결 실패: ' . $e->getMessage()]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['user_pw'])) {
    http_response_code(401);
    echo json_encode(['error' => '아이디 또는 비밀번호가 잘못되었습니다.']);
    exit;
}

$payload = [
    'sub' => $user['id'],
    'user_id' => $user['user_id'],
    'user_name' => $user['user_name'],
    'iat' => time(),
    'exp' => time() + 3600
];

$jwt = JWT::encode($payload, JWT_SECRET, 'HS256');

echo json_encode(['token' => $jwt]);
