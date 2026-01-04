<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/settings.php';

$siteName = setting_str('site_name', APP_NAME);
$siteTagline = setting_str('site_tagline', 'Minimal news experience. Built with PHP + MySQL.');

$fb = trim(setting_str('social_facebook', ''));
$yt = trim(setting_str('social_youtube', ''));
?>
<footer class="border-top bg-white mt-4">
    <div class="container py-4">
        <div class="row g-3 align-items-center">

            <div class="col-12 col-md-6">
                <div class="fw-semibold"><?= htmlspecialchars($siteName) ?></div>
                <div class="small text-muted">
                    <?= htmlspecialchars($siteTagline) ?>
                </div>

                <?php if ($fb !== '' || $yt !== ''): ?>
                    <div class="d-flex gap-3 mt-2 small">
                        <?php if ($fb !== ''): ?>
                            <a class="link-secondary text-decoration-none" target="_blank" rel="noopener"
                                href="<?= htmlspecialchars($fb) ?>">
                                <i class="bi bi-facebook"></i> Facebook
                            </a>
                        <?php endif; ?>

                        <?php if ($yt !== ''): ?>
                            <a class="link-secondary text-decoration-none" target="_blank" rel="noopener"
                                href="<?= htmlspecialchars($yt) ?>">
                                <i class="bi bi-youtube"></i> YouTube
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <ul class="list-inline mb-0 text-md-end">
                    <li class="list-inline-item">
                        <a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Home</a>
                    </li>
                    <li class="list-inline-item">
                        <a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=latest">Latest</a>
                    </li>
                    <li class="list-inline-item">
                        <a class="link-secondary text-decoration-none"
                            href="<?= htmlspecialchars(BASE_URL) ?>/search.php?sort=popular">Popular</a>
                    </li>
                    <li class="list-inline-item">
                        <span class="text-muted">© <?= date('Y') ?></span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>