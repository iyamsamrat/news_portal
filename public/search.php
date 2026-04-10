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
$q        = trim((string) ($_GET['q']         ?? ''));
$catSlug  = trim((string) ($_GET['category']  ?? ''));
$tagSlug  = trim((string) ($_GET['tag']       ?? ''));
$sort     = trim((string) ($_GET['sort']      ?? 'latest'));
$page     = max(1, (int)  ($_GET['page']      ?? 1));

// Date filters – accept YYYY-MM-DD only
$dateFrom = '';
$dateTo   = '';
if (!empty($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']))
    $dateFrom = $_GET['date_from'];
if (!empty($_GET['date_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']))
    $dateTo   = $_GET['date_to'];
// Swap if reversed
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo)
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

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

// Date range filter
if ($dateFrom !== '') {
    $where .= " AND DATE(a.published_at) >= :date_from ";
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(a.published_at) <= :date_to ";
    $params['date_to'] = $dateTo;
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
if ($dateFrom !== '' || $dateTo !== '') {
    $dLabel = ($dateFrom !== '' ? $dateFrom : '…') . ' → ' . ($dateTo !== '' ? $dateTo : '…');
    $filtersTitleParts[] = 'Date: ' . $dLabel;
}
if (!$filtersTitleParts)
    $filtersTitleParts[] = 'Browse';

$hasDateFilter = $dateFrom !== '' || $dateTo !== '';

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

    <!-- Page header -->
    <div class="np-search-header mb-3">
        <div class="np-search-header-inner">
            <!-- Icon + title -->
            <div class="np-search-title-row">
                <?php
                $icon = 'bi-search';
                if ($catSlug !== '')  $icon = 'bi-folder2-open';
                elseif ($tagSlug !== '') $icon = 'bi-hash';
                elseif ($q !== '')    $icon = 'bi-search';
                else                  $icon = 'bi-newspaper';
                ?>
                <span class="np-search-icon"><i class="bi <?= $icon ?>"></i></span>
                <div>
                    <h1 class="np-search-h1"><?= h($filtersTitleParts[0] ?? 'Browse') ?></h1>
                    <div class="np-search-meta">
                        <span><?= (int) $total ?> result<?= $total !== 1 ? 's' : '' ?></span>
                        <span class="np-search-meta-sep">·</span>
                        <span><?= $sort === 'popular' ? 'by popularity' : 'latest first' ?></span>
                    </div>
                </div>
            </div>

            <!-- Sort + clear -->
            <div class="np-search-actions">
                <div class="np-sort-pills">
                    <a class="np-sort-pill <?= $sort === 'latest'  ? 'active' : '' ?>"
                       href="<?= h(build_query_url(['sort' => 'latest',  'page' => 1])) ?>">
                        <i class="bi bi-clock me-1"></i>Latest
                    </a>
                    <a class="np-sort-pill <?= $sort === 'popular' ? 'active' : '' ?>"
                       href="<?= h(build_query_url(['sort' => 'popular', 'page' => 1])) ?>">
                        <i class="bi bi-fire me-1"></i>Popular
                    </a>
                </div>
                <?php if ($q !== '' || $catSlug !== '' || $tagSlug !== '' || $hasDateFilter): ?>
                    <a class="np-clear-all" href="<?= h(BASE_URL) ?>/search.php">
                        <i class="bi bi-x-circle me-1"></i>Clear all
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active filter chips -->
        <?php $hasChips = $q !== '' || $catSlug !== '' || $tagSlug !== '' || $hasDateFilter; ?>
        <?php if ($hasChips): ?>
        <div class="np-filter-chips">
            <?php if ($q !== ''): ?>
                <a class="np-chip" href="<?= h(build_query_url(['q' => null, 'page' => 1])) ?>">
                    <i class="bi bi-search"></i> <?= h($q) ?> <i class="bi bi-x np-chip-x"></i>
                </a>
            <?php endif; ?>
            <?php if ($catSlug !== ''): ?>
                <a class="np-chip" href="<?= h(build_query_url(['category' => null, 'page' => 1])) ?>">
                    <i class="bi bi-folder2-open"></i> <?= h($catSlug) ?> <i class="bi bi-x np-chip-x"></i>
                </a>
            <?php endif; ?>
            <?php if ($tagSlug !== ''): ?>
                <a class="np-chip" href="<?= h(build_query_url(['tag' => null, 'page' => 1])) ?>">
                    <i class="bi bi-hash"></i><?= h($tagSlug) ?> <i class="bi bi-x np-chip-x"></i>
                </a>
            <?php endif; ?>
            <?php if ($hasDateFilter): ?>
                <a class="np-chip" href="<?= h(build_query_url(['date_from' => null, 'date_to' => null, 'page' => 1])) ?>">
                    <i class="bi bi-calendar3"></i>
                    <?= $dateFrom !== '' ? h($dateFrom) : '…' ?> → <?= $dateTo !== '' ? h($dateTo) : '…' ?>
                    <i class="bi bi-x np-chip-x"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Date range filter -->
        <form method="get" action="<?= h(BASE_URL) ?>/search.php" class="np-date-form">
            <?php foreach (['q' => $q, 'category' => $catSlug, 'tag' => $tagSlug, 'sort' => $sort] as $_k => $_v):
                if ($_v !== ''): ?>
                    <input type="hidden" name="<?= $_k ?>" value="<?= h($_v) ?>">
            <?php endif; endforeach; ?>

            <span class="np-date-label"><i class="bi bi-calendar3"></i> Date range</span>
            <input type="date" name="date_from" class="np-date-input"
                   value="<?= h($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
            <span class="np-date-sep">→</span>
            <input type="date" name="date_to" class="np-date-input"
                   value="<?= h($dateTo) ?>" max="<?= date('Y-m-d') ?>">
            <button type="submit" class="np-date-apply">Apply</button>
        </form>
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