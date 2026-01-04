<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin']); // only admin can delete

csrf_verify($_POST['csrf_token'] ?? '');

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

$id = (int) ($_POST['id'] ?? 0);
$mode = trim((string) ($_POST['mode'] ?? 'archive')); // archive|hard
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/articles.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

if ($mode === 'hard') {
    // Hard delete (optional / later)
    // Delete tag relations first if exist
    try {
        $pdo->prepare("DELETE FROM article_tags WHERE article_id=:id")->execute(['id' => $id]);
    } catch (Throwable $e) {
    }

    // Delete comments/ratings/bookmarks relations if you want later
    // For now we keep it simple.

    $pdo->prepare("DELETE FROM articles WHERE id=:id LIMIT 1")->execute(['id' => $id]);
} else {
    // Soft delete = archive
    $pdo->prepare("UPDATE articles SET status='archived', updated_at=NOW() WHERE id=:id")->execute(['id' => $id]);
}

header('Location: ' . $back);
exit;
