<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/auth.php';

auth_start();

$pdo = db();

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// Filters
$q = trim((string) ($_GET['q'] ?? ''));
$catSlug = trim((string) ($_GET['category'] ?? ''));
$tagSlug = trim((string) ($_GET['tag'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'latest'));
$page = max(1, (int) ($_GET['page'] ?? 1));

$allowedSort = ['latest', 'popular'];
if (!in_array($sort, $allowedSort, true))
    $sort = 'latest';

$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query parts
$joins = "";
$where = " WHERE a.status = 'published' ";
$params = [];

// Category filter
if ($catSlug !== '') {
    $joins .= " INNER JOIN categories c ON c.id = a.category_id ";
    $where .= " AND c.slug = :cat ";
    $params['cat'] = $catSlug;
}

// Tag filter
if ($tagSlug !== '') {
    $joins .= " INNER JOIN article_tags atg ON atg.article_id = a.id
                INNER JOIN tags t ON t.id = atg.tag_id ";
    $where .= " AND t.slug = :tag ";
    $params['tag'] = $tagSlug;
}

// Search query (use distinct param names — PDO named params can't repeat)
if ($q !== '') {
    $where .= " AND (
        a.title LIKE :q1
        OR a.summary LIKE :q2
        OR a.content LIKE :q3
    ) ";
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

// Popularity join (views)
$viewsJoin = " LEFT JOIN (
    SELECT article_id, COUNT(*) AS view_count
    FROM article_views
    GROUP BY article_id
) v ON v.article_id = a.id ";

// Sorting
$orderBy = " ORDER BY a.published_at DESC, a.id DESC ";
if ($sort === 'popular') {
    $orderBy = " ORDER BY COALESCE(v.view_count, 0) DESC, a.published_at DESC, a.id DESC ";
}

// Count query (DISTINCT to avoid duplicates with tags join)
$countSql = "
    SELECT COUNT(DISTINCT a.id)
    FROM articles a
    $joins
    $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

// Data query
$dataSql = "
    SELECT DISTINCT
        a.id, a.title, a.slug, a.summary, a.cover_image, a.published_at,
        a.category_id,
        c2.name AS category_name, c2.slug AS category_slug,
        COALESCE(v.view_count, 0) AS view_count
    FROM articles a
    $joins
    LEFT JOIN categories c2 ON c2.id = a.category_id
    $viewsJoin
    $where
    $orderBy
    LIMIT :limit OFFSET :offset
";

$dataStmt = $pdo->prepare($dataSql);

// bind normal params
foreach ($params as $k => $v0) {
    $dataStmt->bindValue(':' . $k, $v0);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$dataStmt->execute();
$articles = $dataStmt->fetchAll() ?: [];

// Headline
$filtersTitleParts = [];
if ($q !== '')
    $filtersTitleParts[] = 'Search: "' . $q . '"';
if ($catSlug !== '')
    $filtersTitleParts[] = 'Category: ' . $catSlug;
if ($tagSlug !== '')
    $filtersTitleParts[] = 'Tag: ' . $tagSlug;
if (!$filtersTitleParts)
    $filtersTitleParts[] = 'Browse';

$pageTitle = implode(' • ', $filtersTitleParts) . ' - ' . APP_NAME;

require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';

function build_query_url(array $overrides = []): string
{
    $base = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '')
            unset($base[$k]);
        else
            $base[$k] = $v;
    }
    return BASE_URL . '/search.php?' . http_build_query($base);
}
?>

