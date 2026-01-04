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
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/comments.php?status=pending')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

$user = auth_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE comments SET status='approved', updated_at=NOW() WHERE id=:id");
    $stmt->execute(['id' => $id]);

} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE comments SET status='rejected', updated_at=NOW() WHERE id=:id");
    $stmt->execute(['id' => $id]);

} elseif ($action === 'delete') {
    if (!$isAdmin) {
        http_response_code(403);
        echo "Forbidden: only admin can delete comments.";
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id=:id LIMIT 1");
    $stmt->execute(['id' => $id]);
}

header('Location: ' . $back);
exit;
