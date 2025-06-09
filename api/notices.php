<?php
// 에러 표시 활성화 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/header.php';

// GET 메소드만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    // DB 연결 확인
    require_once __DIR__ . '/../db/db.php';
    
    // DB 연결 테스트
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // 페이지네이션 파라미터
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // 테이블 존재 확인
    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'notices'");
    if ($tableCheckStmt->rowCount() === 0) {
        throw new Exception('Table notices does not exist');
    }

    // 전체 공지사항 수 조회
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notices');
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

    // 공지사항 조회 (중요 공지 먼저, 그 다음 최신순)
    $stmt = $pdo->prepare('
        SELECT 
            id,
            title,
            content,
            is_important,
            created_at,
            updated_at
        FROM notices
        ORDER BY is_important DESC, created_at DESC
        LIMIT :limit OFFSET :offset
    ');
    
    // 정수로 바인딩
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 결과 데이터 포맷팅
    $formattedNotices = array_map(function($notice) {
        return [
            'id' => (int)$notice['id'],
            'title' => $notice['title'],
            'content' => $notice['content'],
            'isImportant' => (bool)$notice['is_important'],
            'createdAt' => $notice['created_at'],
            'updatedAt' => $notice['updated_at']
        ];
    }, $notices);

    // 페이지네이션 정보
    $totalPages = ceil($totalCount / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    // 응답
    echo json_encode([
        'message' => 'Success',
        'data' => $formattedNotices,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => (int)$totalCount,
            'limit' => $limit,
            'has_next' => $hasNextPage,
            'has_prev' => $hasPrevPage
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to fetch notices',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}