<main id="main" class="container my-4" style="max-width: 1100px;">

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><?= h($filtersTitleParts[0] ?? 'Browse') ?></h1>
            <div class="small text-muted">
                <?= (int) $total ?> result(s)
                <?php if ($sort === 'popular'): ?> • sorted by popularity<?php endif; ?>
                <?php if ($sort === 'latest'): ?> • sorted by latest<?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm <?= $sort === 'latest' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h(build_query_url(['sort' => 'latest', 'page' => 1])) ?>">Latest</a>

            <a class="btn btn-sm <?= $sort === 'popular' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h(build_query_url(['sort' => 'popular', 'page' => 1])) ?>">Popular</a>

            <?php if ($q !== '' || $catSlug !== '' || $tagSlug !== ''): ?>
                <a class="btn btn-sm btn-outline-dark" href="<?= h(BASE_URL) ?>/search.php">Clear</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($q !== ''): ?><span class="badge text-bg-light border">q: <?= h($q) ?></span><?php endif; ?>
        <?php if ($catSlug !== ''): ?><span class="badge text-bg-light border">category:
                <?= h($catSlug) ?></span><?php endif; ?>
        <?php if ($tagSlug !== ''): ?><span class="badge text-bg-light border">tag:
                #<?= h($tagSlug) ?></span><?php endif; ?>
    </div>

    <?php if (empty($articles)): ?>
        <div class="np-card p-4">
            <h2 class="h6 mb-2">No results</h2>
            <div class="text-muted">Try different keywords, remove filters, or browse Latest.</div>
        </div>
    <?php else: ?>

        <div class="row g-3">
            <?php foreach ($articles as $a): ?>
                <?php
                $url      = BASE_URL . '/article.php?slug=' . urlencode((string) $a['slug']);
                $catName  = (string) ($a['category_name'] ?? 'General');
                $catSlug2 = (string) ($a['category_slug'] ?? '');
                $catUrl   = $catSlug2 !== '' ? (BASE_URL . '/search.php?category=' . urlencode($catSlug2)) : '';
                $published = !empty($a['published_at']) ? date('M j, Y', strtotime((string) $a['published_at'])) : '';
                $views    = (int) ($a['view_count'] ?? 0);
                $imgPath  = !empty($a['cover_image']) ? h(UPLOAD_URL) . '/' . h((string) $a['cover_image']) : null;
                ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="np-article-card">
                        <?php if ($imgPath): ?>
                            <a href="<?= h($url) ?>">
                                <img class="np-article-card-img" src="<?= $imgPath ?>"
                                     alt="<?= h($a['title']) ?>">
                            </a>
                        <?php endif; ?>
                        <div class="np-article-card-body">
                            <?php if ($catUrl !== ''): ?>
                                <a class="np-article-card-cat" href="<?= h($catUrl) ?>"><?= h($catName) ?></a>
                            <?php else: ?>
                                <span class="np-article-card-cat"><?= h($catName) ?></span>
                            <?php endif; ?>
                            <a class="np-article-card-title" href="<?= h($url) ?>">
                                <?= h((string) $a['title']) ?>
                            </a>
                            <?php if (!empty($a['summary'])): ?>
                                <div class="np-article-card-summary">
                                    <?= h((string) $a['summary']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="np-article-card-meta">
                                <i class="bi bi-clock"></i>
                                <span><?= h($published) ?></span>
                                <?php if ($sort === 'popular' && $views > 0): ?>
                                    <span class="ms-auto"><i class="bi bi-eye me-1"></i><?= $views ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Search pagination">
                <ul class="pagination pagination-sm flex-wrap">
                    <?php $prev = max(1, $page - 1);
                    $next = min($totalPages, $page + 1); ?>

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(build_query_url(['page' => $prev])) ?>">Prev</a>
                    </li>

                    <?php
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . h(build_query_url(['page' => 1])) . '">1</a></li>';
                        if ($start > 2)
                            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($p = $start; $p <= $end; $p++) {
                        $active = $p === $page ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . h(build_query_url(['page' => $p])) . '">' . $p . '</a></li>';
                    }
                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1)
                            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="' . h(build_query_url(['page' => $totalPages])) . '">' . $totalPages . '</a></li>';
                    }
                    ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(build_query_url(['page' => $next])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>