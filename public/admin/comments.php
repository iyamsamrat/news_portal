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

$status = trim((string) ($_GET['status'] ?? 'pending'));
$q = trim((string) ($_GET['q'] ?? ''));

$allowed = ['pending', 'approved', 'rejected'];
if (!in_array($status, $allowed, true))
    $status = 'pending';

// Counts (nice for moderation)
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
$cntPending = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='pending'");
$cntApproved = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='approved'");
$cntRejected = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='rejected'");

// Fetch rows
$sql = "
    SELECT c.id, c.comment, c.status, c.created_at,
           u.id AS user_id, u.name AS user_name, u.email AS user_email,
           a.id AS article_id, a.title AS article_title, a.slug AS article_slug
    FROM comments c
    INNER JOIN users u ON u.id = c.user_id
    INNER JOIN articles a ON a.id = c.article_id
    WHERE c.status = :status
";
$params = ['status' => $status];

if ($q !== '') {
    $sql .= " AND (
        c.comment LIKE :q
        OR u.name LIKE :q
        OR u.email LIKE :q
        OR a.title LIKE :q
        OR a.slug LIKE :q
    )";
    $params['q'] = '%' . $q . '%';
}

$sql .= " ORDER BY c.created_at DESC LIMIT 120";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$pageTitle = "Comments - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';

$user = auth_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Comment Moderation</h1>
            <div class="small text-muted">Approve, reject, and keep discussions clean.</div>
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

    <!-- Status tabs -->
    <div class="np-card p-2 bg-white mb-3">
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm <?= $status === 'pending' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">Pending (<?= (int) $cntPending ?>)</a>
            <a class="btn btn-sm <?= $status === 'approved' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=approved">Approved (<?= (int) $cntApproved ?>)</a>
            <a class="btn btn-sm <?= $status === 'rejected' ? 'btn-dark' : 'btn-outline-dark' ?>"
                href="<?= h($ADMIN_URL) ?>/comments.php?status=rejected">Rejected (<?= (int) $cntRejected ?>)</a>

            <div class="ms-auto" style="min-width:320px;">
                <form method="get" class="d-flex gap-2">
                    <input type="hidden" name="status" value="<?= h($status) ?>">
                    <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                        placeholder="Search comment/user/article...">
                    <button class="btn btn-sm btn-outline-dark" type="submit">Search</button>
                </form>
            </div>
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
                    $cid = (int) $r['id'];
                    $cStatus = (string) $r['status'];
                    $badge = 'text-bg-light border';
                    if ($cStatus === 'pending')
                        $badge = 'text-bg-warning';
                    if ($cStatus === 'approved')
                        $badge = 'text-bg-success';
                    if ($cStatus === 'rejected')
                        $badge = 'text-bg-secondary';

                    $back = $_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/comments.php?status=' . urlencode($status));
                    ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-2">

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                    <span class="badge <?= h($badge) ?>"><?= h(ucfirst($cStatus)) ?></span>
                                    <span
                                        class="small text-muted"><?= h(date('M d, Y H:i', strtotime((string) $r['created_at']))) ?></span>
                                    <span class="small text-muted">•</span>
                                    <span class="small">
                                        By <strong><?= h($r['user_name']) ?></strong>
                                        <span class="text-muted">(<?= h($r['user_email']) ?>)</span>
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <div class="fw-semibold">On:</div>
                                    <a class="text-decoration-none" target="_blank" rel="noopener"
                                        href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $r['article_slug']) ?>">
                                        <?= h($r['article_title']) ?>
                                    </a>
                                    <span class="text-muted small"> (slug: <?= h($r['article_slug']) ?>)</span>
                                </div>

                                <div class="np-card p-3 bg-light-subtle border rounded-3">
                                    <?= nl2br(h((string) $r['comment'])) ?>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-end">
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

                                <?php if ($cStatus !== 'rejected'): ?>
                                    <form method="post" action="<?= h($ADMIN_URL) ?>/comment_action.php">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= $cid ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="back" value="<?= h($back) ?>">
                                        <button class="btn btn-sm btn-outline-dark" type="submit">
                                            <i class="bi bi-slash-circle"></i> Reject
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

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>