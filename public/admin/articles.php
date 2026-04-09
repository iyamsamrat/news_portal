<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function admin_pagination(int $page, int $total, int $perPage, array $filters): string
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($totalPages <= 1) return '';

    $url = function (int $p) use ($filters): string {
        $q = array_filter(array_merge($filters, ['page' => $p > 1 ? $p : null]),
            fn($v) => $v !== null && $v !== '' && $v !== 0);
        return '?' . http_build_query($q);
    };

    $h  = '<nav class="mt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">';
    $h .= '<div class="small text-muted">Page ' . $page . ' of ' . $totalPages
        . ' &nbsp;·&nbsp; ' . number_format($total) . ' total</div>';
    $h .= '<ul class="pagination pagination-sm mb-0">';

    $h .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">'
        . '<a class="page-link" href="' . ($page > 1 ? h($url($page - 1)) : '#') . '">‹ Prev</a></li>';

    $s = max(1, $page - 2);
    $e = min($totalPages, $page + 2);
    if ($s > 1) {
        $h .= '<li class="page-item"><a class="page-link" href="' . h($url(1)) . '">1</a></li>';
        if ($s > 2) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($p = $s; $p <= $e; $p++) {
        $h .= '<li class="page-item ' . ($p === $page ? 'active' : '') . '">'
            . '<a class="page-link" href="' . h($url($p)) . '">' . $p . '</a></li>';
    }
    if ($e < $totalPages) {
        if ($e < $totalPages - 1) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $h .= '<li class="page-item"><a class="page-link" href="' . h($url($totalPages)) . '">' . $totalPages . '</a></li>';
    }

    $h .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">'
        . '<a class="page-link" href="' . ($page < $totalPages ? h($url($page + 1)) : '#') . '">Next ›</a></li>';

    $h .= '</ul></nav>';
    return $h;
}

// Filters
$q        = trim((string) ($_GET['q'] ?? ''));
$status   = trim((string) ($_GET['status'] ?? ''));
$category = (int) ($_GET['category_id'] ?? 0);
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 20;

// Fetch categories for filter dropdown
$categories = [];
try {
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC")->fetchAll() ?: [];
} catch (Throwable $e) {
    $categories = [];
}

// Build shared WHERE clause
$joins  = "FROM articles a LEFT JOIN categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.created_by";
$where  = "WHERE 1=1";
$params = [];

if ($q !== '') {
    $where .= " AND (a.title LIKE :q1 OR a.slug LIKE :q2 OR u.name LIKE :q3)";
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}
if (in_array($status, ['draft', 'published', 'archived'], true)) {
    $where .= " AND a.status = :status";
    $params['status'] = $status;
}
if ($category > 0) {
    $where .= " AND a.category_id = :cat";
    $params['cat'] = $category;
}

// Count total
$cntStmt = $pdo->prepare("SELECT COUNT(*) $joins $where");
$cntStmt->execute($params);
$total      = (int) $cntStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch page
$dataStmt = $pdo->prepare("
    SELECT a.id, a.title, a.slug, a.status, a.is_featured, a.published_at, a.created_at,
           c.name AS category_name, u.name AS author_name
    $joins $where
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $dataStmt->bindValue(':' . $k, $v);
}
$dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll() ?: [];

$pageTitle = "Articles - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Articles</h1>
            <div class="small text-muted">
                <?= number_format($total) ?> article<?= $total !== 1 ? 's' : '' ?> total
                <?= ($q || $status || $category) ? '· filtered' : '' ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/article_form.php">
                <i class="bi bi-plus-lg"></i> New Article
            </a>
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="np-card p-3 bg-white mb-3">
        <form class="row g-2 align-items-end" method="get" action="">
            <div class="col-12 col-lg-5">
                <label class="form-label small text-muted">Search</label>
                <input class="form-control" type="text" name="q" value="<?= h($q) ?>"
                    placeholder="Search title, slug, or author...">
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label small text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived"  <?= $status === 'archived'  ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label small text-muted">Category</label>
                <select class="form-select" name="category_id">
                    <option value="0">All</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ($category === (int) $c['id']) ? 'selected' : '' ?>>
                            <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-1 d-grid">
                <button class="btn btn-dark" type="submit">Go</button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="np-card p-0 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="border-bottom">
                    <tr class="small text-muted">
                        <th style="width:40px;">#</th>
                        <th>Title</th>
                        <th style="width:160px;">Status</th>
                        <th style="width:180px;">Category</th>
                        <th style="width:160px;">Author</th>
                        <th style="width:170px;">Created</th>
                        <th style="width:210px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-muted">No articles found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $r): ?>
                            <?php
                            $id  = (int) $r['id'];
                            $st  = (string) $r['status'];
                            $num = $offset + $i + 1;
                            $badge = match ($st) {
                                'published' => 'text-bg-success',
                                'draft'     => 'text-bg-warning',
                                'archived'  => 'text-bg-secondary',
                                default     => 'text-bg-light border',
                            };
                            ?>
                            <tr>
                                <td class="text-muted"><?= $num ?></td>

                                <td>
                                    <div class="fw-semibold"><?= h($r['title']) ?></div>
                                    <div class="small text-muted">
                                        <?= h($r['slug']) ?><?= ((int) $r['is_featured'] === 1) ? ' · Featured' : '' ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge <?= h($badge) ?>"><?= h(ucfirst($st)) ?></span>
                                    <?php if (!empty($r['published_at'])): ?>
                                        <div class="small text-muted mt-1">
                                            <?= h(date('M d, Y H:i', strtotime((string) $r['published_at']))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td><?= h($r['category_name'] ?? '-') ?></td>
                                <td><?= h($r['author_name'] ?? '-') ?></td>
                                <td class="text-muted small">
                                    <?= h(date('M d, Y H:i', strtotime((string) $r['created_at']))) ?>
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                                        <a class="btn btn-sm btn-outline-dark"
                                            href="<?= h($ADMIN_URL) ?>/article_form.php?id=<?= $id ?>">Edit</a>

                                        <a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener"
                                            href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $r['slug']) ?>">View</a>

                                        <form method="post" action="<?= h($ADMIN_URL) ?>/article_status.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/articles.php')) ?>">
                                            <?php if ($st === 'published'): ?>
                                                <button class="btn btn-sm btn-outline-dark" name="action" value="draft"
                                                    type="submit">Unpublish</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-dark" name="action" value="publish"
                                                    type="submit">Publish</button>
                                            <?php endif; ?>
                                        </form>

                                        <form method="post" action="<?= h($ADMIN_URL) ?>/article_status.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/articles.php')) ?>">
                                            <button class="btn btn-sm btn-outline-dark" name="action" value="toggle_featured"
                                                type="submit">
                                                <?= ((int) $r['is_featured'] === 1) ? 'Unfeature' : 'Feature' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?= h($ADMIN_URL) ?>/article_status.php" class="d-inline"
                                            onsubmit="return confirm('Archive this article?');">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/articles.php')) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= admin_pagination($page, $total, $perPage, [
        'q'           => $q,
        'status'      => $status,
        'category_id' => $category ?: null,
    ]) ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>
