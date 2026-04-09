<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/security.php';
require_once __DIR__ . '/../app/models/Article.php';
require_once __DIR__ . '/../app/models/Bookmark.php';
require_once __DIR__ . '/../app/models/Rating.php';
require_once __DIR__ . '/../app/models/Comment.php';
require_once __DIR__ . '/../app/config/config.php';

auth_start();

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$article = Article::findPublishedBySlug($slug);
if (!$article) {
    http_response_code(404);
    $pageTitle = "Not Found - " . APP_NAME;
    require_once __DIR__ . '/../app/views/partials/header.php';
    require_once __DIR__ . '/../app/views/partials/navbar.php';
    ?>
    <main id="main" class="container my-4" style="max-width: 900px;">
        <div class="np-card p-4">
            <h1 class="h4 mb-2">Article not found</h1>
            <div class="text-muted">The article may be unpublished or the link is incorrect.</div>
            <a class="btn btn-sm btn-dark mt-3" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Back to Home</a>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/../app/views/partials/footer.php';
    exit;
}

// Logged-in user
$user = auth_user();
$userId = $user ? (int) $user['id'] : null;

// Track view
$sessionId = session_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = $ip !== '' ? hash('sha256', $ip) : null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

Article::incrementView((int) $article['id'], $userId, $sessionId, $ipHash, $userAgent);

// Stats (ratings + comments count)
$ratingStats = Article::getAvgRating((int) $article['id']);
$commentsCount = Article::getCommentsCount((int) $article['id']);

// Bookmark state
$isBookmarked = false;
if ($user) {
    $isBookmarked = Bookmark::isBookmarked((int) $user['id'], (int) $article['id']);
}

// User rating
$userRating = null;
if ($user) {
    $userRating = Rating::getUserRating((int) $user['id'], (int) $article['id']);
}

// Approved comments — paginated
$commentsPerPage   = 10;
$cpage             = max(1, (int) ($_GET['cpage'] ?? 1));
$totalComments     = $commentsCount; // already fetched above (approved only)
$totalCPages       = max(1, (int) ceil($totalComments / $commentsPerPage));
$cpage             = min($cpage, $totalCPages);
$commentOffset     = ($cpage - 1) * $commentsPerPage;
$approvedComments  = Comment::listApprovedForArticle(
    (int) $article['id'],
    $commentsPerPage,
    $commentOffset
);

$pageTitle = $article['title'] . " - " . APP_NAME;

require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>

