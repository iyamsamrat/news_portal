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
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/categories.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

// Check usage
$chk = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE category_id=:id");
$chk->execute(['id' => $id]);
$used = (int) $chk->fetchColumn();

if ($used > 0) {
    // If used, do NOT delete. Instead mark inactive (safe CMS behavior)
    $pdo->prepare("UPDATE categories SET is_active=0 WHERE id=:id LIMIT 1")->execute(['id' => $id]);
    header('Location: ' . $back);
    exit;
}

// Safe delete
$pdo->prepare("DELETE FROM categories WHERE id=:id LIMIT 1")->execute(['id' => $id]);

header('Location: ' . $back);
exit;
