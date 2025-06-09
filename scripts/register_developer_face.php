<?php
/**
 * 개발자 얼굴 등록 스크립트
 * 사용법: php register_developer_face.php [이미지_경로]
 */

if ($argc !== 2) {
    echo "사용법: php register_developer_face.php [이미지_경로]\n";
    exit(1);
}

$imagePath = $argv[1];

if (!file_exists($imagePath)) {
    echo "이미지 파일을 찾을 수 없습니다: $imagePath\n";
    exit(1);
}

$projectRoot = __DIR__ . '/..';
$pythonPath = $projectRoot . '/pokeface_env/bin/python3';

if (!file_exists($pythonPath)) {
    $pythonPath = 'python3';
    echo "가상환경을 찾을 수 없어 시스템 Python을 사용합니다.\n";
}

$scriptPath = __DIR__ . '/face_embedding.py';
$cmd = escapeshellcmd("$pythonPath $scriptPath " . escapeshellarg($imagePath));
$output = shell_exec($cmd);
$result = json_decode($output, true);

if (!isset($result['embedding'])) {
    echo "❌ 얼굴 인식 실패: " . ($result['error'] ?? '알 수 없는 에러') . "\n";
    exit(1);
}

$embedding = $result['embedding'];

$envFile = __DIR__ . '/../.env';
$envContent = file_get_contents($envFile);

$envContent = preg_replace('/^DEVELOPER_FACE_EMBEDDING=.*$/m', '', $envContent);
$envContent = trim($envContent);

$embeddingJson = json_encode($embedding);
$envContent .= "\nDEVELOPER_FACE_EMBEDDING=" . $embeddingJson . "\n";

if (file_put_contents($envFile, $envContent)) {
    echo "✅ 개발자 얼굴이 성공적으로 등록되었습니다!\n";
    echo "이제 해당 얼굴 사진을 업로드하면 뮤츠를 획득할 수 있습니다.\n";
} else {
    echo "❌ .env 파일 쓰기 실패\n";
    exit(1);
}
?>