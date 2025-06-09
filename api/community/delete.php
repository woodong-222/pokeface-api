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

    // DELETE 요청 데이터 받기
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['postId'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Post ID is required']);
        exit;
    }

    $postId = (int)$input['postId'];

    // 게시글 존재 및 작성자 확인
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

    // 작성자 본인인지 확인
    if ((int)$post['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['message' => '본인이 작성한 게시글만 삭제할 수 있습니다']);
        exit;
    }

    // 트랜잭션 시작
    $pdo->beginTransaction();

    try {
        // 게시글을 완전 삭제가 아닌 is_deleted = 1로 소프트 삭제
        $deleteStmt = $pdo->prepare('
            UPDATE community_posts 
            SET is_deleted = 1, updated_at = NOW() 
            WHERE id = ?
        ');
        $deleteStmt->execute([$postId]);

        // 관련된 좋아요도 삭제
        $deleteLikesStmt = $pdo->prepare('
            DELETE FROM post_likes 
            WHERE post_id = ?
        ');
        $deleteLikesStmt->execute([$postId]);

        // 트랜잭션 커밋
        $pdo->commit();

        // 이미지 파일이 있다면 실제 파일도 삭제 (선택사항)
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
        // 트랜잭션 롤백
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