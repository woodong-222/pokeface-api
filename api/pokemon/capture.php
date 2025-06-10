<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['message' => 'Image upload failed', 'upload_error' => $_FILES['image']['error'] ?? 'No file']);
    exit;
}

$uploadedFile = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($uploadedFile['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid image type. Only JPEG and PNG allowed']);
    exit;
}

if ($uploadedFile['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['message' => 'File too large. Maximum 5MB allowed']);
    exit;
}

function sanitizeFileName($filename) {
    $pathInfo = pathinfo($filename);
    $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';
    $baseName = $pathInfo['filename'];
    
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = trim($safeName, '_');
    
    if (strlen($safeName) < 3) {
        $safeName = 'capture_' . date('YmdHis');
    }
    
    return $safeName . '.' . $extension;
}

$uploadDir = __DIR__ . '/../../uploads/captures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalFileName = $uploadedFile['name'];
$safeFileName = sanitizeFileName($originalFileName);
$tempFilePath = $uploadDir . $safeFileName;

$counter = 1;
$baseSafeFileName = $safeFileName;
while (file_exists($tempFilePath)) {
    $pathInfo = pathinfo($baseSafeFileName);
    $safeFileName = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
    $tempFilePath = $uploadDir . $safeFileName;
    $counter++;
}

if (!move_uploaded_file($uploadedFile['tmp_name'], $tempFilePath)) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to save uploaded file']);
    exit;
}

