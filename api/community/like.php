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

    // POST 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['postId'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Post ID is required']);
        exit;
    }

    $postId = (int)$input['postId'];

    // 게시글 존재 확인
    $checkStmt = $pdo->prepare('SELECT id FROM community_posts WHERE id = ? AND is_deleted = 0');
    $checkStmt->execute([$postId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => '게시글을 찾을 수 없습니다']);
        exit;
    }

    // 현재 좋아요 상태 확인
    $likeStmt = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
    $likeStmt->execute([$postId, $userId]);
    $likeExists = $likeStmt->fetch();

    // 트랜잭션 시작
    $pdo->beginTransaction();

    try {
        if ($likeExists) {
            // 좋아요 취소
            $deleteLikeStmt = $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
            $deleteLikeStmt->execute([$postId, $userId]);

            $updateCountStmt = $pdo->prepare('UPDATE community_posts SET like_count = like_count - 1 WHERE id = ?');
            $updateCountStmt->execute([$postId]);

            $isLiked = false;
        } else {
            // 좋아요 추가
            $insertLikeStmt = $pdo->prepare('INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())');
            $insertLikeStmt->execute([$postId, $userId]);

            $updateCountStmt = $pdo->prepare('UPDATE community_posts SET like_count = like_count + 1 WHERE id = ?');
            $updateCountStmt->execute([$postId]);

            $isLiked = true;
        }

        // 트랜잭션 커밋
        $pdo->commit();

        // 업데이트된 좋아요 수 조회
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
        // 트랜잭션 롤백
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