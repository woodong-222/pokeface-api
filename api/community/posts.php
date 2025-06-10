<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

try {
    $pdo->exec("SET time_zone = '+09:00'");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        exit;
    }

    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    if ($limit > 100) {
        $limit = 100;
    }

    $userId = $currentUser['id'];

    $sql = '
        SELECT 
            p.id,
            p.user_id,
            p.title,
            p.content,
            p.image,
            p.created_at,
            p.like_count,
            u.user_name as author,
            COALESCE(u.profile_pokemon_id, 25) as authorProfilePokemonId,
            CASE WHEN pl.user_id IS NOT NULL THEN 1 ELSE 0 END as isLiked
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN post_likes pl ON p.id = pl.post_id AND pl.user_id = ?
        WHERE p.is_deleted = 0
        ORDER BY p.created_at DESC
        LIMIT ?, ?
    ';
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $userId, PDO::PARAM_INT);
    $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    $stmt->bindParam(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $posts = [];
    foreach ($result as $row) {
        $posts[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'author' => $row['author'],
            'authorProfilePokemonId' => (int)$row['authorProfilePokemonId'],
            'createdAt' => $row['created_at'],
            'likeCount' => (int)$row['like_count'],
            'isLiked' => (bool)$row['isLiked'],
            'isAuthor' => ((int)$row['user_id'] === $userId),
            'image' => $row['image'] ? '../uploads/community/' . $row['image'] : null
        ];
    }

    $response = [
        'message' => 'Success',
        'posts' => $posts,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($posts)
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to fetch posts',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>