<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../core/auth.php';
auth_start();
$user = auth_user();


// Optional: show dynamic categories if DB exists
$categories = [];
try {
    require_once __DIR__ . '/../../config/db.php';
    $pdo = db();
    $stmt = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 10");
    $categories = $stmt->fetchAll();
} catch (Throwable $e) {
    // ignore
}
?>

<header class="np-header sticky-top bg-white border-bottom">
    <!-- Top row -->
    <nav class="navbar navbar-expand-lg bg-white">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">
                <span class="np-brand-mark">NP</span>
                <span class="fw-semibold"><?= htmlspecialchars(APP_NAME) ?></span>
            </a>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <!-- Search toggle -->
                <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse"
                    data-bs-target="#npSearchBar" aria-controls="npSearchBar" aria-expanded="false"
                    aria-label="Toggle search">
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
                            <li><a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/profile.php">Profile</a>
                            </li>
                            <li><a class="dropdown-item"
                                    href="<?= htmlspecialchars(BASE_URL) ?>/bookmarks.php">Bookmarks</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/my_ratings.php">Ratings</a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <?php if (($user['role'] ?? 'user') === 'admin' || ($user['role'] ?? 'user') === 'editor'): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/admin/dashboard.php">
                                        Admin Dashboard
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            <?php endif; ?>

                            <li><a class="dropdown-item text-danger"
                                    href="<?= htmlspecialchars(BASE_URL) ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-dark" href="<?= htmlspecialchars(BASE_URL) ?>/login.php">Login</a>
                    <a class="btn btn-sm btn-dark" href="<?= htmlspecialchars(BASE_URL) ?>/register.php">Register</a>
                <?php endif; ?>

                <!-- Mobile menu button -->
                <button class="navbar-toggler ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#npNavLinks"
                    aria-controls="npNavLinks" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <!-- Collapsible nav links (for smaller screens) -->
            <div class="collapse navbar-collapse mt-3 mt-lg-0" id="npNavLinks">
                <ul class="navbar-nav ms-lg-4 gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item"><a class="nav-link"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=latest">Latest</a></li>
                    <li class="nav-item"><a class="nav-link"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=popular">Popular</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Search bar (collapsible) -->
    <div class="collapse border-top" id="npSearchBar">
        <div class="container py-3">
            <form class="row g-2" action="<?= htmlspecialchars(BASE_URL) ?>/search.php" method="get" role="search">
                <div class="col-12 col-md-9">
                    <input class="form-control" type="search" name="q"
                        placeholder="Search headlines, topics, keywords..." aria-label="Search query"
                        autocomplete="off">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-dark" type="submit">Search</button>
                </div>
            </form>
            <div class="small text-muted mt-2">
                Tip: Use category links below to filter quickly.
            </div>
        </div>
    </div>

    <!-- Category row (horizontal scroll on mobile) -->
    <div class="np-catbar border-top bg-white">
        <div class="container">
            <div class="np-catbar-scroll py-2">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $c): ?>
                        <a class="np-catlink"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=<?= urlencode($c['slug']) ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if DB not loaded yet -->
                    <a class="np-catlink" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=top">Top</a>
                    <a class="np-catlink" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=world">World</a>
                    <a class="np-catlink" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=business">Business</a>
                    <a class="np-catlink"
                        href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=technology">Technology</a>
                    <a class="np-catlink" href="<?= htmlspecialchars(BASE_URL) ?>/search.php?category=sports">Sports</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>