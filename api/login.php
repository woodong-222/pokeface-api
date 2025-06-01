<?php
require_once __DIR__ . '/../config/header.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JSON 응답 헤더
header('Content-Type: application/json');

// 입력값 받기
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? '';
$password = $input['user_pw'] ?? '';

// 입력값 유효성 검사
if (empty($user_id) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => '아이디와 비밀번호를 모두 입력해주세요.']);
    exit;
}

// DB 연결 시도
try {
    require_once __DIR__ . '/../db/db.php';  // 여기서 $pdo 로드됨
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB 연결 실패: ' . $e->getMessage()]);
    exit;
}

// 사용자 조회
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 로그인 유효성 검사
if (!$user || !password_verify($password, $user['user_pw'])) {
    http_response_code(401);
    echo json_encode(['error' => '아이디 또는 비밀번호가 잘못되었습니다.']);
    exit;
}

// JWT 토큰 생성
$payload = [
    'sub' => $user['id'],
    'user_id' => $user['user_id'],
    'user_name' => $user['user_name'],
    'iat' => time(),
    'exp' => time() + 3600  // 1시간 유효
];

$jwt = JWT::encode($payload, JWT_SECRET, 'HS256');

// 성공 응답
echo json_encode(['token' => $jwt]);
