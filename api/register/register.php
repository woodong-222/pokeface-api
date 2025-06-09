<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../vendor/autoload.php';

require_once '../../db/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$user_id = trim($data['user_id'] ?? '');
$user_pw = trim($data['user_pw'] ?? '');
$confirm_pw = trim($data['confirm_user_pw'] ?? '');
$user_name = trim($data['user_name'] ?? '');

if (!$user_id || !$user_pw || !$confirm_pw || !$user_name) {
    http_response_code(400);
    echo json_encode(['detail' => '모든 필수 입력값을 채워주세요.']);
    exit;
}

if ($user_pw !== $confirm_pw) {
    http_response_code(400);
    echo json_encode(['detail' => '비밀번호와 비밀번호 확인이 일치하지 않습니다.']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_name = ?");
$stmt->execute([$user_name]);
if ($stmt->fetchColumn() > 0) {
    http_response_code(409);
    echo json_encode(['detail' => '이미 사용 중인 닉네임입니다.']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() > 0) {
    http_response_code(409);
    echo json_encode(['detail' => '이미 존재하는 아이디입니다.']);
    exit;
}

$hashed_pw = password_hash($user_pw, PASSWORD_BCRYPT);

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO users (user_id, user_pw, user_name) VALUES (?, ?, ?)");
    $result = $stmt->execute([$user_id, $hashed_pw, $user_name]);
    
    if ($result) {
        $newUserId = $pdo->lastInsertId();
        
        $randomPokemonId = rand(1, 149);
        $profileStmt = $pdo->prepare("UPDATE users SET profile_pokemon_id = ? WHERE id = ?");
        $profileStmt->execute([$randomPokemonId, $newUserId]);
        
        $statsStmt = $pdo->prepare("INSERT INTO user_stats (user_id) VALUES (?)");
        $statsStmt->execute([$newUserId]);
        
        $pdo->commit();
        
        echo json_encode(['message' => '회원가입에 성공했습니다.']);
    } else {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['detail' => '회원가입 중 오류가 발생했습니다.']);
    }
} catch (Exception $e) {
    $pdo->rollback();
    http_response_code(500);
    echo json_encode(['detail' => '회원가입 중 오류가 발생했습니다.']);
}
?>