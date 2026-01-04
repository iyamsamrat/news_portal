<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin']);

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . $ADMIN_URL . '/users.php');
    exit;
}

$st = $pdo->prepare("SELECT id, name, email, role, avatar_url, bio, is_active, created_at FROM users WHERE id=:id LIMIT 1");
$st->execute(['id' => $id]);
$userRow = $st->fetch();

if (!$userRow) {
    header('Location: ' . $ADMIN_URL . '/users.php');
    exit;
}

// Activity counts
function countQuery(PDO $pdo, string $sql, array $params = []): int
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
}

$articleCount = countQuery($pdo, "SELECT COUNT(*) FROM articles WHERE created_by = :uid", ['uid' => $id]);
$commentCount = countQuery($pdo, "SELECT COUNT(*) FROM comments WHERE user_id = :uid", ['uid' => $id]);
$ratingCount = countQuery($pdo, "SELECT COUNT(*) FROM ratings WHERE user_id = :uid", ['uid' => $id]);
$bookmarkCount = countQuery($pdo, "SELECT COUNT(*) FROM bookmarks WHERE user_id = :uid", ['uid' => $id]);

$pageTitle = "User Details - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">User Details</h1>
            <div class="small text-muted">Profile information and activity overview.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/users.php">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="np-card p-3 p-md-4 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle border d-flex align-items-center justify-content-center"
                        style="width:56px;height:56px;">
                        <i class="bi bi-person fs-3 text-muted"></i>
                    </div>
                    <div>
                        <div class="h5 mb-0">
                            <?= h((string) $userRow['name']) ?>
                        </div>
                        <div class="text-muted small">
                            <?= h((string) $userRow['email']) ?>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-2 small">
                    <div class="col-6 text-muted">Role</div>
                    <div class="col-6 fw-semibold">
                        <?= h(ucfirst((string) $userRow['role'])) ?>
                    </div>

                    <div class="col-6 text-muted">Active</div>
                    <div class="col-6 fw-semibold">
                        <?= ((int) $userRow['is_active'] === 1) ? 'Yes' : 'No' ?>
                    </div>

                    <div class="col-6 text-muted">Created</div>
                    <div class="col-6 fw-semibold">
                        <?= h(date('M d, Y H:i', strtotime((string) $userRow['created_at']))) ?>
                    </div>
                </div>

                <?php if (!empty($userRow['bio'])): ?>
                    <hr class="my-3">
                    <div class="small text-muted mb-1">Bio</div>
                    <div>
                        <?= nl2br(h((string) $userRow['bio'])) ?>
                    </div>
                <?php endif; ?>

                <hr class="my-3">

                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/users.php">Manage from list</a>
                    <form method="post" action="<?= h($ADMIN_URL) ?>/user_action.php"
                        onsubmit="return confirm('Generate a new temporary password for this user?');">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $userRow['id'] ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="back" value="<?= h($ADMIN_URL . '/users.php') ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="np-card p-3 bg-white">
                        <div class="small text-muted">Articles created</div>
                        <div class="h4 mb-0">
                            <?= (int) $articleCount ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="np-card p-3 bg-white">
                        <div class="small text-muted">Comments</div>
                        <div class="h4 mb-0">
                            <?= (int) $commentCount ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="np-card p-3 bg-white">
                        <div class="small text-muted">Ratings</div>
                        <div class="h4 mb-0">
                            <?= (int) $ratingCount ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="np-card p-3 bg-white">
                        <div class="small text-muted">Bookmarks</div>
                        <div class="h4 mb-0">
                            <?= (int) $bookmarkCount ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="np-card p-3 bg-white">
                        <div class="fw-semibold">Next improvements (optional)</div>
                        <div class="small text-muted">
                            Later we can add last login tracking, IP auditing, and user-specific content moderation
                            flags.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>