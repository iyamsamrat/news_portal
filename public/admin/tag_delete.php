<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin']); // delete only admin
csrf_verify($_POST['csrf_token'] ?? '');

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

$id = (int) ($_POST['id'] ?? 0);
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/tags.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

// Remove relations first
try {
    $pdo->prepare("DELETE FROM article_tags WHERE tag_id=:id")->execute(['id' => $id]);
} catch (Throwable $e) {
}

// Delete tag
$pdo->prepare("DELETE FROM tags WHERE id=:id LIMIT 1")->execute(['id' => $id]);

header('Location: ' . $back);
exit;
