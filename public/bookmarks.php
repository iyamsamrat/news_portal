<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/models/Bookmark.php';
require_once __DIR__ . '/../app/config/config.php';

auth_require_login();
$user = auth_user();

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$items = Bookmark::listForUser((int) $user['id'], 60);

$pageTitle = "My Bookmarks - " . APP_NAME;
require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main" class="container my-4">
    <div class="np-card p-4 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="h4 mb-1 np-title">My Bookmarks</h1>
                <div class="small text-muted">Saved articles for quick access.</div>
            </div>
            <a class="btn btn-sm btn-outline-dark" href="<?= h(BASE_URL) ?>/index.php">Browse News</a>
        </div>
    </div>

    <div class="row g-3">
        <?php if (empty($items)): ?>
            <div class="col-12">
                <div class="np-card p-4 text-center text-muted">
                    You have no bookmarks yet. Open an article and tap “Save”.
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($items as $a): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="np-card h-100 p-3 d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <a class="badge text-bg-light border text-decoration-none"
                            href="<?= h(BASE_URL) ?>/index.php?category=<?= urlencode((string) $a['category_slug']) ?>">
                            <?= h($a['category_name'] ?? 'General') ?>
                        </a>
                        <span class="small text-muted">
                            <?= $a['published_at'] ? h(date('M d, Y', strtotime((string) $a['published_at']))) : '' ?>
                        </span>
                    </div>

                    <?php if (!empty($a['cover_image'])): ?>
                        <div class="mb-2">
                            <img src="<?= h(UPLOAD_URL) ?>/<?= h($a['cover_image']) ?>" class="img-fluid rounded-3 border"
                                alt="Article image">
                        </div>
                    <?php endif; ?>

                    <h2 class="h6 mb-2"><?= h($a['title']) ?></h2>

                    <p class="small text-muted flex-grow-1">
                        <?= h($a['summary'] ?: 'Click to read the full article.') ?>
                    </p>

                    <a class="btn btn-sm btn-outline-dark w-100 mt-2"
                        href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string) $a['slug']) ?>">
                        Open
                    </a>

                    <div class="small text-muted mt-2">
                        Saved: <?= h(date('M d, Y', strtotime((string) $a['bookmarked_at']))) ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>