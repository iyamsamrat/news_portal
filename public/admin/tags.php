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

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t
        ");
        $st->execute(['t' => $table]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function admin_pagination(int $page, int $total, int $perPage, array $filters): string
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($totalPages <= 1) return '';

    $url = function (int $p) use ($filters): string {
        $q = array_filter(array_merge($filters, ['page' => $p > 1 ? $p : null]),
            fn($v) => $v !== null && $v !== '');
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

$hasTags = table_exists($pdo, 'tags') && table_exists($pdo, 'article_tags');
$user    = auth_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');

$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;

// Edit mode
$editId = (int) ($_GET['edit'] ?? 0);
$edit   = null;
if ($hasTags && $editId > 0) {
    $st = $pdo->prepare("SELECT * FROM tags WHERE id=:id LIMIT 1");
    $st->execute(['id' => $editId]);
    $edit = $st->fetch() ?: null;
}

$rows       = [];
$total      = 0;
$totalPages = 1;

if ($hasTags) {
    // Build WHERE
    $where      = '';
    $params     = [];
    if ($q !== '') {
        $where      = "WHERE t.name LIKE :q1 OR t.slug LIKE :q2";
        $params['q1'] = '%' . $q . '%';
        $params['q2'] = '%' . $q . '%';
    }

    // Count
    try {
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tags t $where");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();
    } catch (Throwable $e) {
        $total = 0;
    }

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Fetch page (try with created_at, fall back without)
    $fetchPage = function (bool $withCreatedAt) use ($pdo, $where, $params, $perPage, $offset): array {
        $dateCol = $withCreatedAt ? 't.created_at' : 'NULL AS created_at';
        $sql     = "
            SELECT t.id, t.name, t.slug, $dateCol,
                   COALESCE(x.use_count, 0) AS use_count
            FROM tags t
            LEFT JOIN (
                SELECT tag_id, COUNT(*) AS use_count FROM article_tags GROUP BY tag_id
            ) x ON x.tag_id = t.id
            $where
            ORDER BY x.use_count DESC, t.name ASC
            LIMIT :limit OFFSET :offset
        ";
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue(':' . $k, $v);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    };

    try {
        $rows = $fetchPage(true);
    } catch (Throwable $e) {
        try {
            $rows = $fetchPage(false);
        } catch (Throwable $e2) {
            $rows = [];
        }
    }
}

$pageTitle = "Tags - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Tags</h1>
            <div class="small text-muted">Organize articles for search, filters, and recommendations.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/tags.php">
                <i class="bi bi-plus-lg"></i> New Tag
            </a>
        </div>
    </div>

    <?php if (!$hasTags): ?>
        <div class="np-card p-4 bg-white" style="max-width:900px;">
            <h2 class="h6 mb-2">Tags tables not found</h2>
            <div class="text-muted">Your database is missing <code>tags</code> and/or <code>article_tags</code>.</div>
        </div>
    <?php else: ?>

        <div class="row g-3">

            <!-- Form -->
            <div class="col-12 col-lg-4">
                <div class="np-card p-3 p-md-4 bg-white">
                    <div class="fw-semibold mb-1"><?= $edit ? 'Edit Tag' : 'Add Tag' ?></div>
                    <div class="small text-muted mb-3">Short and consistent (e.g., "Nepal", "Elections").</div>

                    <form method="post" action="<?= h($ADMIN_URL) ?>/tag_save.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="name" required
                                value="<?= h((string) ($edit['name'] ?? '')) ?>" placeholder="e.g., Technology">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input class="form-control" name="slug"
                                value="<?= h((string) ($edit['slug'] ?? '')) ?>"
                                placeholder="auto-from-name">
                            <div class="form-text">Leave blank to auto-generate.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-dark" type="submit">
                                <i class="bi bi-check2"></i> <?= $edit ? 'Update' : 'Create' ?>
                            </button>
                            <?php if ($edit): ?>
                                <a class="btn btn-outline-dark" href="<?= h($ADMIN_URL) ?>/tags.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="col-12 col-lg-8">
                <div class="np-card p-3 bg-white mb-2">
                    <form method="get" class="d-flex gap-2 align-items-center">
                        <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                            placeholder="Search tags...">
                        <button class="btn btn-sm btn-outline-dark" type="submit">Search</button>
                        <?php if ($q !== ''): ?>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= h($ADMIN_URL) ?>/tags.php">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="np-card p-0 bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="border-bottom">
                                <tr class="small text-muted">
                                    <th style="width:60px;">#</th>
                                    <th>Name</th>
                                    <th style="width:220px;">Slug</th>
                                    <th style="width:100px;">Used</th>
                                    <th style="width:150px;">Created</th>
                                    <th style="width:180px;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="6" class="p-4 text-muted">No tags found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $i => $r): ?>
                                        <?php
                                        $tid = (int) $r['id'];
                                        $num = ($page - 1) * $perPage + $i + 1;
                                        ?>
                                        <tr>
                                            <td class="text-muted"><?= $num ?></td>
                                            <td class="fw-semibold"><?= h($r['name']) ?></td>
                                            <td class="text-muted"><?= h($r['slug']) ?></td>
                                            <td>
                                                <span class="badge text-bg-light border">
                                                    <?= (int) $r['use_count'] ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?= $r['created_at'] ? h(date('M d, Y', strtotime((string) $r['created_at']))) : '—' ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1 justify-content-end flex-wrap">
                                                    <a class="btn btn-sm btn-outline-dark"
                                                        href="<?= h($ADMIN_URL) ?>/tags.php?edit=<?= $tid ?><?= $q ? '&q=' . urlencode($q) : '' ?>&page=<?= $page ?>">
                                                        Edit
                                                    </a>
                                                    <?php if ($isAdmin): ?>
                                                        <form method="post"
                                                            action="<?= h($ADMIN_URL) ?>/tag_delete.php"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Delete this tag? It will be removed from all articles.');">
                                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                            <input type="hidden" name="id" value="<?= $tid ?>">
                                                            <input type="hidden" name="back"
                                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/tags.php')) ?>">
                                                            <button class="btn btn-sm btn-outline-danger"
                                                                type="submit">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?= admin_pagination($page, $total, $perPage, ['q' => $q]) ?>

                <div class="small text-muted mt-2">
                    Tags are attached to articles via the Tags field in the article editor.
                </div>
            </div>

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>
