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

    $title = '';
    $content = '';
    $image = null;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            try {
                $image = saveUploadedImage($_FILES['image'], $userId);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['message' => $e->getMessage()]);
                exit;
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => '파일 크기가 서버 설정을 초과했습니다',
                UPLOAD_ERR_FORM_SIZE => '파일 크기가 폼 설정을 초과했습니다',
                UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드되었습니다',
                UPLOAD_ERR_NO_TMP_DIR => '임시 디렉토리가 없습니다',
                UPLOAD_ERR_CANT_WRITE => '디스크에 쓸 수 없습니다',
                UPLOAD_ERR_EXTENSION => '확장자에 의해 업로드가 중단되었습니다'
            ];
            
            $errorMessage = $uploadErrors[$_FILES['image']['error']] ?? '알 수 없는 업로드 오류';
            http_response_code(400);
            echo json_encode(['message' => $errorMessage]);
            exit;
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON data']);
            exit;
        }
        
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $image = $input['image'] ?? null;
    }

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['message' => '제목을 입력해주세요']);
        exit;
    }

    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['message' => '내용을 입력해주세요']);
        exit;
    }

    if (mb_strlen($title) > 100) {
        http_response_code(400);
        echo json_encode(['message' => '제목은 100자 이하로 입력해주세요']);
        exit;
    }

    if (mb_strlen($content) > 1000) {
        http_response_code(400);
        echo json_encode(['message' => '내용은 1000자 이하로 입력해주세요']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO community_posts (user_id, title, content, image, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ');
    
    $result = $stmt->execute([$userId, $title, $content, $image]);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['message' => '게시글 작성에 실패했습니다']);
        exit;
    }

    $postId = $pdo->lastInsertId();

    $postStmt = $pdo->prepare('
        SELECT 
            p.id,
            p.title,
            p.content,
            p.image,
            p.created_at,
            p.like_count,
            u.user_name as author,
            COALESCE(u.profile_pokemon_id, 25) as authorProfilePokemonId
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ');
    
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'Post created successfully',
        'post' => [
            'id' => (int)$post['id'],
            'title' => $post['title'],
            'content' => $post['content'],
            'author' => $post['author'],
            'authorProfilePokemonId' => (int)$post['authorProfilePokemonId'],
            'createdAt' => $post['created_at'],
            'likeCount' => 0,
            'isLiked' => false,
            'image' => $post['image'] ? '../uploads/community/' . $post['image'] : null
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to create post',
        'error' => $e->getMessage()
    ]);
}

function saveUploadedImage($file, $userId) {
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('이미지 크기는 5MB 이하만 업로드 가능합니다');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('JPG, PNG, GIF, WEBP 형식만 지원합니다');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'community_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;

    $uploadDir = __DIR__ . '/../../uploads/community/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('업로드 디렉토리 생성에 실패했습니다: ' . $uploadDir);
        }
        chmod($uploadDir, 0777);
    }

    if (!is_writable($uploadDir)) {
        if (!chmod($uploadDir, 0777)) {
            throw new Exception('업로드 디렉토리에 쓰기 권한이 없습니다: ' . $uploadDir . ' (권한 변경 실패)');
        }
        
        if (!is_writable($uploadDir)) {
            throw new Exception('업로드 디렉토리에 쓰기 권한이 없습니다: ' . $uploadDir . ' (권한 변경 후에도 실패)');
        }
    }

    $uploadPath = $uploadDir . $filename;

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('업로드된 파일이 유효하지 않습니다');
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $error = error_get_last();
        throw new Exception('파일 이동에 실패했습니다. Error: ' . ($error['message'] ?? 'Unknown error') . ' Path: ' . $uploadPath);
    }

    return $filename;
}