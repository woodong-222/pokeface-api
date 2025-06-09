<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../db/db.php';
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'notices'");
    if ($tableCheckStmt->rowCount() === 0) {
        throw new Exception('Table notices does not exist');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notices');
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

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
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $totalPages = ceil($totalCount / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

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