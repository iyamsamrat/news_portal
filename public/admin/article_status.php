<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);

csrf_verify($_POST['csrf_token'] ?? '');

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

$id = (int) ($_POST['id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/articles.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

// Read current
$stmt = $pdo->prepare("SELECT id, status, is_featured, allow_comments, published_at FROM articles WHERE id=:id LIMIT 1");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: ' . $back);
    exit;
}

$status = (string) ($row['status'] ?? 'draft');
$isFeatured = (int) ($row['is_featured'] ?? 0);
$allowComments = (int) ($row['allow_comments'] ?? 1);

$now = date('Y-m-d H:i:s');

if ($action === 'publish') {
    $newStatus = 'published';
    $publishedAt = ($row['published_at'] ?? null) ?: $now;

    $u = $pdo->prepare("UPDATE articles SET status='published', published_at=:pa, updated_at=NOW() WHERE id=:id");
    $u->execute(['pa' => $publishedAt, 'id' => $id]);

} elseif ($action === 'draft') {
    $u = $pdo->prepare("UPDATE articles SET status='draft', published_at=NULL, updated_at=NOW() WHERE id=:id");
    $u->execute(['id' => $id]);

} elseif ($action === 'archive') {
    $u = $pdo->prepare("UPDATE articles SET status='archived', updated_at=NOW() WHERE id=:id");
    $u->execute(['id' => $id]);

} elseif ($action === 'toggle_featured') {
    $new = $isFeatured ? 0 : 1;
    $u = $pdo->prepare("UPDATE articles SET is_featured=:v, updated_at=NOW() WHERE id=:id");
    $u->execute(['v' => $new, 'id' => $id]);

} elseif ($action === 'toggle_comments') {
    $new = $allowComments ? 0 : 1;
    $u = $pdo->prepare("UPDATE articles SET allow_comments=:v, updated_at=NOW() WHERE id=:id");
    $u->execute(['v' => $new, 'id' => $id]);
}

header('Location: ' . $back);
exit;