<main id="main" class="container my-4" style="max-width: 900px;">
    <article class="np-card p-4 p-md-5">

        <!-- Article header -->
        <?php
        $catSlugA = (string) ($article['category_slug'] ?? '');
        $catNameA = $article['category_name'] ?? 'General';
        ?>
        <?php if ($catSlugA !== ''): ?>
            <a class="np-article-cat"
               href="<?= h(BASE_URL) ?>/search.php?category=<?= urlencode($catSlugA) ?>">
                <?= h($catNameA) ?>
            </a>
        <?php else: ?>
            <span class="np-article-cat"><?= h($catNameA) ?></span>
        <?php endif; ?>

        <h1 class="np-article-title"><?= h($article['title']) ?></h1>

        <?php if (!empty($article['summary'])): ?>
            <p style="font-size:1.05rem; color:#555; line-height:1.65; margin-bottom:20px;">
                <?= h($article['summary']) ?>
            </p>
        <?php endif; ?>

        <div class="np-article-meta">
            <?php if (!empty($article['author_name'])): ?>
                <span><i class="bi bi-person me-1"></i><?= h($article['author_name']) ?></span>
            <?php endif; ?>
            <?php if ($article['published_at']): ?>
                <span><i class="bi bi-calendar3 me-1"></i>
                    <?= h(date('F j, Y', strtotime((string) $article['published_at']))) ?>
                </span>
            <?php endif; ?>
            <span><i class="bi bi-star me-1"></i>
                <?= number_format((float) $ratingStats['avg_rating'], 1) ?>
                (<?= (int) $ratingStats['ratings_count'] ?>)
            </span>
            <span><i class="bi bi-chat me-1"></i><?= (int) $commentsCount ?> comments</span>
            <?php if (!empty($article['sentiment_label'])): ?>
                <?php
                $sl  = $article['sentiment_label'];
                $sIc = $sl === 'positive' ? 'bi-emoji-smile' : ($sl === 'negative' ? 'bi-emoji-frown' : 'bi-emoji-neutral');
                $sCl = $sl === 'positive' ? 'text-bg-success' : ($sl === 'negative' ? 'text-bg-danger' : 'text-bg-secondary');
                ?>
                <span class="badge <?= $sCl ?> d-inline-flex align-items-center gap-1">
                    <i class="bi <?= $sIc ?>"></i><?= ucfirst($sl) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($article['cover_image'])): ?>
            <div class="mb-4">
                <img class="img-fluid rounded-4 border" alt="Cover image"
                    src="<?= h(UPLOAD_URL) ?>/<?= h($article['cover_image']) ?>">
            </div>
        <?php endif; ?>

        <?php if (($article['media_type'] ?? 'none') !== 'none' && !empty($article['media_url'])): ?>
            <div class="mb-4">
                <div class="np-card p-3">
                    <div class="small text-muted mb-2">Media</div>
                    <a href="<?= h($article['media_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                        <?= h($article['media_url']) ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="np-prose">
            <?php
            $paras = preg_split("/\R\R+/", (string) $article['content']);
            foreach ($paras as $p) {
                $p = trim($p);
                if ($p === '') continue;
                echo '<p>' . nl2br(h($p)) . '</p>';
            }
            ?>
        </div>

        <hr class="my-4">

        <!-- Action bar -->
        <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex gap-2 flex-wrap">

                <?php if ($user): ?>

                    <!-- Save/Unsave Bookmark -->
                    <form method="post" action="<?= h(BASE_URL) ?>/bookmark_toggle.php" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                        <input type="hidden" name="slug" value="<?= h($slug) ?>">
                        <button class="btn btn-sm <?= $isBookmarked ? 'btn-dark' : 'btn-outline-dark' ?>" type="submit">
                            <i class="bi <?= $isBookmarked ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                            <?= $isBookmarked ? 'Saved' : 'Save' ?>
                        </button>
                    </form>

                    <!-- Ratings (1–5) -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted">Your rating:</span>

                        <form method="post" action="<?= h(BASE_URL) ?>/rate_article.php" class="d-inline-flex gap-1">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                            <input type="hidden" name="slug" value="<?= h($slug) ?>">

                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button class="btn btn-sm <?= ($userRating === $i) ? 'btn-dark' : 'btn-outline-dark' ?>"
                                    type="submit" name="rating" value="<?= $i ?>" title="Rate <?= $i ?> out of 5">
                                    <i
                                        class="bi <?= ($userRating !== null && $userRating >= $i) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                </button>
                            <?php endfor; ?>
                        </form>

                        <?php if ($userRating !== null): ?>
                            <span class="small text-muted">(<?= (int) $userRating ?>/5)</span>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <a class="btn btn-sm btn-dark" href="<?= h(BASE_URL) ?>/login.php">Login to save, rate & comment</a>
                <?php endif; ?>

            </div>

            <!-- Social share -->
            <div class="d-flex gap-2">
                <?php
                $fullUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
                    ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
                ?>
                <a class="btn btn-sm btn-light border" target="_blank" rel="noopener"
                    href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($fullUrl) ?>">
                    Share
                </a>
            </div>
        </div>

        <!-- COMMENTS SECTION -->
        <hr class="my-4">
        <section id="comments">
            <h2 class="h5 mb-2">Comments</h2>
            <div class="small text-muted mb-3">
                Only approved comments are visible. New comments may require approval.
            </div>

            <?php if ($user): ?>
                <div class="np-card p-3 mb-3">
                    <form method="post" action="<?= h(BASE_URL) ?>/comment_create.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                        <input type="hidden" name="slug" value="<?= h($slug) ?>">

                        <label class="form-label small text-muted">Write a comment</label>
                        <textarea class="form-control" name="comment" rows="3" maxlength="1000"
                            placeholder="Type your comment..." required></textarea>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="small text-muted">Max 1000 characters.</div>
                            <button class="btn btn-sm btn-dark" type="submit">Post Comment</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="np-card p-3 mb-3">
                    <div class="text-muted">
                        <a href="<?= h(BASE_URL) ?>/login.php">Login</a> to comment.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($approvedComments) && $cpage === 1): ?>
                <div class="np-card p-3 text-muted">No comments yet. Be the first!</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2 mb-3">
                    <?php foreach ($approvedComments as $c): ?>
                        <?php
                        $csl    = $c['sentiment_label'] ?? null;
                        $cIcon  = $csl === 'positive' ? 'bi-emoji-smile' : ($csl === 'negative' ? 'bi-emoji-frown' : 'bi-emoji-neutral');
                        $cClass = $csl === 'positive' ? 'text-bg-success' : ($csl === 'negative' ? 'text-bg-danger' : 'text-bg-secondary');
                        ?>
                        <div class="np-card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold"><?= h($c['user_name']) ?></span>
                                    <?php if ($csl): ?>
                                        <span class="badge <?= $cClass ?> d-inline-flex align-items-center gap-1" style="font-size:.7rem;">
                                            <i class="bi <?= $cIcon ?>"></i> <?= ucfirst($csl) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <?= h(date('M d, Y H:i', strtotime((string) $c['created_at']))) ?>
                                </div>
                            </div>
                            <div><?= nl2br(h($c['comment'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalCPages > 1): ?>
                    <?php
                    $cpUrl = function (int $p) use ($slug): string {
                        $params = ['slug' => $slug];
                        if ($p > 1) $params['cpage'] = $p;
                        return '?' . http_build_query($params) . '#comments';
                    };
                    ?>
                    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                        <div class="small text-muted">
                            Page <?= $cpage ?> of <?= $totalCPages ?>
                            &nbsp;·&nbsp; <?= $totalComments ?> comment<?= $totalComments !== 1 ? 's' : '' ?>
                        </div>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $cpage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $cpage > 1 ? h($cpUrl($cpage - 1)) : '#' ?>">‹</a>
                            </li>
                            <?php for ($cp = 1; $cp <= $totalCPages; $cp++): ?>
                                <li class="page-item <?= $cp === $cpage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= h($cpUrl($cp)) ?>"><?= $cp ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $cpage >= $totalCPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $cpage < $totalCPages ? h($cpUrl($cpage + 1)) : '#' ?>">›</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    </article>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>