try {
    $pythonPath = 'python3';
    
    $scriptPath = __DIR__ . '/../../scripts/face_embedding.py';
    
    if (!file_exists($scriptPath)) {
        throw new Exception("Python script not found: $scriptPath");
    }
    
    $cmd = escapeshellcmd("$pythonPath $scriptPath " . escapeshellarg($tempFilePath));
    
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );
    
    $process = proc_open($cmd, $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        fclose($pipes[0]);
        
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        $return_value = proc_close($process);
        
        error_log("Python script output: " . $output);
        if (!empty($errors)) {
            error_log("Python script errors: " . $errors);
        }
        error_log("Python script return value: " . $return_value);
        
        if (empty($output)) {
            throw new Exception("Python script returned empty output. Errors: " . $errors);
        }
        
        $lines = explode("\n", trim($output));
        $jsonLine = null;
        
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if (!empty($line) && (str_starts_with($line, '{') || str_starts_with($line, '['))) {
                $jsonLine = $line;
                break;
            }
        }
        
        if (!$jsonLine) {
            throw new Exception("No valid JSON found in Python script output. Raw output: " . $output);
        }
        
        $result = json_decode($jsonLine, true);
        
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parsing failed: " . json_last_error_msg() . ". Raw JSON: " . $jsonLine);
        }
        
        if (isset($result['error'])) {
            throw new Exception($result['error']);
        }
        
        if (!isset($result['embedding'])) {
            throw new Exception("No embedding found in Python script output");
        }
        
        $embedding = $result['embedding'];
        
    } else {
        throw new Exception("Failed to execute Python script");
    }
    
    $env = parse_ini_file(__DIR__ . '/../../.env');
    $developerEmbedding = null;
    
    if (isset($env['DEVELOPER_FACE_EMBEDDING'])) {
        $developerEmbedding = json_decode($env['DEVELOPER_FACE_EMBEDDING'], true);
    }
    
    $stmt = $pdo->prepare('SELECT id, face_embedding, pokemon_id FROM captures WHERE user_id = ? AND face_embedding IS NOT NULL');
    $stmt->execute([$currentUser['id']]);
    $existingFaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $matchedFace = null;
    $threshold = 0.75;
    
    foreach ($existingFaces as $face) {
        $storedEmbedding = json_decode($face['face_embedding'], true);
        if ($storedEmbedding && is_array($storedEmbedding)) {
            $similarity = calculateCosineSimilarity($embedding, $storedEmbedding);
            
            error_log("Face similarity: " . $similarity . " for pokemon_id: " . $face['pokemon_id']);
            
            if ($similarity > $threshold) {
                $matchedFace = $face;
                break;
            }
        }
    }
    
    if ($matchedFace) {
        $pokemonId = $matchedFace['pokemon_id'];
        
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
        
        error_log("Matched existing face for pokemon_id: " . $pokemonId . " - same person, no evolution");
        
        $stmt = $pdo->prepare('SELECT evolution_stage FROM user_pokemon WHERE user_id = ? AND pokemon_id = ?');
        $stmt->execute([$currentUser['id'], $pokemonId]);
        $currentStage = $stmt->fetchColumn();
        
        if (!$currentStage) {
            $minStage = getMinEvolutionStage($pokemonId);
            $stmt = $pdo->prepare('INSERT INTO user_pokemon (user_id, pokemon_id, evolution_stage, captured_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$currentUser['id'], $pokemonId, $minStage]);
            $currentStage = $minStage;
        }
        
        echo json_encode([
            'message' => 'Pokemon captured successfully',
            'result' => [
                'pokemonId' => $pokemonId,
                'pokemonName' => getPokemonName($pokemonId),
                'pokemonImage' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$pokemonId}.png",
                'isNew' => false,
                'evolutionStage' => $currentStage,
                'evolved' => false
            ]
        ]);
        
    } else {
        error_log("No matching face found, assigning new pokemon");
        
        $availableStarter = getAvailableStarterPokemon($currentUser['id']);
        
        if (!$availableStarter) {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            http_response_code(400);
            echo json_encode(['message' => 'No available starter Pokemon']);
            exit;
        }
        
        $pokemonId = $availableStarter['id'];
        
        error_log("Assigned new pokemon_id: " . $pokemonId);
        
        $stmt = $pdo->prepare('INSERT INTO captures (user_id, pokemon_id, image_path, original_filename, face_embedding, captured_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$currentUser['id'], $pokemonId, $safeFileName, $originalFileName, json_encode($embedding)]);
        
        $stmt = $pdo->prepare('SELECT evolution_stage FROM user_pokemon WHERE user_id = ? AND pokemon_id = ?');
        $stmt->execute([$currentUser['id'], $pokemonId]);
        $currentStage = $stmt->fetchColumn();
        
        $isNew = false;
        $evolved = false;
        $newStage = 1;
        
        if (!$currentStage) {
            $minStage = getMinEvolutionStage($pokemonId);
            $stmt = $pdo->prepare('INSERT INTO user_pokemon (user_id, pokemon_id, evolution_stage, captured_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$currentUser['id'], $pokemonId, $minStage]);
            $newStage = $minStage;
            $isNew = true;
            $evolved = false;
            
            error_log("First capture of pokemon_id: {$pokemonId} at stage: {$minStage}");
        } else {
            $maxStage = getMaxEvolutionStage($pokemonId);
            $newStage = min($currentStage + 1, $maxStage);
            $evolved = $newStage > $currentStage;
            
            if ($evolved) {
                $stmt = $pdo->prepare('UPDATE user_pokemon SET evolution_stage = ?, updated_at = NOW() WHERE user_id = ? AND pokemon_id = ?');
                $stmt->execute([$newStage, $currentUser['id'], $pokemonId]);
                
                error_log("Pokemon evolved from stage {$currentStage} to {$newStage}");
            } else {
                error_log("Pokemon already at max evolution stage: {$currentStage}");
            }
        }
        
        updateUserStats($currentUser['id'], $evolved);
        
        echo json_encode([
            'message' => 'Pokemon captured successfully',
            'result' => [
                'pokemonId' => $pokemonId,
                'pokemonName' => getPokemonName($pokemonId),
                'pokemonImage' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$pokemonId}.png",
                'isNew' => $isNew,
                'evolutionStage' => $newStage,
                'evolved' => $evolved
            ]
        ]);
    }
    
} catch (Exception $e) {
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
    
    http_response_code(500);
    echo json_encode([
        'message' => 'Face detection failed',
        'error' => $e->getMessage()
    ]);
}

