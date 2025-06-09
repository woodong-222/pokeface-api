<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

try {
    require_once __DIR__ . '/../../db/db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

    $checkStmt = $pdo->prepare('
        SELECT user_id, image 
        FROM community_posts 
        WHERE id = ? AND is_deleted = 0
    ');
    $checkStmt->execute([$postId]);
    $post = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['message' => '게시글을 찾을 수 없습니다']);
        exit;
    }

    if ((int)$post['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['message' => '본인이 작성한 게시글만 삭제할 수 있습니다']);
        exit;
    }

    $pdo->beginTransaction();

    try {
        $deleteStmt = $pdo->prepare('
            UPDATE community_posts 
            SET is_deleted = 1, updated_at = NOW() 
            WHERE id = ?
        ');
        $deleteStmt->execute([$postId]);

        $deleteLikesStmt = $pdo->prepare('
            DELETE FROM post_likes 
            WHERE post_id = ?
        ');
        $deleteLikesStmt->execute([$postId]);

        $pdo->commit();

        if ($post['image']) {
            $imagePath = __DIR__ . '/../../uploads/community/' . $post['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        echo json_encode([
            'message' => 'Post deleted successfully',
            'postId' => $postId
        ]);

    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to delete post',
        'error' => $e->getMessage()
    ]);
}