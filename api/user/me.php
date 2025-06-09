<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

try {
    $stmt = $pdo->prepare('
        SELECT 
            id,
            user_id,
            user_name,
            created_at,
            profile_pokemon_id
        FROM users 
        WHERE id = ?
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['message' => 'User not found']);
        exit;
    }

    $statsStmt = $pdo->prepare('
        SELECT 
            total_captures,
            unique_pokemon_count,
            last_capture_date,
            evolution_count
        FROM user_stats 
        WHERE user_id = ?
    ');
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM captures WHERE user_id = ?');
        $totalStmt->execute([$userId]);
        $totalCaptures = $totalStmt->fetchColumn();

        $uniqueStmt = $pdo->prepare('SELECT COUNT(DISTINCT pokemon_id) FROM captures WHERE user_id = ?');
        $uniqueStmt->execute([$userId]);
        $uniquePokemon = $uniqueStmt->fetchColumn();

        $lastStmt = $pdo->prepare('SELECT MAX(captured_at) FROM captures WHERE user_id = ?');
        $lastStmt->execute([$userId]);
        $lastCaptureDate = $lastStmt->fetchColumn();

        $stats = [
            'total_captures' => $totalCaptures,
            'unique_pokemon_count' => $uniquePokemon,
            'last_capture_date' => $lastCaptureDate,
            'evolution_count' => 0
        ];
    }

    $totalPokemonCount = 151;
    $completionRate = $stats['unique_pokemon_count'] > 0 
        ? round(($stats['unique_pokemon_count'] / $totalPokemonCount) * 100, 1) 
        : 0;

    $favoriteStmt = $pdo->prepare('
        SELECT 
            pokemon_id,
            COUNT(*) as count
        FROM captures 
        WHERE user_id = ?
        GROUP BY pokemon_id
        ORDER BY count DESC, pokemon_id ASC
        LIMIT 1
    ');
    $favoriteStmt->execute([$userId]);
    $favoritePokemon = $favoriteStmt->fetch(PDO::FETCH_ASSOC);

    $recentStmt = $pdo->prepare('
        SELECT COUNT(*) 
        FROM captures 
        WHERE user_id = ? AND captured_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ');
    $recentStmt->execute([$userId]);
    $recentCaptures = $recentStmt->fetchColumn();

    echo json_encode([
        'message' => 'Success',
        'user' => [
            'id' => $user['user_id'],
            'username' => $user['user_name'],
            'user_id' => $user['user_id'],
            'joinDate' => $user['created_at'],
            'profilePokemonId' => (int)($user['profile_pokemon_id'] ?? 25)
        ],
        'stats' => [
            'totalPokemon' => $totalPokemonCount,
            'caughtPokemon' => (int)$stats['unique_pokemon_count'],
            'completionRate' => $completionRate,
            'totalCatches' => (int)$stats['total_captures'],
            'evolutionCount' => (int)($stats['evolution_count'] ?? 0),
            'lastLoginDate' => date('Y-m-d'),
            'recentCaptures' => (int)$recentCaptures,
            'favoritePokemonNumber' => $favoritePokemon ? (int)$favoritePokemon['pokemon_id'] : null,
            'favoritePokemonCount' => $favoritePokemon ? (int)$favoritePokemon['count'] : 0
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to fetch user info',
        'error' => $e->getMessage()
    ]);
}
?>