function updateUserStats($userId, $evolutionOccurred = false) {
    global $pdo;
    
    try {
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM captures WHERE user_id = ?');
        $totalStmt->execute([$userId]);
        $totalCaptures = $totalStmt->fetchColumn();
        
        $uniqueStmt = $pdo->prepare('SELECT COUNT(DISTINCT pokemon_id) FROM captures WHERE user_id = ?');
        $uniqueStmt->execute([$userId]);
        $uniquePokemon = $uniqueStmt->fetchColumn();
        
        $lastStmt = $pdo->prepare('SELECT MAX(captured_at) FROM captures WHERE user_id = ?');
        $lastStmt->execute([$userId]);
        $lastCaptureDate = $lastStmt->fetchColumn();
        
        $evolutionStmt = $pdo->prepare('SELECT evolution_count FROM user_stats WHERE user_id = ?');
        $evolutionStmt->execute([$userId]);
        $currentEvolutionCount = $evolutionStmt->fetchColumn() ?: 0;
        
        $newEvolutionCount = $evolutionOccurred ? $currentEvolutionCount + 1 : $currentEvolutionCount;
        
        $updateStmt = $pdo->prepare('
            INSERT INTO user_stats (user_id, total_captures, unique_pokemon_count, last_capture_date, evolution_count, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                total_captures = VALUES(total_captures),
                unique_pokemon_count = VALUES(unique_pokemon_count),
                last_capture_date = VALUES(last_capture_date),
                evolution_count = VALUES(evolution_count),
                updated_at = NOW()
        ');
        $updateStmt->execute([$userId, $totalCaptures, $uniquePokemon, $lastCaptureDate, $newEvolutionCount]);
        
        error_log("Updated user_stats for user_id: {$userId}, total: {$totalCaptures}, unique: {$uniquePokemon}, evolutions: {$newEvolutionCount}");
        
    } catch (Exception $e) {
        error_log("Failed to update user_stats: " . $e->getMessage());
    }
}

function calculateCosineSimilarity($vec1, $vec2) {
    if (count($vec1) !== count($vec2)) {
        return 0;
    }
    
    $dotProduct = 0;
    $magnitude1 = 0;
    $magnitude2 = 0;
    
    for ($i = 0; $i < count($vec1); $i++) {
        $dotProduct += $vec1[$i] * $vec2[$i];
        $magnitude1 += $vec1[$i] * $vec1[$i];
        $magnitude2 += $vec2[$i] * $vec2[$i];
    }
    
    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);
    
    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0;
    }
    
    return $dotProduct / ($magnitude1 * $magnitude2);
}

function getMaxEvolutionStage($pokemonId) {
    $evolutionStages = [
        1 => 3, 2 => 3, 3 => 3,
        4 => 3, 5 => 3, 6 => 3,
        7 => 3, 8 => 3, 9 => 3,
        10 => 3, 11 => 3, 12 => 3,
        13 => 3, 14 => 3, 15 => 3,
        16 => 3, 17 => 3, 18 => 3,
        29 => 3, 30 => 3, 31 => 3,
        32 => 3, 33 => 3, 34 => 3,
        43 => 3, 44 => 3, 45 => 3,
        60 => 3, 61 => 3, 62 => 3,
        63 => 3, 64 => 3, 65 => 3,
        66 => 3, 67 => 3, 68 => 3,
        69 => 3, 70 => 3, 71 => 3,
        74 => 3, 75 => 3, 76 => 3,
        92 => 3, 93 => 3, 94 => 3,
        
        19 => 2, 20 => 2,
        21 => 2, 22 => 2,
        23 => 2, 24 => 2,
        25 => 2, 26 => 2,
        27 => 2, 28 => 2,
        35 => 2, 36 => 2,
        37 => 2, 38 => 2,
        39 => 2, 40 => 2,
        41 => 2, 42 => 2,
        46 => 2, 47 => 2,
        48 => 2, 49 => 2,
        50 => 2, 51 => 2,
        52 => 2, 53 => 2,
        54 => 2, 55 => 2,
        56 => 2, 57 => 2,
        58 => 2, 59 => 2,
        72 => 2, 73 => 2,
        77 => 2, 78 => 2,
        79 => 2, 80 => 2,
        81 => 2, 82 => 2,
        84 => 2, 85 => 2,
        86 => 2, 87 => 2,
        88 => 2, 89 => 2,
        90 => 2, 91 => 2,
        96 => 2, 97 => 2,
        98 => 2, 99 => 2,
        100 => 2, 101 => 2,
        102 => 2, 103 => 2,
        104 => 2, 105 => 2,
        109 => 2, 110 => 2,
        111 => 2, 112 => 2,
        116 => 2, 117 => 2,
        118 => 2, 119 => 2,
        120 => 2, 121 => 2,
        129 => 2, 130 => 2,
        
        133 => 4, 134 => 2, 135 => 3, 136 => 4,
    ];
    
    return $evolutionStages[$pokemonId] ?? 1;
}

