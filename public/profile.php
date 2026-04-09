<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/security.php';

auth_start();
auth_require_login();

$user   = auth_user();
$userId = (int) $user['id'];
$pdo    = db();

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$errors  = [];
$success = '';

// ── Fetch fresh user row ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, name, email, role, bio, created_at FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$dbUser = $stmt->fetch();
if (!$dbUser) {
    auth_logout();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ── Handle form submissions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        // ── Update name + bio ─────────────────────────────────────────
        if ($action === 'update_name') {
            $newName = trim((string) ($_POST['name'] ?? ''));
            $newBio  = trim((string) ($_POST['bio']  ?? ''));
            if ($newName === '') {
                $errors[] = 'Name cannot be empty.';
            } elseif (mb_strlen($newName) > 100) {
                $errors[] = 'Name is too long (max 100 characters).';
            } else {
                $pdo->prepare("UPDATE users SET name = :n, bio = :b WHERE id = :id")
                    ->execute(['n' => $newName, 'b' => $newBio ?: null, 'id' => $userId]);
                $_SESSION['user']['name'] = $newName;
                $dbUser['name'] = $newName;
                $dbUser['bio']  = $newBio;
                $success = 'Profile updated successfully.';
            }
        }

        // ── Change password ───────────────────────────────────────────
        if ($action === 'change_password') {
            $current  = (string) ($_POST['current_password']  ?? '');
            $newPass  = (string) ($_POST['new_password']      ?? '');
            $confirm  = (string) ($_POST['confirm_password']  ?? '');

            $hashStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
            $hashStmt->execute(['id' => $userId]);
            $hash = (string) ($hashStmt->fetchColumn() ?: '');

            if (!password_verify($current, $hash)) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($newPass !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                    ->execute(['h' => $newHash, 'id' => $userId]);
                $success = 'Password changed successfully.';
            }
        }
    }
}

// ── Stats ─────────────────────────────────────────────────────────────
$statsStmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM bookmarks  WHERE user_id = :uid1) AS bookmarks,
        (SELECT COUNT(*) FROM ratings    WHERE user_id = :uid2) AS ratings,
        (SELECT COUNT(*) FROM comments   WHERE user_id = :uid3 AND status = 'approved') AS comments
");
$statsStmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
$stats = $statsStmt->fetch();

$pageTitle = "My Profile – " . APP_NAME;
require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main" class="container my-4" style="max-width: 780px;">

    <h1 class="h4 mb-4">My Profile</h1>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Account Info ─────────────────────────────────────────── -->
    <div class="np-card p-4 mb-4">
        <h2 class="h6 fw-semibold mb-3">Account Information</h2>
        <div class="row g-2 mb-1">
            <div class="col-5 col-md-3 text-muted small">Email</div>
            <div class="col-7 col-md-9 small"><?= h($dbUser['email']) ?></div>
        </div>
        <div class="row g-2 mb-1">
            <div class="col-5 col-md-3 text-muted small">Role</div>
            <div class="col-7 col-md-9">
                <span class="badge text-bg-secondary"><?= h(ucfirst($dbUser['role'])) ?></span>
            </div>
        </div>
        <div class="row g-2 mb-1">
            <div class="col-5 col-md-3 text-muted small">Member since</div>
            <div class="col-7 col-md-9 small">
                <?= h(date('F j, Y', strtotime((string) $dbUser['created_at']))) ?>
            </div>
        </div>
        <?php if (!empty($dbUser['bio'])): ?>
        <div class="row g-2">
            <div class="col-5 col-md-3 text-muted small">Bio</div>
            <div class="col-7 col-md-9 small"><?= h($dbUser['bio']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Activity Stats ───────────────────────────────────────── -->
    <div class="np-card p-4 mb-4">
        <h2 class="h6 fw-semibold mb-3">My Activity</h2>
        <div class="d-flex gap-4 flex-wrap">
            <a href="<?= h(BASE_URL) ?>/bookmarks.php" class="text-decoration-none text-dark text-center">
                <div class="fs-4 fw-bold"><?= (int) ($stats['bookmarks'] ?? 0) ?></div>
                <div class="small text-muted">Bookmarks</div>
            </a>
            <a href="<?= h(BASE_URL) ?>/my_ratings.php" class="text-decoration-none text-dark text-center">
                <div class="fs-4 fw-bold"><?= (int) ($stats['ratings'] ?? 0) ?></div>
                <div class="small text-muted">Ratings</div>
            </a>
            <div class="text-center">
                <div class="fs-4 fw-bold"><?= (int) ($stats['comments'] ?? 0) ?></div>
                <div class="small text-muted">Comments</div>
            </div>
        </div>
    </div>

    <!-- ── Update Name ──────────────────────────────────────────── -->
    <div class="np-card p-4 mb-4">
        <h2 class="h6 fw-semibold mb-3">Update Display Name</h2>
        <form method="post" action="<?= h(BASE_URL) ?>/profile.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_name">
            <div class="mb-3">
                <label class="form-label small text-muted">Display Name</label>
                <input class="form-control" type="text" name="name"
                       value="<?= h($dbUser['name']) ?>" maxlength="100" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">Bio <span class="text-muted">(optional)</span></label>
                <textarea class="form-control" name="bio" rows="3" maxlength="500"
                    placeholder="Tell something about yourself..."><?= h($dbUser['bio'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-sm btn-dark" type="submit">Save Profile</button>
        </form>
    </div>

    <!-- ── Change Password ──────────────────────────────────────── -->
    <div class="np-card p-4">
        <h2 class="h6 fw-semibold mb-3">Change Password</h2>
        <form method="post" action="<?= h(BASE_URL) ?>/profile.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
                <label class="form-label small text-muted">Current Password</label>
                <input class="form-control" type="password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">New Password</label>
                <input class="form-control" type="password" name="new_password"
                       minlength="8" required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">Confirm New Password</label>
                <input class="form-control" type="password" name="confirm_password" required>
            </div>
            <button class="btn btn-sm btn-dark" type="submit">Change Password</button>
        </form>
    </div>

</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>
