<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/settings.php';

$siteName    = setting_str('site_name', APP_NAME);
$siteTagline = setting_str('site_tagline', '');
$fb          = trim(setting_str('social_facebook', ''));
$yt          = trim(setting_str('social_youtube', ''));

$cats = [];
try {
    require_once __DIR__ . '/../../config/db.php';
    $pdo  = db();
    $cats = $pdo->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY name ASC LIMIT 8")->fetchAll() ?: [];
} catch (Throwable $e) {}

function hf(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>

<footer class="np-footer">
    <div class="np-footer-inner">
        <div class="container">
            <div class="row g-4">

                <!-- Brand -->
                <div class="col-12 col-md-4">
                    <div class="np-footer-brand">
                        <span class="np-footer-mark">सु</span>
                        <?= hf($siteName) ?>
                    </div>
                    <?php if ($siteTagline): ?>
                        <p class="np-footer-tagline"><?= hf($siteTagline) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-3 mt-3">
                        <?php if ($fb): ?>
                            <a href="<?= hf($fb) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-facebook me-1"></i>Facebook
                            </a>
                        <?php endif; ?>
                        <?php if ($yt): ?>
                            <a href="<?= hf($yt) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-youtube me-1"></i>YouTube
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigate -->
                <div class="col-6 col-md-2">
                    <div class="np-footer-col-title">Navigate</div>
                    <ul>
                        <li><a href="<?= hf(BASE_URL) ?>/index.php">Home</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/search.php?sort=latest">Latest</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/search.php?sort=popular">Popular</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/bookmarks.php">Bookmarks</a></li>
                    </ul>
                </div>

                <!-- Categories -->
                <?php if (!empty($cats)): ?>
                <div class="col-6 col-md-3">
                    <div class="np-footer-col-title">Categories</div>
                    <ul>
                        <?php foreach ($cats as $c): ?>
                        <li>
                            <a href="<?= hf(BASE_URL) ?>/search.php?category=<?= urlencode($c['slug']) ?>">
                                <?= hf($c['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Account -->
                <div class="col-6 col-md-3">
                    <div class="np-footer-col-title">Account</div>
                    <ul>
                        <li><a href="<?= hf(BASE_URL) ?>/login.php">Login</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/register.php">Register</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/profile.php">My Profile</a></li>
                        <li><a href="<?= hf(BASE_URL) ?>/my_ratings.php">My Ratings</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
    <div class="np-footer-bottom">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <span>© <?= date('Y') ?> <?= hf($siteName) ?>. All rights reserved.</span>
            <span>Built with PHP &amp; MySQL</span>
        </div>
    </div>
</footer>

<!-- Scroll to top -->
<button id="np-scroll-top" aria-label="Back to top">
    <i class="bi bi-chevron-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const btn = document.getElementById('np-scroll-top');
    window.addEventListener('scroll', () => {
        btn.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();
</script>
</body>
</html>
