<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/auth.php';

auth_start();
$user = auth_user();

// Settings
$siteName = setting_str('site_name', APP_NAME);

// Categories
$categories = [];
try {
    require_once __DIR__ . '/../../config/db.php';
    $pdo = db();
    $stmt = $pdo->query("
        SELECT name, slug
        FROM categories
        WHERE is_active = 1
        ORDER BY name ASC
        LIMIT 10
    ");
    $categories = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    // ignore
}
?>

<header class="np-header sticky-top bg-white border-bottom">

    <!-- TOP NAVBAR -->
    <nav class="navbar navbar-expand-lg bg-white">
        <div class="container py-2">

            <!-- LEFT: Brand -->
            <a class="navbar-brand d-flex align-items-center gap-2 me-4"
                href="<?= htmlspecialchars(BASE_URL) ?>/index.php">
                <span class="np-brand-mark">सु</span>
                <span class="fw-semibold"><?= htmlspecialchars($siteName) ?></span>
            </a>

            <!-- CENTER: Nav links -->
            <div class="collapse navbar-collapse" id="npNavLinks">
                <ul class="navbar-nav me-auto gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=latest">Latest</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=popular">Popular</a>
                    </li>
                </ul>
            </div>

            <!-- RIGHT: Search + Account -->
            <div class="d-flex align-items-center gap-2 ms-auto">

                <!-- Search toggle -->
                <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse"
                    data-bs-target="#npSearchBar" aria-expanded="false" aria-label="Toggle search">
                    <i class="bi bi-search"></i>
                    <span class="d-none d-md-inline ms-1">Search</span>
                </button>

                <!-- Account -->
                <?php if ($user): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <?= htmlspecialchars($user['name'] ?? 'Account') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/profile.php">
                                    Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/bookmarks.php">
                                    Bookmarks
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/my_ratings.php">
                                    Ratings
                                </a>
                            </li>

                            <?php if (in_array(($user['role'] ?? 'user'), ['admin', 'editor'], true)): ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/admin/dashboard.php">
                                        Admin Dashboard
                                    </a>
                                </li>
                            <?php endif; ?>

                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= htmlspecialchars(BASE_URL) ?>/logout.php">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars(BASE_URL) ?>/login.php">Login</a>
                    <a class="btn btn-sm btn-dark" href="<?= htmlspecialchars(BASE_URL) ?>/register.php">Register</a>
                <?php endif; ?>

                <!-- Mobile toggle -->
                <button class="navbar-toggler ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#npNavLinks"
                    aria-controls="npNavLinks" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

            </div>
        </div>
    </nav>

    <!-- SEARCH BAR -->
    <div class="collapse border-top" id="npSearchBar">
        <div class="container py-3">
            <form class="row g-2" action="<?= htmlspecialchars(BASE_URL) ?>/search.php" method="get">
                <div class="col-12 col-md-9">
                    <input class="form-control" type="search" name="q"
                        placeholder="Search headlines, topics, keywords..." autocomplete="off">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-dark" type="submit">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CATEGORY BAR -->
    <div class="np-catbar border-top bg-white">
        <div class="container">
            <div class="np-catbar-scroll py-2">
                <?php if ($categories): ?>
                    <?php foreach ($categories as $c): ?>
                        <a class="np-catlink"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=<?= urlencode($c['slug']) ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</header>