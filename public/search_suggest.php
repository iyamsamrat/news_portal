<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$q = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $pdo  = db();
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT a.title, a.slug, c.name AS category_name
        FROM articles a
        LEFT JOIN categories c ON c.id = a.category_id
        WHERE a.status = 'published'
          AND (a.title LIKE :q1 OR a.summary LIKE :q2)
        ORDER BY a.published_at DESC
        LIMIT 7
    ");
    $stmt->execute(['q1' => $like, 'q2' => $like]);
    $rows = $stmt->fetchAll();

    $results = array_map(fn($r) => [
        'title'    => (string) $r['title'],
        'category' => (string) ($r['category_name'] ?? ''),
        'url'      => rtrim(BASE_URL, '/') . '/article.php?slug=' . rawurlencode((string) $r['slug']),
    ], $rows);

    echo json_encode(['results' => $results]);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
}
