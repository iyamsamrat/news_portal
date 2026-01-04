<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/security.php';
require_once __DIR__ . '/../app/models/Comment.php';

auth_require_login();
csrf_verify($_POST['csrf_token'] ?? '');

$user = auth_user();
$userId = (int) $user['id'];

$articleId = (int) ($_POST['article_id'] ?? 0);
$slug = trim((string) ($_POST['slug'] ?? ''));
$comment = (string) ($_POST['comment'] ?? '');

if ($articleId <= 0 || $slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

Comment::create($articleId, $userId, $comment);

// back to article
header('Location: ' . BASE_URL . '/article.php?slug=' . urlencode($slug) . '#comments');
exit;
