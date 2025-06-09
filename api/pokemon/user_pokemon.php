<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        exit;
    }

    $userId = $currentUser['id'];

    $sql = '
        SELECT 
            pokemon_id,
            evolution_stage,
            captured_at,
            updated_at
        FROM user_pokemon 
        WHERE user_id = ?
        ORDER BY pokemon_id ASC
    ';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pokemons = [];
    foreach ($result as $row) {
        $pokemons[] = [
            'pokemon_id' => (int)$row['pokemon_id'],
            'evolution_stage' => (int)$row['evolution_stage'],
            'captured_at' => $row['captured_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode([
        'message' => 'Success',
        'pokemons' => $pokemons,
        'count' => count($pokemons)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to fetch user pokemons',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>