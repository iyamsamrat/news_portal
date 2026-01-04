<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/security.php';
require_once __DIR__ . '/../app/models/Rating.php';

auth_require_login();
csrf_verify($_POST['csrf_token'] ?? '');

$user = auth_user();
$userId = (int) $user['id'];

$articleId = (int) ($_POST['article_id'] ?? 0);
$slug = trim((string) ($_POST['slug'] ?? ''));
$rating = (int) ($_POST['rating'] ?? 0);

if ($articleId <= 0 || $slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($rating >= 1 && $rating <= 5) {
    Rating::upsert($userId, $articleId, $rating);
}

// Redirect back to article page
header('Location: ' . BASE_URL . '/article.php?slug=' . urlencode($slug));
exit;
