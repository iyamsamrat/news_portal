<?php
declare(strict_types=1);

/**
 * Admin: Re-run Sentiment Analysis on all articles and comments.
 * POST-only, CSRF-protected, admin/editor access.
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';
require_once __DIR__ . '/../../app/models/Sentiment.php';

auth_start();
auth_require_role(['admin', 'editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php?sentiment_error=csrf');
    exit;
}

$pdo = db();
$aCount = 0;
$cCount = 0;

// Re-score all articles
$stmt = $pdo->query("SELECT id, title, summary, content FROM articles");
foreach ($stmt->fetchAll() as $row) {
    $text = implode(' ', [
        (string) ($row['title']   ?? ''),
        (string) ($row['summary'] ?? ''),
        mb_substr((string) ($row['content'] ?? ''), 0, 1000),
    ]);
    Sentiment::analyseAndStoreArticle((int) $row['id'], $text);
    $aCount++;
}

// Re-score all comments
$stmt2 = $pdo->query("SELECT id, comment FROM comments");
foreach ($stmt2->fetchAll() as $row) {
    Sentiment::analyseAndStoreComment((int) $row['id'], (string) $row['comment']);
    $cCount++;
}

header('Location: ' . BASE_URL . '/admin/dashboard.php?sentiment_done=1&a=' . $aCount . '&c=' . $cCount);
exit;
