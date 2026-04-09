<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/auth.php';

auth_start();
$pdo = db();

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// ── Hero: featured or latest ─────────────────────────────────────
$hero = $pdo->query("
    SELECT a.id, a.title, a.slug, a.summary, a.cover_image, a.published_at,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'published'
    ORDER BY a.is_featured DESC, a.published_at DESC
    LIMIT 1
")->fetch() ?: null;

$heroId = $hero ? (int) $hero['id'] : 0;

// ── Latest (excluding hero) ───────────────────────────────────────
$latestStmt = $pdo->prepare("
    SELECT a.id, a.title, a.slug, a.summary, a.cover_image, a.published_at,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.status = 'published' AND a.id != :hid
    ORDER BY a.published_at DESC
    LIMIT 9
");
$latestStmt->execute(['hid' => $heroId]);
$articles = $latestStmt->fetchAll() ?: [];

// ── Most read (last 7 days) ───────────────────────────────────────
try {
    $popular = $pdo->query("
        SELECT a.id, a.title, a.slug, a.cover_image, a.published_at,
               COUNT(v.id) AS views
        FROM article_views v
        INNER JOIN articles a ON a.id = v.article_id
        WHERE a.status = 'published'
          AND v.created_at >= (NOW() - INTERVAL 7 DAY)
        GROUP BY a.id
        ORDER BY views DESC
        LIMIT 5
    ")->fetchAll() ?: [];
} catch (Throwable $e) { $popular = []; }

// ── Categories (sidebar) ──────────────────────────────────────────
try {
    $cats = $pdo->query("
        SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY name ASC LIMIT 12
    ")->fetchAll() ?: [];
} catch (Throwable $e) { $cats = []; }

$pageTitle = APP_NAME;
require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main">
<div class="container py-4" style="max-width:1160px;">

    <?php if ($hero): ?>
    <?php
    $heroUrl  = BASE_URL . '/article.php?slug=' . urlencode((string) $hero['slug']);
    $heroImg  = !empty($hero['cover_image']) ? UPLOAD_URL . '/' . (string) $hero['cover_image'] : null;
    $heroCat  = $hero['category_name'] ?? '';
    $heroDate = $hero['published_at'] ? date('M j, Y', strtotime((string) $hero['published_at'])) : '';
    ?>
    <div class="mb-4">
        <a class="np-hero" href="<?= h($heroUrl) ?>">
            <?php if ($heroImg): ?>
                <img class="np-hero-img" src="<?= h($heroImg) ?>" alt="<?= h($hero['title']) ?>">
            <?php else: ?>
                <div class="np-hero-no-img"></div>
            <?php endif; ?>
            <div class="np-hero-overlay">
                <?php if ($heroCat !== ''): ?>
                    <span class="np-hero-cat"><?= h($heroCat) ?></span>
                <?php endif; ?>
                <div class="np-hero-title"><?= h($hero['title']) ?></div>
                <?php if (!empty($hero['summary'])): ?>
                    <div class="np-hero-meta d-none d-md-block mb-1">
                        <?= h(mb_strimwidth((string) $hero['summary'], 0, 160, '…')) ?>
                    </div>
                <?php endif; ?>
                <?php if ($heroDate): ?>
                    <div class="np-hero-meta">
                        <i class="bi bi-clock me-1"></i><?= h($heroDate) ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── Article grid ─────────────────────────────────────── -->
        <div class="col-12 col-lg-8">

            <?php if (!empty($articles)): ?>
            <div class="np-section-title">Latest News</div>
            <div class="row g-3 mb-4">
                <?php foreach ($articles as $a):
                    $url     = BASE_URL . '/article.php?slug=' . urlencode((string) $a['slug']);
                    $catN    = $a['category_name'] ?? '';
                    $catS    = $a['category_slug'] ?? '';
                    $catUrl  = $catS !== '' ? BASE_URL . '/search.php?category=' . urlencode($catS) : '';
                    $date    = $a['published_at'] ? date('M j, Y', strtotime((string) $a['published_at'])) : '';
                    $img     = !empty($a['cover_image']) ? UPLOAD_URL . '/' . (string) $a['cover_image'] : null;
                ?>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="np-card">
                        <?php if ($img): ?>
                        <div class="np-card-img-wrap">
                            <a href="<?= h($url) ?>">
                                <img class="np-card-img" src="<?= h($img) ?>" alt="<?= h($a['title']) ?>">
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="np-card-body">
                            <?php if ($catN !== ''): ?>
                                <?php if ($catUrl !== ''): ?>
                                    <a class="np-card-cat" href="<?= h($catUrl) ?>"><?= h($catN) ?></a>
                                <?php else: ?>
                                    <span class="np-card-cat"><?= h($catN) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a class="np-card-title" href="<?= h($url) ?>"><?= h($a['title']) ?></a>
                            <?php if (!empty($a['summary'])): ?>
                                <div class="np-card-summary"><?= h($a['summary']) ?></div>
                            <?php endif; ?>
                            <div class="np-card-meta">
                                <i class="bi bi-clock"></i> <?= h($date) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center">
                <a class="btn btn-outline-dark btn-sm px-4"
                   href="<?= h(BASE_URL) ?>/search.php?sort=latest">
                    More Articles &nbsp;<i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php else: ?>
            <div class="text-center py-5 text-muted">No articles published yet.</div>
            <?php endif; ?>

        </div>

        <!-- ── Sidebar ───────────────────────────────────────────── -->
        <div class="col-12 col-lg-4">

            <?php if (!empty($popular)): ?>
            <div class="np-sidebar-box">
                <div class="np-section-title">Most Read</div>
                <div class="np-list">
                    <?php foreach ($popular as $i => $p):
                        $pUrl   = BASE_URL . '/article.php?slug=' . urlencode((string) $p['slug']);
                        $pImg   = !empty($p['cover_image']) ? UPLOAD_URL . '/' . (string) $p['cover_image'] : null;
                        $pDate  = $p['published_at'] ? date('M j', strtotime((string) $p['published_at'])) : '';
                    ?>
                    <a class="np-list-item" href="<?= h($pUrl) ?>">
                        <span class="np-list-num"><?= $i + 1 ?></span>
                        <?php if ($pImg): ?>
                            <img class="np-list-thumb" src="<?= h($pImg) ?>" alt="">
                        <?php endif; ?>
                        <div class="np-list-text">
                            <div class="np-list-title"><?= h($p['title']) ?></div>
                            <div class="np-list-date">
                                <i class="bi bi-eye me-1"></i><?= (int)($p['views'] ?? 0) ?> views
                                <?php if ($pDate): ?> · <?= h($pDate) ?><?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($cats)): ?>
            <div class="np-sidebar-box">
                <div class="np-section-title">Browse Topics</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($cats as $c): ?>
                    <a class="np-topic-pill"
                       href="<?= h(BASE_URL) ?>/search.php?category=<?= urlencode($c['slug']) ?>">
                        <?= h($c['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>
