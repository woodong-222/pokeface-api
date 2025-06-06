<?php
require_once __DIR__ . '/../../config/header.php';

require_once '../../db/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? '';
$user_name = $data['user_name'] ?? '';

if (empty($user_id) || empty($user_name)) {
    http_response_code(400);
    echo json_encode(['error' => '필수 항목 누락']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_name = ?");
$stmt->execute([$user_id, $user_name]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => '일치하는 회원 정보가 없습니다.']);
    exit;
}

echo json_encode(['message' => '확인 완료']);