function getMinEvolutionStage($pokemonId) {
    $secondStageStarters = [
        20, 22, 24, 26, 28, 36, 38, 40, 42, 47, 49, 51, 53, 55, 57, 59,
        73, 78, 80, 82, 85, 87, 89, 91, 97, 99, 101, 103, 105, 110, 112,
        117, 119, 121, 130, 134, 135, 136
    ];
    
    return in_array($pokemonId, $secondStageStarters) ? 2 : 1;
}

function getPokemonName($pokemonId) {
    $koreanNames = [
        1 => '이상해씨', 2 => '이상해풀', 3 => '이상해꽃',
        4 => '파이리', 5 => '리자드', 6 => '리자몽',
        7 => '꼬부기', 8 => '어니부기', 9 => '거북왕',
        10 => '캐터피', 11 => '단데기', 12 => '버터플',
        13 => '뿔충이', 14 => '딱충이', 15 => '독침붕',
        16 => '구구', 17 => '피죤', 18 => '피죤투',
        19 => '꼬렛', 20 => '레트라',
        21 => '깨비참', 22 => '깨비드릴조',
        23 => '아보', 24 => '아보크',
        25 => '피카츄', 26 => '라이츄',
        27 => '모래두지', 28 => '고지',
        29 => '니드런♀', 30 => '니드리나', 31 => '니드퀸',
        32 => '니드런♂', 33 => '니드리노', 34 => '니드킹',
        35 => '삐삐', 36 => '픽시',
        37 => '식스테일', 38 => '나인테일',
        39 => '푸린', 40 => '푸크린',
        41 => '주바트', 42 => '골뱃',
        43 => '뚜벅쵸', 44 => '냄새꼬', 45 => '라플레시아',
        46 => '파라스', 47 => '파라섹트',
        48 => '콘팡', 49 => '도나리',
        50 => '디그다', 51 => '닥트리오',
        52 => '나옹', 53 => '페르시온',
        54 => '고라파덕', 55 => '골덕',
        56 => '망키', 57 => '성원숭',
        58 => '가디', 59 => '윈디',
        60 => '발챙이', 61 => '슈륙챙이', 62 => '강챙이',
        63 => '캐이시', 64 => '윤겔라', 65 => '후딘',
        66 => '알통몬', 67 => '근육몬', 68 => '괴력몬',
        69 => '모다피', 70 => '우츠동', 71 => '우츠보트',
        72 => '왕눈해', 73 => '독파리',
        74 => '꼬마돌', 75 => '데구리', 76 => '딱구리',
        77 => '포니타', 78 => '날쌩마',
        79 => '야돈', 80 => '야도란',
        81 => '코일', 82 => '레어코일',
        83 => '파오리',
        84 => '두두', 85 => '두트리오',
        86 => '쥬쥬', 87 => '쥬레곤',
        88 => '질퍽이', 89 => '질뻐기',
        90 => '셀러', 91 => '파르셀',
        92 => '고오스', 93 => '고우스트', 94 => '팬텀',
        95 => '롱스톤',
        96 => '슬리프', 97 => '슬리퍼',
        98 => '크랩', 99 => '킹크랩',
        100 => '찌리리공', 101 => '붐볼',
        102 => '아라리', 103 => '나시',
        104 => '탕구리', 105 => '텅구리',
        106 => '시라소몬', 107 => '홍수몬',
        108 => '내루미',
        109 => '또가스', 110 => '또도가스',
        111 => '뿔카노', 112 => '코뿌리',
        113 => '럭키',
        114 => '덩쿠리',
        115 => '캥카',
        116 => '쏘드라', 117 => '시드라',
        118 => '콘치', 119 => '왕콘치',
        120 => '별가사리', 121 => '아쿠스타',
        122 => '마임맨',
        123 => '스라크',
        124 => '루주라',
        125 => '에레브',
        126 => '마그마',
        127 => '쁘사이저',
        128 => '켄타로스',
        129 => '잉어킹', 130 => '갸라도스',
        131 => '라프라스',
        132 => '메타몽',
        133 => '이브이', 134 => '샤미드', 135 => '쥬피썬더', 136 => '부스터',
        137 => '폴리곤',
        138 => '암나이트', 139 => '암스타',
        140 => '투구', 141 => '투구푸스',
        142 => '프테라',
        143 => '잠만보',
        144 => '프리저', 145 => '썬더', 146 => '파이어',
        147 => '미뇽', 148 => '신뇽', 149 => '망나뇽',
        150 => '뮤츠',
        151 => '뮤'
    ];
    
    return $koreanNames[$pokemonId] ?? "포켓몬 #{$pokemonId}";
}

