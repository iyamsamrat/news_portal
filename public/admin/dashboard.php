<?php
declare(strict_types=1);

/**
 * Admin Dashboard (Phase 0)
 * Location: /public/admin/dashboard.php
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';

auth_start();
auth_require_role(['admin', 'editor']);

$pdo = db();

function countSafe(PDO $pdo, string $sql): int
{
    try {
        $stmt = $pdo->query($sql);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function fetchAllSafe(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}


$user = auth_user();
$role = (string) ($user['role'] ?? 'user');

// ---- Metrics (safe even if table missing during dev)
$totalArticles = countSafe($pdo, "SELECT COUNT(*) FROM articles");
$published = countSafe($pdo, "SELECT COUNT(*) FROM articles WHERE status='published'");
$drafts = countSafe($pdo, "SELECT COUNT(*) FROM articles WHERE status='draft'");
$archived = countSafe($pdo, "SELECT COUNT(*) FROM articles WHERE status='archived'");

$pendingComments = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='pending'");
$approvedComments = countSafe($pdo, "SELECT COUNT(*) FROM comments WHERE status='approved'");

$totalUsers = countSafe($pdo, "SELECT COUNT(*) FROM users");
$activeUsers = countSafe($pdo, "SELECT COUNT(*) FROM users WHERE is_active=1");

// Trending (last 7 days) – if you have article_views table, else fallback empty
$trending = fetchAllSafe($pdo, "
    SELECT a.title, a.slug, COUNT(v.id) AS views_7d
    FROM article_views v
    INNER JOIN articles a ON a.id = v.article_id
    WHERE v.viewed_at >= (NOW() - INTERVAL 7 DAY)
    GROUP BY a.id
    ORDER BY views_7d DESC
    LIMIT 6
");

// Latest drafts
$latestDrafts = fetchAllSafe($pdo, "
    SELECT id, title, slug, created_at
    FROM articles
    WHERE status='draft'
    ORDER BY created_at DESC
    LIMIT 6
");

// Latest pending comments
$latestPendingComments = fetchAllSafe($pdo, "
    SELECT c.id, c.comment, c.created_at, a.title AS article_title, a.slug AS article_slug, u.name AS user_name
    FROM comments c
    INNER JOIN articles a ON a.id = c.article_id
    INNER JOIN users u ON u.id = c.user_id
    WHERE c.status='pending'
    ORDER BY c.created_at DESC
    LIMIT 6
");

// Absolute URLs (prevents wrong relative navigation)
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

$pageTitle = "Admin Dashboard - " . APP_NAME;
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Dashboard</h1>
            <div class="small text-muted">
                Signed in as <strong><?= h($user['name'] ?? 'Admin') ?></strong>
                <span class="badge text-bg-light border ms-1"><?= h($role) ?></span>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/articles.php">
                <i class="bi bi-file-earmark-plus"></i> Manage Articles
            </a>
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">
                <i class="bi bi-chat-dots"></i> Pending Comments (<?= (int) $pendingComments ?>)
            </a>
            <a class="btn btn-sm btn-outline-dark" href="<?= h(BASE_URL) ?>/index.php" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> View Site
            </a>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Total Articles</div>
                        <div class="h4 mb-0"><?= (int) $totalArticles ?></div>
                    </div>
                    <i class="bi bi-files fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Published</div>
                        <div class="h4 mb-0"><?= (int) $published ?></div>
                    </div>
                    <i class="bi bi-globe2 fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Drafts</div>
                        <div class="h4 mb-0"><?= (int) $drafts ?></div>
                    </div>
                    <i class="bi bi-pencil-square fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Archived</div>
                        <div class="h4 mb-0"><?= (int) $archived ?></div>
                    </div>
                    <i class="bi bi-archive fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Pending Comments</div>
                        <div class="h4 mb-0"><?= (int) $pendingComments ?></div>
                    </div>
                    <i class="bi bi-chat-square-dots fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Approved Comments</div>
                        <div class="h4 mb-0"><?= (int) $approvedComments ?></div>
                    </div>
                    <i class="bi bi-chat-left-text fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Total Users</div>
                        <div class="h4 mb-0"><?= (int) $totalUsers ?></div>
                    </div>
                    <i class="bi bi-people fs-3 text-muted"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Active Users</div>
                        <div class="h4 mb-0"><?= (int) $activeUsers ?></div>
                    </div>
                    <i class="bi bi-person-check fs-3 text-muted"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Panels -->
    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Latest Drafts</div>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/articles.php?status=draft">View
                        all</a>
                </div>

                <?php if (empty($latestDrafts)): ?>
                    <div class="text-muted small">No drafts found.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($latestDrafts as $d): ?>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                href="<?= h($ADMIN_URL) ?>/article_form.php?id=<?= (int) $d['id'] ?>">
                                <div>
                                    <div class="fw-semibold"><?= h($d['title']) ?></div>
                                    <div class="small text-muted">
                                        <?= h(date('M d, Y H:i', strtotime((string) $d['created_at']))) ?>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Pending Comments</div>
                    <a class="btn btn-sm btn-outline-dark"
                        href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">Moderate</a>
                </div>

                <?php if (empty($latestPendingComments)): ?>
                    <div class="text-muted small">No pending comments.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($latestPendingComments as $c): ?>
                            <a class="list-group-item list-group-item-action"
                                href="<?= h($ADMIN_URL) ?>/comments.php?status=pending">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold"><?= h($c['user_name']) ?></div>
                                        <div class="small text-muted">On: <?= h($c['article_title']) ?></div>
                                    </div>
                                    <div class="small text-muted">
                                        <?= h(date('M d, H:i', strtotime((string) $c['created_at']))) ?>
                                    </div>
                                </div>
                                <div class="small mt-2 text-body">
                                    <?= h(mb_strimwidth((string) $c['comment'], 0, 140, '...')) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12">
            <div class="np-card p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Trending (Last 7 days)</div>
                    <div class="small text-muted">Based on views</div>
                </div>

                <?php if (empty($trending)): ?>
                    <div class="text-muted small">
                        No view data yet. (This activates when `article_views` table has records.)
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th>Article</th>
                                    <th style="width:160px;">Views (7d)</th>
                                    <th style="width:140px;">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trending as $t): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($t['title']) ?></td>
                                        <td><?= (int) $t['views_7d'] ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener"
                                                href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $t['slug']) ?>">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>