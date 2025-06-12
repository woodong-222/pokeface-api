<?php
require_once __DIR__ . '/../../config/header.php';
require_once __DIR__ . '/../../config/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $orderBy = isset($_GET['order']) ? $_GET['order'] : 'desc';
    $orderBy = in_array($orderBy, ['asc', 'desc']) ? $orderBy : 'desc';

    $userId = $currentUser['id'];

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM captures_history WHERE user_id = :user_id');
    $countStmt->execute(['user_id' => $userId]);
    $totalCount = $countStmt->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT 
            id,
            pokemon_id,
            image_path,
            original_filename,
            captured_at
        FROM captures_history
        WHERE user_id = :user_id
        ORDER BY captured_at ' . strtoupper($orderBy) . '
        LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
    );
    
    $stmt->execute(['user_id' => $userId]);
    
    $captures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedCaptures = array_map(function($capture) {
        return [
            'id' => (int)$capture['id'],
            'pokemonNumber' => (int)$capture['pokemon_id'],
            'pokemonName' => getPokemonName($capture['pokemon_id']),
            // 이 부분을 수정
            'originalImage' => '/uploads/captures/' . $capture['image_path'], // '../' 제거
            'originalFilename' => $capture['original_filename'],
            'imageUrl' => '/uploads/captures/' . $capture['image_path'], // '../' 제거
            'captureDate' => $capture['captured_at'],
            'captureDateFormatted' => formatKoreanTime($capture['captured_at'])
        ];
    }, $captures);

    $totalPages = ceil($totalCount / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    echo json_encode([
        'message' => 'Success',
        'data' => $formattedCaptures,
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
        'message' => 'Failed to fetch album',
        'error' => $e->getMessage()
    ]);
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

function formatKoreanTime($datetime) {
    try {
        $date = new DateTime($datetime);
        $date->setTimezone(new DateTimeZone('Asia/Seoul'));
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $datetime;
    }
}
?>