function getAvailableStarterPokemon($userId) {
    $starterPokemons = [
        ['id' => 1, 'name' => '이상해씨'],
        ['id' => 4, 'name' => '파이리'],
        ['id' => 7, 'name' => '꼬부기'],
        ['id' => 25, 'name' => '피카츄'],
        ['id' => 10, 'name' => '캐터피'],
        ['id' => 13, 'name' => '뿔충이'],
        ['id' => 16, 'name' => '구구'],
        ['id' => 19, 'name' => '꼬렛'],
        ['id' => 21, 'name' => '깨비참'],
        ['id' => 23, 'name' => '아보'],
        ['id' => 27, 'name' => '모래두지'],
        ['id' => 29, 'name' => '니드런♀'],
        ['id' => 32, 'name' => '니드런♂'],
        ['id' => 35, 'name' => '삐삐'],
        ['id' => 37, 'name' => '식스테일'],
        ['id' => 39, 'name' => '푸린'],
        ['id' => 41, 'name' => '주바트'],
        ['id' => 43, 'name' => '뚜벅쵸'],
        ['id' => 46, 'name' => '파라스'],
        ['id' => 48, 'name' => '콘팡'],
        ['id' => 50, 'name' => '디그다'],
        ['id' => 52, 'name' => '나옹'],
        ['id' => 54, 'name' => '고라파덕'],
        ['id' => 56, 'name' => '망키'],
        ['id' => 58, 'name' => '가디'],
        ['id' => 60, 'name' => '발챙이'],
        ['id' => 63, 'name' => '캐이시'],
        ['id' => 66, 'name' => '알통몬'],
        ['id' => 69, 'name' => '모다피'],
        ['id' => 72, 'name' => '왕눈해'],
        ['id' => 74, 'name' => '꼬마돌'],
        ['id' => 77, 'name' => '포니타'],
        ['id' => 79, 'name' => '야돈'],
        ['id' => 81, 'name' => '코일'],
        ['id' => 84, 'name' => '두두'],
        ['id' => 86, 'name' => '쥬쥬'],
        ['id' => 88, 'name' => '질퍽이'],
        ['id' => 90, 'name' => '셀러'],
        ['id' => 92, 'name' => '고오스'],
        ['id' => 96, 'name' => '슬리프'],
        ['id' => 98, 'name' => '크랩'],
        ['id' => 100, 'name' => '찌리리공'],
        ['id' => 102, 'name' => '아라리'],
        ['id' => 104, 'name' => '탕구리'],
        ['id' => 109, 'name' => '또가스'],
        ['id' => 111, 'name' => '뿔카노'],
        ['id' => 116, 'name' => '쏘드라'],
        ['id' => 118, 'name' => '콘치'],
        ['id' => 120, 'name' => '별가사리'],
        ['id' => 129, 'name' => '잉어킹'],
        ['id' => 133, 'name' => '이브이'],
    ];
    
    $randomIndex = array_rand($starterPokemons);
    return $starterPokemons[$randomIndex];
}
?>