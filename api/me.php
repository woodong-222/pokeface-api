<?php
require_once __DIR__ . '/../config/header.php';
require_once __DIR__ . '/../config/auth_middleware.php';

echo json_encode([
    'message' => 'Success',
    'user_id' => $userId
]);
