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
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/categories.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

// Toggle
$stmt = $pdo->prepare("SELECT is_active FROM categories WHERE id=:id LIMIT 1");
$stmt->execute(['id' => $id]);
$cur = $stmt->fetchColumn();

if ($cur === false) {
    header('Location: ' . $back);
    exit;
}

$new = ((int) $cur === 1) ? 0 : 1;

$u = $pdo->prepare("UPDATE categories SET is_active=:v WHERE id=:id LIMIT 1");
$u->execute(['v' => $new, 'id' => $id]);

header('Location: ' . $back);
exit;
