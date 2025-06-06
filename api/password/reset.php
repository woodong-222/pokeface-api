<?php
require_once __DIR__ . '/../../config/header.php';

require_once '../../db/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? '';
$new_pw = $data['new_user_pw'] ?? '';
$confirm_pw = $data['confirm_user_pw'] ?? '';

if (empty($user_id) || empty($new_pw) || empty($confirm_pw)) {
    http_response_code(400);
    echo json_encode(['error' => '필수 항목 누락']);
    exit;
}

if ($new_pw !== $confirm_pw) {
    http_response_code(400);
    echo json_encode(['error' => '비밀번호가 일치하지 않습니다.']);
    exit;
}

$hashed_pw = password_hash($new_pw, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET user_pw = ? WHERE user_id = ?");
$success = $stmt->execute([$hashed_pw, $user_id]);

if ($success) {
    echo json_encode(['message' => '비밀번호가 성공적으로 변경되었습니다.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => '비밀번호 변경 실패']);
}