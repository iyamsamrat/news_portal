<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :t
        ");
        $st->execute(['t' => $table]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}


$hasTags = table_exists($pdo, 'tags') && table_exists($pdo, 'article_tags');
$user = auth_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');

$q = trim((string) ($_GET['q'] ?? ''));

// edit mode
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;

if ($hasTags && $editId > 0) {
    $st = $pdo->prepare("SELECT * FROM tags WHERE id=:id LIMIT 1");
    $st->execute(['id' => $editId]);
    $edit = $st->fetch() ?: null;
}

$rows = [];
if ($hasTags) {
    $sql = "
      SELECT
        t.id, t.name, t.slug, t.created_at,
        COALESCE(x.use_count, 0) AS use_count
      FROM tags t
      LEFT JOIN (
        SELECT tag_id, COUNT(*) AS use_count
        FROM article_tags
        GROUP BY tag_id
      ) x ON x.tag_id = t.id
    ";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE t.name LIKE :q OR t.slug LIKE :q ";
        $params['q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY x.use_count DESC, t.name ASC LIMIT 200";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll() ?: [];
}

$pageTitle = "Tags - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Tags</h1>
            <div class="small text-muted">Organize articles for search, filters, and recommendations.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/tags.php">
                <i class="bi bi-plus-lg"></i> New Tag
            </a>
        </div>
    </div>

    <?php if (!$hasTags): ?>
        <div class="np-card p-4 bg-white" style="max-width: 900px;">
            <h2 class="h6 mb-2">Tags tables not found</h2>
            <div class="text-muted mb-3">
                Your database is missing <code>tags</code> and/or <code>article_tags</code>.
            </div>
            <div class="small text-muted">Create the tables using the SQL I provide at the bottom, then reload this page.
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3">

            <!-- Form -->
            <div class="col-12 col-lg-4">
                <div class="np-card p-3 p-md-4 bg-white">
                    <div class="fw-semibold mb-1"><?= $edit ? 'Edit Tag' : 'Add Tag' ?></div>
                    <div class="small text-muted mb-3">Tags should be short and consistent (e.g., "Nepal", "Elections").
                    </div>

                    <form method="post" action="<?= h($ADMIN_URL) ?>/tag_save.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="name" required
                                value="<?= h((string) ($edit['name'] ?? '')) ?>" placeholder="e.g., Technology">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input class="form-control" name="slug" value="<?= h((string) ($edit['slug'] ?? '')) ?>"
                                placeholder="auto-from-name">
                            <div class="form-text">Leave blank to auto-generate from name.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-dark" type="submit">
                                <i class="bi bi-check2"></i> <?= $edit ? 'Update' : 'Create' ?>
                            </button>
                            <?php if ($edit): ?>
                                <a class="btn btn-outline-dark" href="<?= h($ADMIN_URL) ?>/tags.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="col-12 col-lg-8">
                <div class="np-card p-3 bg-white mb-2">
                    <form method="get" class="d-flex gap-2 align-items-center">
                        <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                            placeholder="Search tags...">
                        <button class="btn btn-sm btn-outline-dark" type="submit">Search</button>
                        <?php if ($q !== ''): ?>
                            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/tags.php">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="np-card p-0 bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="border-bottom">
                                <tr class="small text-muted">
                                    <th style="width:60px;">#</th>
                                    <th>Name</th>
                                    <th style="width:240px;">Slug</th>
                                    <th style="width:120px;">Used</th>
                                    <th style="width:160px;">Created</th>
                                    <th style="width:220px;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="6" class="p-4 text-muted">No tags found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $i => $r): ?>
                                        <?php $tid = (int) $r['id']; ?>
                                        <tr>
                                            <td class="text-muted"><?= $i + 1 ?></td>
                                            <td class="fw-semibold"><?= h($r['name']) ?></td>
                                            <td class="text-muted"><?= h($r['slug']) ?></td>
                                            <td><span class="badge text-bg-light border"><?= (int) $r['use_count'] ?></span></td>
                                            <td class="text-muted small">
                                                <?= h(date('M d, Y', strtotime((string) $r['created_at']))) ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1 justify-content-end flex-wrap">
                                                    <a class="btn btn-sm btn-outline-dark"
                                                        href="<?= h($ADMIN_URL) ?>/tags.php?edit=<?= $tid ?>">Edit</a>

                                                    <?php if ($isAdmin): ?>
                                                        <form method="post" action="<?= h($ADMIN_URL) ?>/tag_delete.php"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Delete this tag? It will be removed from all articles.');">
                                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                            <input type="hidden" name="id" value="<?= $tid ?>">
                                                            <input type="hidden" name="back"
                                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/tags.php')) ?>">
                                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="small text-muted mt-2">
                    Note: Tags are attached to articles via the Tags field in the article editor.
                </div>
            </div>

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>