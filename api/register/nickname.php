<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../vendor/autoload.php';

require_once '../../db/db.php';

$user_name = trim($_GET['user_name'] ?? '');

if (!$user_name) {
    http_response_code(400);
    echo json_encode(['detail' => '닉네임을 입력해주세요.']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_name = ?");
$stmt->execute([$user_name]);
$exists = $stmt->fetchColumn();

if ($exists > 0) {
    http_response_code(409);
    echo json_encode(['detail' => '이미 사용 중인 닉네임입니다.']);
} else {
    echo json_encode(['message' => '사용 가능한 닉네임입니다.']);
}
?>