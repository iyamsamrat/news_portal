<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);
$user = auth_user();

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text));
    $text = preg_replace('~[^\pL\pN]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'category';
}

// edit mode
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM categories WHERE id=:id LIMIT 1");
    $st->execute(['id' => $editId]);
    $edit = $st->fetch() ?: null;
}

// list (includes article count)
$rows = $pdo->query("
    SELECT
      c.id, c.name, c.slug, c.sort_order, c.is_active, c.created_at,
      COALESCE(x.article_count, 0) AS article_count
    FROM categories c
    LEFT JOIN (
      SELECT category_id, COUNT(*) AS article_count
      FROM articles
      GROUP BY category_id
    ) x ON x.category_id = c.id
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll() ?: [];

$pageTitle = "Categories - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Categories</h1>
            <div class="small text-muted">Control site navigation and article classification from CMS.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/categories.php">
                <i class="bi bi-plus-lg"></i> New Category
            </a>
        </div>
    </div>

    <div class="row g-3">

        <!-- Form -->
        <div class="col-12 col-lg-4">
            <div class="np-card p-3 p-md-4 bg-white">
                <div class="fw-semibold mb-1"><?= $edit ? 'Edit Category' : 'Add Category' ?></div>
                <div class="small text-muted mb-3">Keep names short. Slugs are used in URLs.</div>

                <form method="post" action="<?= h($ADMIN_URL) ?>/category_save.php" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input class="form-control" name="name" required
                            value="<?= h((string) ($edit['name'] ?? '')) ?>" placeholder="e.g., Technology">
                    </div>

                    <!-- NEW: Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                            placeholder="Optional short description..."><?= h((string) ($edit['description'] ?? '')) ?></textarea>
                        <div class="form-text">Optional. Useful for SEO and category landing pages later.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input class="form-control" name="slug" value="<?= h((string) ($edit['slug'] ?? '')) ?>"
                            placeholder="<?= h($edit ? (string) $edit['slug'] : slugify('technology')) ?>">
                        <div class="form-text">Leave blank to auto-generate from name.</div>
                    </div>

                    <!-- NEW: SEO fields -->
                    <div class="mb-3">
                        <label class="form-label">SEO Title</label>
                        <input class="form-control" name="meta_title"
                            value="<?= h((string) ($edit['meta_title'] ?? '')) ?>" placeholder="Optional meta title">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SEO Description</label>
                        <textarea class="form-control" name="meta_description" rows="2"
                            placeholder="Optional meta description"><?= h((string) ($edit['meta_description'] ?? '')) ?></textarea>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Sort Order</label>
                            <input class="form-control" type="number" name="sort_order" min="0"
                                value="<?= h((string) ($edit['sort_order'] ?? '0')) ?>">
                            <div class="form-text">Lower shows first.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Active</label>
                            <select class="form-select" name="is_active">
                                <?php $active = (int) ($edit['is_active'] ?? 1); ?>
                                <option value="1" <?= $active === 1 ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= $active === 0 ? 'selected' : '' ?>>No</option>
                            </select>
                            <div class="form-text">Hide from navbar if No.</div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button class="btn btn-dark" type="submit">
                            <i class="bi bi-check2"></i> <?= $edit ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a class="btn btn-outline-dark" href="<?= h($ADMIN_URL) ?>/categories.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="col-12 col-lg-8">
            <div class="np-card p-0 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="border-bottom">
                            <tr class="small text-muted">
                                <th style="width:60px;">#</th>
                                <th>Name</th>
                                <th style="width:200px;">Slug</th>
                                <th style="width:110px;">Articles</th>
                                <th style="width:110px;">Order</th>
                                <th style="width:110px;">Active</th>
                                <th style="width:150px;">Created</th>
                                <th style="width:260px;" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="8" class="p-4 text-muted">No categories yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $r): ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>
                                        <td class="fw-semibold"><?= h($r['name']) ?></td>
                                        <td class="text-muted"><?= h($r['slug']) ?></td>

                                        <td>
                                            <span
                                                class="badge text-bg-light border"><?= (int) ($r['article_count'] ?? 0) ?></span>
                                        </td>

                                        <td><?= (int) $r['sort_order'] ?></td>

                                        <td>
                                            <?php if ((int) $r['is_active'] === 1): ?>
                                                <span class="badge text-bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-muted small">
                                            <?= h(date('M d, Y', strtotime((string) $r['created_at']))) ?>
                                        </td>

                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1 justify-content-end flex-wrap">

                                                <a class="btn btn-sm btn-outline-dark"
                                                    href="<?= h($ADMIN_URL) ?>/categories.php?edit=<?= (int) $r['id'] ?>">
                                                    Edit
                                                </a>

                                                <!-- Toggle Active -->
                                                <form method="post" action="<?= h($ADMIN_URL) ?>/category_toggle.php"
                                                    class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <input type="hidden" name="back"
                                                        value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/categories.php')) ?>">
                                                    <button class="btn btn-sm btn-outline-dark" type="submit">
                                                        <?= ((int) $r['is_active'] === 1) ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>

                                                <!-- Delete (admin only, protected) -->
                                                <?php if (($_SESSION['user']['role'] ?? ($user['role'] ?? 'user')) === 'admin'): ?>
                                                    <form method="post" action="<?= h($ADMIN_URL) ?>/category_delete.php"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Delete this category? If it is used by articles, it will be disabled instead.');">
                                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                        <input type="hidden" name="back"
                                                            value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/categories.php')) ?>">
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
                Tip: Only <strong>Active</strong> categories show in your public navbar category bar.
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>