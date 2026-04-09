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

function countSafe(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
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

// Filters
$status  = trim((string) ($_GET['status'] ?? 'pending'));
$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$allowed = ['pending', 'approved', 'hidden'];
if (!in_array($status, $allowed, true))
    $status = 'pending';

// Tab counts (global, not filtered by search)
$cntPending  = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='pending'");
$cntApproved = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='approved'");
$cntHidden   = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='hidden'");

// Build shared WHERE
$joins  = "FROM comments c INNER JOIN users u ON u.id = c.user_id INNER JOIN articles a ON a.id = c.article_id";
$where  = "WHERE c.status = :status";
$params = ['status' => $status];

if ($q !== '') {
    $where .= " AND (
        c.comment LIKE :q1 OR u.name LIKE :q2 OR u.email LIKE :q3
        OR a.title LIKE :q4 OR a.slug LIKE :q5
    )";
    $qv = '%' . $q . '%';
    $params['q1'] = $qv;
    $params['q2'] = $qv;
    $params['q3'] = $qv;
    $params['q4'] = $qv;
    $params['q5'] = $qv;
}

// Count for current tab+search
$total      = countSafe($pdo, "SELECT COUNT(*) $joins $where", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch page
$dataStmt = $pdo->prepare("
    SELECT c.id, c.comment, c.status, c.created_at,
           c.sentiment_score, c.sentiment_label,
           u.id AS user_id, u.name AS user_name, u.email AS user_email,
           a.id AS article_id, a.title AS article_title, a.slug AS article_slug
    $joins $where
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $dataStmt->bindValue(':' . $k, $v);
}
$dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll() ?: [];

$pageTitle = "Comments - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';

$user    = auth_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Comment Moderation</h1>
            <div class="small text-muted">
                <?= number_format($total) ?> comment<?= $total !== 1 ? 's' : '' ?>
                in <strong><?= h($status) ?></strong>
                <?= $q ? '· filtered' : '' ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">
                <i class="bi bi-inbox"></i> Pending (<?= (int) $cntPending ?>)
            </a>
        </div>
    </div>

    <!-- Status tabs + search -->
    <div class="np-card p-2 bg-white mb-3">
        <div class="admin-filter-bar">
            <a class="btn btn-sm <?= $status === 'pending'  ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">Pending (<?= (int) $cntPending ?>)</a>
            <a class="btn btn-sm <?= $status === 'approved' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=approved">Approved (<?= (int) $cntApproved ?>)</a>
            <a class="btn btn-sm <?= $status === 'hidden'   ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=hidden">Hidden (<?= (int) $cntHidden ?>)</a>

            <form method="get" class="admin-filter-search d-flex gap-2">
                <input type="hidden" name="status" value="<?= h($status) ?>">
                <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                    placeholder="Search comment/user/article...">
                <button class="btn btn-sm btn-outline-dark" type="submit">Search</button>
                <?php if ($q !== ''): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?= h($ADMIN_URL) ?>/comments.php?status=<?= urlencode($status) ?>">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="np-card p-0 bg-white overflow-hidden">
        <?php if (empty($rows)): ?>
            <div class="p-4 text-muted">No comments found for this filter.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $cid     = (int) $r['id'];
                    $cStatus = (string) $r['status'];
                    $badge   = match ($cStatus) {
                        'pending'  => 'text-bg-warning',
                        'approved' => 'text-bg-success',
                        'hidden'   => 'text-bg-secondary',
                        default    => 'text-bg-light border',
                    };

                    $sentLabel = (string) ($r['sentiment_label'] ?? '');
                    $sentScore = $r['sentiment_score'] !== null ? (float) $r['sentiment_score'] : null;
                    $sentBadge = '';
                    $sentIcon  = '';
                    if ($sentLabel === 'positive') {
                        $sentBadge = 'text-bg-success';
                        $sentIcon  = 'bi-emoji-smile';
                    } elseif ($sentLabel === 'negative') {
                        $sentBadge = 'text-bg-danger';
                        $sentIcon  = 'bi-emoji-frown';
                    } elseif ($sentLabel === 'neutral') {
                        $sentBadge = 'text-bg-secondary';
                        $sentIcon  = 'bi-emoji-neutral';
                    }

                    $back = $_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/comments.php?status=' . urlencode($status));
                    ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-2">

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                    <span class="badge <?= h($badge) ?>"><?= h(ucfirst($cStatus)) ?></span>
                                    <?php if ($sentBadge !== ''): ?>
                                        <span class="badge <?= h($sentBadge) ?>"
                                            title="Score: <?= $sentScore !== null ? number_format($sentScore, 3) : 'N/A' ?>">
                                            <i class="bi <?= h($sentIcon) ?>"></i> <?= h(ucfirst($sentLabel)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="small text-muted">
                                        <?= h(date('M d, Y H:i', strtotime((string) $r['created_at']))) ?>
                                    </span>
                                    <span class="small text-muted">·</span>
                                    <span class="small">
                                        By <strong><?= h($r['user_name']) ?></strong>
                                        <span class="text-muted">(<?= h($r['user_email']) ?>)</span>
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <span class="fw-semibold small">On: </span>
                                    <a class="text-decoration-none small" target="_blank" rel="noopener"
                                        href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $r['article_slug']) ?>">
                                        <?= h($r['article_title']) ?>
                                    </a>
                                </div>

                                <div class="p-3 bg-light border rounded-3 small">
                                    <?= nl2br(h((string) $r['comment'])) ?>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-end flex-shrink-0">
                                <?php if ($cStatus !== 'approved'): ?>
                                    <form method="post" action="<?= h($ADMIN_URL) ?>/comment_action.php">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= $cid ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="back" value="<?= h($back) ?>">
                                        <button class="btn btn-sm btn-dark" type="submit">
                                            <i class="bi bi-check2"></i> Approve
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($cStatus !== 'hidden'): ?>
                                    <form method="post" action="<?= h($ADMIN_URL) ?>/comment_action.php">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= $cid ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="back" value="<?= h($back) ?>">
                                        <button class="btn btn-sm btn-outline-dark" type="submit">
                                            <i class="bi bi-slash-circle"></i> Hide
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= h($ADMIN_URL) ?>/comment_action.php"
                                        onsubmit="return confirm('Delete this comment permanently?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= $cid ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="back" value="<?= h($back) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?= admin_pagination($page, $total, $perPage, [
        'status' => $status,
        'q'      => $q,
    ]) ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>
