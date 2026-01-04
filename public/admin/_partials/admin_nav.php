<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/core/auth.php';
require_once __DIR__ . '/../../../app/config/config.php';

$user = auth_user();
$role = $user['role'] ?? 'user';

$current = basename($_SERVER['PHP_SELF'] ?? '');
function isActive(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<aside class="admin-sidebar d-none d-lg-flex flex-column p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="admin-brand">
            <span class="mark">NP</span>
            <span>Admin</span>
        </div>
        <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars(BASE_URL) ?>/index.php" title="Back to site">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </div>

    <div class="small text-muted mb-3">
        Signed in as <strong><?= htmlspecialchars($user['name'] ?? 'User') ?></strong>
        <span class="badge text-bg-light border ms-1"><?= htmlspecialchars($role) ?></span>
    </div>

    <nav class="d-flex flex-column gap-1">
        <a class="admin-link <?= isActive('dashboard.php', $current) ?>" href="dashboard.php">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <!-- Phase 1 will use these -->
        <a class="admin-link <?= isActive('articles.php', $current) ?>" href="articles.php">
            <i class="bi bi-file-earmark-text"></i> Articles
        </a>

        <!-- Already built comment moderation earlier; keep link for convenience -->
        <a class="admin-link <?= isActive('comments.php', $current) ?>" href="comments.php">
            <i class="bi bi-chat-dots"></i> Comments
        </a>

        <!-- Coming soon -->
        <a class="admin-link <?= isActive('categories.php', $current) ?>" href="categories.php">
            <i class="bi bi-tags"></i> Categories
        </a>

        <a class="admin-link <?= isActive('tags.php', $current) ?>" href="tags.php">
            <i class="bi bi-hash"></i> Tags
        </a>

        <a class="admin-link <?= isActive('settings.php', $current) ?>" href="settings.php">
            <i class="bi bi-gear"></i> Settings
        </a>
    </nav>

    <div class="mt-auto pt-3 border-top">
        <a class="admin-link" href="<?= htmlspecialchars(BASE_URL) ?>/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>

<!-- Mobile topbar + offcanvas -->
<div class="flex-grow-1 d-flex flex-column">
    <div class="admin-topbar d-lg-none">
        <div class="container-fluid py-2 d-flex align-items-center justify-content-between">
            <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#adminMenu">
                <i class="bi bi-list"></i>
            </button>
            <div class="admin-brand">
                <span class="mark">NP</span><span>Admin</span>
            </div>
            <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="adminMenu">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Admin Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="small text-muted mb-3">
                Signed in as <strong><?= htmlspecialchars($user['name'] ?? 'User') ?></strong>
                <span class="badge text-bg-light border ms-1"><?= htmlspecialchars($role) ?></span>
            </div>

            <div class="d-flex flex-column gap-1">
                <a class="admin-link <?= isActive('dashboard.php', $current) ?>" href="dashboard.php">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
                <a class="admin-link <?= isActive('articles.php', $current) ?>" href="articles.php">
                    <i class="bi bi-file-earmark-text"></i> Articles
                </a>
                <a class="admin-link <?= isActive('comments.php', $current) ?>" href="comments.php">
                    <i class="bi bi-chat-dots"></i> Comments
                </a>
                <a class="admin-link <?= isActive('categories.php', $current) ?>" href="categories.php">
                    <i class="bi bi-tags"></i> Categories
                </a>
                <a class="admin-link <?= isActive('tags.php', $current) ?>" href="tags.php">
                    <i class="bi bi-hash"></i> Tags
                </a>
                <a class="admin-link <?= isActive('settings.php', $current) ?>" href="settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>

                <hr>
                <a class="admin-link" href="<?= htmlspecialchars(BASE_URL) ?>/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>