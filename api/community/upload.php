<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

try {
    require_once __DIR__ . '/../../db/db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        exit;
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['message' => '이미지 파일이 필요합니다']);
        exit;
    }

    $file = $_FILES['image'];

    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['message' => '이미지 크기는 5MB 이하만 업로드 가능합니다']);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['message' => 'JPG, PNG, GIF, WEBP 형식만 지원합니다']);
        exit;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'community_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;

    $uploadDir = __DIR__ . '/../../uploads/community/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        http_response_code(500);
        echo json_encode(['message' => '이미지 업로드에 실패했습니다']);
        exit;
    }

    echo json_encode([
        'message' => 'Image uploaded successfully',
        'filename' => $filename,
        'url' => '/uploads/community/' . $filename,
        'size' => $file['size'],
        'type' => $mimeType
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to upload image',
        'error' => $e->getMessage()
    ]);
}