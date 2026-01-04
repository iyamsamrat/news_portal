<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/auth.php';

auth_start();
$pdo = db();

/**
 * Helpers
 */
function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/**
 * Inputs
 */
$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$limit = 12;

/**
 * Base SQL (published articles only)
 */
$sql = "
    SELECT
        a.id,
        a.title,
        a.slug,
        a.summary,
        a.cover_image,
        a.published_at,
        c.name AS category_name,
        c.slug AS category_slug
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'published'
";

$params = [];

/**
 * Search filter
 */
if ($q !== '') {
    $sql .= " AND (a.title LIKE :q OR a.content LIKE :q)";
    $params['q'] = '%' . $q . '%';
}

/**
 * Category filter
 */
if ($category !== '') {
    $sql .= " AND c.slug = :category";
    $params['category'] = $category;
}

/**
 * Ordering
 */
$sql .= " ORDER BY a.is_featured DESC, a.published_at DESC LIMIT :limit";

/**
 * Prepare & execute
 */
$stmt = $pdo->prepare($sql);

// Bind params safely
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

$stmt->execute();
$articles = $stmt->fetchAll();

/**
 * Page title
 */
$pageTitle = APP_NAME;
if ($q !== '') {
    $pageTitle = 'Search: ' . $q . ' - ' . APP_NAME;
}
if ($category !== '') {
    $pageTitle = ucfirst($category) . ' - ' . APP_NAME;
}

require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main" class="container my-4">

    <!-- Page header -->
    <div class="np-card p-4 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
                <h1 class="h4 mb-1 np-title">
                    <?= $category ? h(ucfirst($category)) : 'Latest News' ?>
                </h1>
                <div class="small text-muted">
                    <?php if ($q): ?>
                        Showing results for "<strong><?= h($q) ?></strong>"
                    <?php else: ?>
                        Clean, minimal, fast news experience.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Articles grid -->
    <div class="row g-3">
        <?php if (empty($articles)): ?>
            <div class="col-12">
                <div class="np-card p-4 text-center text-muted">
                    No articles found.
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($articles as $a): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="np-card h-100 p-3 d-flex flex-column">

                    <!-- Category + date -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <a class="badge text-bg-light border text-decoration-none"
                            href="<?= h(BASE_URL) ?>/index.php?category=<?= urlencode((string) $a['category_slug']) ?>">
                            <?= h($a['category_name'] ?? 'General') ?>
                        </a>

                        <span class="small text-muted">
                            <?= $a['published_at'] ? h(date('M d, Y', strtotime((string) $a['published_at']))) : '' ?>
                        </span>
                    </div>

                    <!-- Cover image -->
                    <?php if (!empty($a['cover_image'])): ?>
                        <div class="mb-2">
                            <img src="<?= h(UPLOAD_URL) ?>/<?= h($a['cover_image']) ?>" class="img-fluid rounded-3 border"
                                alt="Article image">
                        </div>
                    <?php endif; ?>

                    <!-- Title -->
                    <h2 class="h6 mb-2 flex-grow-0">
                        <?= h($a['title']) ?>
                    </h2>

                    <!-- Summary -->
                    <p class="small text-muted flex-grow-1">
                        <?= h($a['summary'] ?: 'Click to read the full article.') ?>
                    </p>

                    <!-- Read button -->
                    <a class="btn btn-sm btn-outline-dark w-100 mt-2"
                        href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $a['slug']) ?>">
                        Read Article
                    </a>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>