<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
try {
    csrf_verify($token);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file   = $_FILES['image'];
// Validate via getimagesize (always available, no extension required)
$imgInfo = @getimagesize($file['tmp_name']);
$allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
$imgType = $imgInfo ? (int)$imgInfo[2] : 0;

if (!$imgInfo || !isset($allowedTypes[$imgType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image type']);
    exit;
}

$ext = $allowedTypes[$imgType];

$subdir  = 'editor/' . date('Y/m');
$saveDir = rtrim(UPLOAD_PATH, '/') . '/' . $subdir;

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

$filename  = bin2hex(random_bytes(10)) . '.' . $ext;
$savePath  = $saveDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

$url = rtrim(UPLOAD_URL, '/') . '/' . $subdir . '/' . $filename;
echo json_encode(['url' => $url]);
