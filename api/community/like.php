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

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['postId'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Post ID is required']);
        exit;
    }

    $postId = (int)$input['postId'];

    $checkStmt = $pdo->prepare('SELECT id FROM community_posts WHERE id = ? AND is_deleted = 0');
    $checkStmt->execute([$postId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => '게시글을 찾을 수 없습니다']);
        exit;
    }

    $likeStmt = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
    $likeStmt->execute([$postId, $userId]);
    $likeExists = $likeStmt->fetch();

    $pdo->beginTransaction();

    try {
        if ($likeExists) {
            $deleteLikeStmt = $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
            $deleteLikeStmt->execute([$postId, $userId]);

            $updateCountStmt = $pdo->prepare('UPDATE community_posts SET like_count = like_count - 1 WHERE id = ?');
            $updateCountStmt->execute([$postId]);

            $isLiked = false;
        } else {
            $insertLikeStmt = $pdo->prepare('INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())');
            $insertLikeStmt->execute([$postId, $userId]);

            $updateCountStmt = $pdo->prepare('UPDATE community_posts SET like_count = like_count + 1 WHERE id = ?');
            $updateCountStmt->execute([$postId]);

            $isLiked = true;
        }

        $pdo->commit();

        $countStmt = $pdo->prepare('SELECT like_count FROM community_posts WHERE id = ?');
        $countStmt->execute([$postId]);
        $likeCount = $countStmt->fetchColumn();

        echo json_encode([
            'message' => 'Success',
            'postId' => $postId,
            'isLiked' => $isLiked,
            'likeCount' => (int)$likeCount
        ]);

    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to toggle like',
        'error' => $e->getMessage()
    ]);
}