<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';
require_once __DIR__ . '/../../app/models/Sentiment.php';

auth_start();
auth_require_role(['admin', 'editor']);

$pdo  = db();
$user = auth_user();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// ── Aggregate breakdowns ───────────────────────────────────────────
$commentSentiment = Sentiment::getBreakdown('comments');
$articleSentiment = Sentiment::getBreakdown('articles');

$cTotal = array_sum($commentSentiment);
$aTotal = array_sum($articleSentiment);

// ── Top positive articles ──────────────────────────────────────────
$topPositiveArticles = $pdo->query("
    SELECT id, title, slug, sentiment_score, sentiment_label
    FROM articles
    WHERE sentiment_label = 'positive'
    ORDER BY sentiment_score DESC
    LIMIT 6
")->fetchAll() ?: [];

// ── Top negative articles ──────────────────────────────────────────
$topNegativeArticles = $pdo->query("
    SELECT id, title, slug, sentiment_score, sentiment_label
    FROM articles
    WHERE sentiment_label = 'negative'
    ORDER BY sentiment_score ASC
    LIMIT 6
")->fetchAll() ?: [];

// ── Most positive comments ─────────────────────────────────────────
$topPositiveComments = $pdo->query("
    SELECT c.id, c.comment, c.sentiment_score, c.sentiment_label,
           u.name AS user_name, a.title AS article_title, a.slug AS article_slug
    FROM comments c
    INNER JOIN users    u ON u.id = c.user_id
    INNER JOIN articles a ON a.id = c.article_id
    WHERE c.sentiment_label = 'positive'
    ORDER BY c.sentiment_score DESC
    LIMIT 5
")->fetchAll() ?: [];

// ── Most negative comments ─────────────────────────────────────────
$topNegativeComments = $pdo->query("
    SELECT c.id, c.comment, c.sentiment_score, c.sentiment_label,
           u.name AS user_name, a.title AS article_title, a.slug AS article_slug
    FROM comments c
    INNER JOIN users    u ON u.id = c.user_id
    INNER JOIN articles a ON a.id = c.article_id
    WHERE c.sentiment_label = 'negative'
    ORDER BY c.sentiment_score ASC
    LIMIT 5
")->fetchAll() ?: [];

// ── Unanalysed counts ──────────────────────────────────────────────
$unanalysedArticles  = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE sentiment_label IS NULL")->fetchColumn();
$unanalysedComments  = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE sentiment_label IS NULL")->fetchColumn();

$pageTitle = "Sentiment Analysis – Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <!-- ── Page header ──────────────────────────────────────────── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-bar-chart-line me-2"></i>Sentiment Analysis</h1>
            <div class="small text-muted">
                Lexicon-based engine (English + Romanised &amp; Devanagari Nepali + Emojis)
            </div>
        </div>
        <form method="post" action="<?= h($ADMIN_URL) ?>/sentiment_rerun.php"
              onsubmit="return confirm('Re-analyse all articles and comments? This may take a moment.');">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit" class="btn btn-sm btn-dark">
                <i class="bi bi-arrow-repeat me-1"></i>Re-run Analysis
            </button>
        </form>
    </div>

    <?php if (!empty($_GET['sentiment_done'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        Re-scored: <strong><?= (int) ($_GET['a'] ?? 0) ?></strong> articles and
        <strong><?= (int) ($_GET['c'] ?? 0) ?></strong> comments.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($unanalysedArticles > 0 || $unanalysedComments > 0): ?>
    <div class="alert alert-warning py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= $unanalysedArticles ?> article(s) and <?= $unanalysedComments ?> comment(s) have not been analysed yet.
        Use <strong>Re-run Analysis</strong> to score them.
    </div>
    <?php endif; ?>

    <!-- ── KPI cards ────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <?php
        $kpis = [
            ['label' => 'Comments Analysed',  'val' => $cTotal, 'icon' => 'bi-chat-left-text',   'color' => 'text-primary'],
            ['label' => 'Positive Comments',  'val' => $commentSentiment['positive'] ?? 0,
             'sub'   => $cTotal > 0 ? round(($commentSentiment['positive'] ?? 0) / $cTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-smile',   'color' => 'text-success'],
            ['label' => 'Neutral Comments',   'val' => $commentSentiment['neutral']  ?? 0,
             'sub'   => $cTotal > 0 ? round(($commentSentiment['neutral']  ?? 0) / $cTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-neutral',  'color' => 'text-secondary'],
            ['label' => 'Negative Comments',  'val' => $commentSentiment['negative'] ?? 0,
             'sub'   => $cTotal > 0 ? round(($commentSentiment['negative'] ?? 0) / $cTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-frown',    'color' => 'text-danger'],
            ['label' => 'Articles Analysed',  'val' => $aTotal, 'icon' => 'bi-file-earmark-text', 'color' => 'text-primary'],
            ['label' => 'Positive Articles',  'val' => $articleSentiment['positive'] ?? 0,
             'sub'   => $aTotal > 0 ? round(($articleSentiment['positive'] ?? 0) / $aTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-smile',   'color' => 'text-success'],
            ['label' => 'Neutral Articles',   'val' => $articleSentiment['neutral']  ?? 0,
             'sub'   => $aTotal > 0 ? round(($articleSentiment['neutral']  ?? 0) / $aTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-neutral',  'color' => 'text-secondary'],
            ['label' => 'Negative Articles',  'val' => $articleSentiment['negative'] ?? 0,
             'sub'   => $aTotal > 0 ? round(($articleSentiment['negative'] ?? 0) / $aTotal * 100) . '%' : '—',
             'icon'  => 'bi-emoji-frown',    'color' => 'text-danger'],
        ];
        ?>

        <?php foreach ($kpis as $kpi): ?>
        <div class="col-6 col-md-3">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted"><?= $kpi['label'] ?></div>
                        <div class="h4 mb-0"><?= (int) $kpi['val'] ?></div>
                        <?php if (!empty($kpi['sub'])): ?>
                            <div class="small text-muted"><?= $kpi['sub'] ?></div>
                        <?php endif; ?>
                    </div>
                    <i class="bi <?= $kpi['icon'] ?> fs-3 <?= $kpi['color'] ?> opacity-75"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Charts ───────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="np-card p-4 bg-white h-100">
                <div class="fw-semibold mb-3">Comment Sentiment Distribution</div>
                <?php if ($cTotal > 0): ?>
                <div class="d-flex align-items-center gap-4">
                    <div style="position:relative;width:130px;height:130px;flex-shrink:0;">
                        <canvas id="commentChart"></canvas>
                    </div>
                    <div class="flex-grow-1">
                        <?php foreach (['positive' => ['bg-success','Positive'], 'neutral' => ['bg-secondary','Neutral'], 'negative' => ['bg-danger','Negative']] as $lbl => [$bg, $label]): ?>
                        <?php
                        $cnt = $commentSentiment[$lbl] ?? 0;
                        $pct = $cTotal > 0 ? round($cnt / $cTotal * 100) : 0;
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= $label ?></span>
                                <span class="text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar <?= $bg ?>" style="width:<?= $pct ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="text-muted small">No comments analysed yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="np-card p-4 bg-white h-100">
                <div class="fw-semibold mb-3">Article Sentiment Distribution</div>
                <?php if ($aTotal > 0): ?>
                <div class="d-flex align-items-center gap-4">
                    <div style="position:relative;width:130px;height:130px;flex-shrink:0;">
                        <canvas id="articleChart"></canvas>
                    </div>
                    <div class="flex-grow-1">
                        <?php foreach (['positive' => ['bg-success','Positive'], 'neutral' => ['bg-secondary','Neutral'], 'negative' => ['bg-danger','Negative']] as $lbl => [$bg, $label]): ?>
                        <?php
                        $cnt = $articleSentiment[$lbl] ?? 0;
                        $pct = $aTotal > 0 ? round($cnt / $aTotal * 100) : 0;
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= $label ?></span>
                                <span class="text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar <?= $bg ?>" style="width:<?= $pct ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="text-muted small">No articles analysed yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Article tables ───────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- Top Positive Articles -->
        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-emoji-smile text-success"></i>
                    <div class="fw-semibold">Most Positive Articles</div>
                </div>
                <?php if (empty($topPositiveArticles)): ?>
                    <div class="text-muted small">None found.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr class="small text-muted">
                            <th>Article</th><th style="width:100px;">Score</th><th style="width:60px;">Open</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($topPositiveArticles as $a): ?>
                        <tr>
                            <td class="small"><?= h($a['title']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar bg-success"
                                             style="width:<?= round(abs((float)$a['sentiment_score']) * 100) ?>%;"></div>
                                    </div>
                                    <span class="small text-muted" style="white-space:nowrap;">
                                        <?= number_format((float)$a['sentiment_score'], 2) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-dark py-0 px-1" target="_blank" rel="noopener"
                                   href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string)$a['slug']) ?>">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Negative Articles -->
        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-emoji-frown text-danger"></i>
                    <div class="fw-semibold">Most Negative Articles</div>
                </div>
                <?php if (empty($topNegativeArticles)): ?>
                    <div class="text-muted small">None found.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr class="small text-muted">
                            <th>Article</th><th style="width:100px;">Score</th><th style="width:60px;">Open</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($topNegativeArticles as $a): ?>
                        <tr>
                            <td class="small"><?= h($a['title']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar bg-danger"
                                             style="width:<?= round(abs((float)$a['sentiment_score']) * 100) ?>%;"></div>
                                    </div>
                                    <span class="small text-muted" style="white-space:nowrap;">
                                        <?= number_format((float)$a['sentiment_score'], 2) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-dark py-0 px-1" target="_blank" rel="noopener"
                                   href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string)$a['slug']) ?>">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Comment tables ───────────────────────────────────────── -->
    <div class="row g-3">
        <!-- Top Positive Comments -->
        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-emoji-smile text-success"></i>
                    <div class="fw-semibold">Most Positive Comments</div>
                </div>
                <?php if (empty($topPositiveComments)): ?>
                    <div class="text-muted small">None found.</div>
                <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($topPositiveComments as $c): ?>
                    <div class="border rounded p-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="small fw-semibold"><?= h($c['user_name']) ?></span>
                                <span class="badge text-bg-success ms-1" style="font-size:.65rem;">
                                    <?= number_format((float)$c['sentiment_score'], 2) ?>
                                </span>
                            </div>
                            <a class="small text-muted text-decoration-none" target="_blank" rel="noopener"
                               href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string)$c['article_slug']) ?>">
                                <?= h(mb_strimwidth((string)$c['article_title'], 0, 30, '…')) ?>
                                <i class="bi bi-box-arrow-up-right" style="font-size:.65rem;"></i>
                            </a>
                        </div>
                        <div class="small text-muted">
                            <?= h(mb_strimwidth((string)$c['comment'], 0, 120, '…')) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Negative Comments -->
        <div class="col-12 col-xl-6">
            <div class="np-card p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-emoji-frown text-danger"></i>
                    <div class="fw-semibold">Most Negative Comments</div>
                </div>
                <?php if (empty($topNegativeComments)): ?>
                    <div class="text-muted small">None found.</div>
                <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($topNegativeComments as $c): ?>
                    <div class="border rounded p-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="small fw-semibold"><?= h($c['user_name']) ?></span>
                                <span class="badge text-bg-danger ms-1" style="font-size:.65rem;">
                                    <?= number_format((float)$c['sentiment_score'], 2) ?>
                                </span>
                            </div>
                            <a class="small text-muted text-decoration-none" target="_blank" rel="noopener"
                               href="<?= h(BASE_URL) ?>/article.php?slug=<?= urlencode((string)$c['article_slug']) ?>">
                                <?= h(mb_strimwidth((string)$c['article_title'], 0, 30, '…')) ?>
                                <i class="bi bi-box-arrow-up-right" style="font-size:.65rem;"></i>
                            </a>
                        </div>
                        <div class="small text-muted">
                            <?= h(mb_strimwidth((string)$c['comment'], 0, 120, '…')) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<!-- Chart.js doughnut charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const COLORS = ['#198754', '#6c757d', '#dc3545'];

    <?php if ($cTotal > 0): ?>
    new Chart(document.getElementById('commentChart'), {
        type: 'doughnut',
        data: {
            labels: ['Positive', 'Neutral', 'Negative'],
            datasets: [{
                data: [
                    <?= (int)($commentSentiment['positive'] ?? 0) ?>,
                    <?= (int)($commentSentiment['neutral']  ?? 0) ?>,
                    <?= (int)($commentSentiment['negative'] ?? 0) ?>
                ],
                backgroundColor: COLORS,
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '65%'
        }
    });
    <?php endif; ?>

    <?php if ($aTotal > 0): ?>
    new Chart(document.getElementById('articleChart'), {
        type: 'doughnut',
        data: {
            labels: ['Positive', 'Neutral', 'Negative'],
            datasets: [{
                data: [
                    <?= (int)($articleSentiment['positive'] ?? 0) ?>,
                    <?= (int)($articleSentiment['neutral']  ?? 0) ?>,
                    <?= (int)($articleSentiment['negative'] ?? 0) ?>
                ],
                backgroundColor: COLORS,
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '65%'
        }
    });
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>
