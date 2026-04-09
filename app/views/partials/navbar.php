<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/settings.php';
require_once __DIR__ . '/../../core/auth.php';

auth_start();
$user     = auth_user();
$siteName = setting_str('site_name', APP_NAME);

$categories = [];
try {
    require_once __DIR__ . '/../../config/db.php';
    $pdo  = db();
    $stmt = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY name ASC LIMIT 12");
    $categories = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {}

function hn(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>

<header class="np-header">

    <nav class="navbar navbar-expand-lg" style="padding: 0;">
        <div class="container" style="padding-top:10px; padding-bottom:10px;">

            <!-- Brand -->
            <a class="navbar-brand" href="<?= hn(BASE_URL) ?>/index.php">
                <span class="np-brand-mark">सु</span>
                <?= hn($siteName) ?>
            </a>

            <!-- Desktop links -->
            <div class="collapse navbar-collapse" id="npNav">
                <ul class="navbar-nav ms-3 me-auto gap-1">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= hn(BASE_URL) ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= hn(BASE_URL) ?>/search.php?sort=latest">Latest</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= hn(BASE_URL) ?>/search.php?sort=popular">Popular</a>
                    </li>
                </ul>
            </div>

            <!-- Right side -->
            <div class="d-flex align-items-center gap-2">

                <!-- Search toggle -->
                <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                        style="font-size:.82rem; border-color:#ddd;"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#npSearchCollapse"
                        aria-expanded="false">
                    <i class="bi bi-search"></i>
                    <span class="d-none d-md-inline">Search</span>
                </button>

                <!-- Account -->
                <?php if ($user): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-dark dropdown-toggle d-flex align-items-center gap-1"
                            style="font-size:.82rem;"
                            type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-fill"></i>
                        <span class="d-none d-sm-inline"><?= hn(mb_strimwidth($user['name'] ?? '', 0, 14, '…')) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow" style="min-width:190px; font-size:.875rem;">
                        <li>
                            <div class="px-3 py-2 text-muted" style="font-size:.78rem; border-bottom:1px solid #f0f0f0;">
                                <?= hn($user['email'] ?? '') ?>
                            </div>
                        </li>
                        <li><a class="dropdown-item py-2" href="<?= hn(BASE_URL) ?>/profile.php">
                            <i class="bi bi-person me-2 text-muted"></i>Profile
                        </a></li>
                        <li><a class="dropdown-item py-2" href="<?= hn(BASE_URL) ?>/bookmarks.php">
                            <i class="bi bi-bookmark me-2 text-muted"></i>Bookmarks
                        </a></li>
                        <li><a class="dropdown-item py-2" href="<?= hn(BASE_URL) ?>/my_ratings.php">
                            <i class="bi bi-star me-2 text-muted"></i>My Ratings
                        </a></li>
                        <?php if (in_array($user['role'] ?? '', ['admin', 'editor'], true)): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2" href="<?= hn(BASE_URL) ?>/admin/dashboard.php">
                            <i class="bi bi-grid me-2 text-muted"></i>Admin Panel
                        </a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= hn(BASE_URL) ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" style="font-size:.82rem; border-color:#ddd;"
                   href="<?= hn(BASE_URL) ?>/login.php">Login</a>
                <a class="btn btn-sm btn-dark" style="font-size:.82rem;"
                   href="<?= hn(BASE_URL) ?>/register.php">Register</a>
                <?php endif; ?>

                <!-- Hamburger -->
                <button class="navbar-toggler border-0 p-1" type="button"
                        data-bs-toggle="collapse" data-bs-target="#npNav">
                    <i class="bi bi-list" style="font-size:1.3rem;"></i>
                </button>

            </div>
        </div>
    </nav>

    <!-- Search bar (collapse) — overlays page, no layout shift -->
    <div class="collapse np-search-bar" id="npSearchCollapse">
        <div class="container">
            <div class="np-live-wrap">
                <form class="d-flex gap-2" action="<?= hn(BASE_URL) ?>/search.php" method="get" id="npSearchForm">
                    <input class="form-control" id="npSearchInput" style="font-size:.875rem;"
                           type="search" name="q" autocomplete="off"
                           placeholder="Search articles, topics…">
                    <button class="btn btn-dark px-4" style="font-size:.875rem;" type="submit">Search</button>
                </form>
                <div class="np-live-results" id="npLiveResults" hidden></div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var input   = document.getElementById('npSearchInput');
        var box     = document.getElementById('npLiveResults');
        var form    = document.getElementById('npSearchForm');
        var BASE    = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
        var timer;

        if (!input || !box) return;

        function esc(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function render(results, q) {
            box.innerHTML = '';
            if (!results.length) {
                box.innerHTML = '<div class="np-live-no-result">No results for "<strong>' + esc(q) + '</strong>"</div>';
                box.hidden = false;
                return;
            }
            results.forEach(function (r) {
                var a = document.createElement('a');
                a.href = r.url;
                a.className = 'np-live-result';
                a.innerHTML = '<span class="np-live-result-title">' + esc(r.title) + '</span>' +
                              (r.category ? '<span class="np-live-result-cat">' + esc(r.category) + '</span>' : '');
                box.appendChild(a);
            });
            var all = document.createElement('a');
            all.href = BASE + '/search.php?q=' + encodeURIComponent(q);
            all.className = 'np-live-result-all';
            all.textContent = 'See all results for "' + q + '" →';
            box.appendChild(all);
            box.hidden = false;
        }

        function hide() { box.hidden = true; }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { hide(); return; }
            timer = setTimeout(function () {
                fetch(BASE + '/search_suggest.php?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (d) { render(d.results || [], q); })
                    .catch(hide);
            }, 220);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 2 && box.children.length) box.hidden = false;
        });

        form.addEventListener('submit', hide);

        document.addEventListener('click', function (e) {
            if (!box.contains(e.target) && e.target !== input) hide();
        });

        /* Auto-focus when search panel opens */
        document.getElementById('npSearchCollapse').addEventListener('shown.bs.collapse', function () {
            input.focus();
        });
        document.getElementById('npSearchCollapse').addEventListener('hide.bs.collapse', function () {
            hide();
            input.value = '';
        });
    })();
    </script>

    <!-- Category bar -->
    <?php if ($categories): ?>
    <div class="np-catbar">
        <div class="container">
            <div class="np-catbar-scroll">
                <?php foreach ($categories as $c): ?>
                <a class="np-catlink"
                   href="<?= hn(BASE_URL) ?>/search.php?category=<?= urlencode($c['slug']) ?>">
                    <?= hn($c['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</header>
