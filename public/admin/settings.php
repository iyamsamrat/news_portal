<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin']);

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// Fetch all settings, grouped
$rows = [];
try {
    $rows = $pdo->query("
        SELECT
            id,
            setting_key,
            setting_value,
            group_name,
            type,
            label,
            description,
            is_public,
            updated_at
        FROM settings
        ORDER BY group_name ASC, setting_key ASC
    ")->fetchAll() ?: [];
} catch (Throwable $e) {
    $rows = [];
}

$groups = [];
foreach ($rows as $r) {
    $g = (string) ($r['group_name'] ?? 'general');
    $groups[$g][] = $r;
}

// Flash
$flash = $_SESSION['admin_settings_flash'] ?? null;
if ($flash)
    unset($_SESSION['admin_settings_flash']);

$pageTitle = "Settings - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4" style="max-width: 1200px;">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Settings</h1>
            <div class="small text-muted">Everything configurable from CMS. Add new keys anytime.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= str_starts_with((string) $flash, 'Failed:') ? 'alert-danger' : 'alert-success' ?>">
            <?= h((string) $flash) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="np-card p-4 bg-white">
            <div class="fw-semibold mb-1">No settings found</div>
            <div class="text-muted small">
                Insert settings rows into <code>settings</code> table, then reload this page.
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3">
            <!-- Left: group list -->
            <div class="col-12 col-lg-3">
                <div class="np-card p-3 bg-white">
                    <div class="fw-semibold mb-2">Groups</div>
                    <div class="d-grid gap-2">
                        <?php foreach (array_keys($groups) as $g): ?>
                            <a class="btn btn-sm btn-outline-dark text-start" href="#group-<?= h($g) ?>">
                                <?= h(ucfirst($g)) ?> (<?= count($groups[$g]) ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-3">

                    <div class="small text-muted">
                        Tip: Add a new setting by inserting a new row into <code>settings</code>.
                        It will appear here automatically.
                    </div>
                </div>
            </div>

            <!-- Right: settings form -->
            <div class="col-12 col-lg-9">
                <form method="post" action="<?= h($ADMIN_URL) ?>/settings_save.php" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

                    <?php foreach ($groups as $g => $items): ?>
                        <section id="group-<?= h($g) ?>" class="mb-3">
                            <div class="np-card p-0 bg-white overflow-hidden">
                                <div class="p-3 border-bottom">
                                    <div class="fw-semibold"><?= h(ucfirst($g)) ?></div>
                                    <div class="small text-muted">Settings in “<?= h($g) ?>”.</div>
                                </div>

                                <div class="p-3">
                                    <div class="row g-3">
                                        <?php foreach ($items as $it): ?>
                                            <?php
                                            $key = (string) $it['setting_key'];
                                            $rawVal = (string) ($it['setting_value'] ?? '');
                                            $decoded = null;
                                            $isJson = false;

                                            if ($rawVal !== '') {
                                                $tmp = json_decode($rawVal, true);
                                                if (json_last_error() === JSON_ERROR_NONE) {
                                                    $decoded = $tmp;
                                                    $isJson = true;
                                                }
                                            }

                                            $type = (string) ($it['type'] ?? 'string');

                                            // What to show in input (human friendly)
                                            if ($type === 'bool') {
                                                $val = ($isJson ? (bool) $decoded : ($rawVal === '1'));
                                                $val = $val ? '1' : '0';
                                            } elseif ($type === 'int') {
                                                $val = (string) ($isJson ? (int) $decoded : (int) $rawVal);
                                            } elseif ($type === 'json') {
                                                // show formatted JSON for json-type only
                                                $val = $rawVal;
                                            } else {
                                                // string/text: show decoded string without quotes
                                                $val = $isJson && is_string($decoded) ? $decoded : $rawVal;
                                            }

                                            $type = (string) ($it['type'] ?? 'string');
                                            $label = (string) ($it['label'] ?? $key);
                                            $desc = (string) ($it['description'] ?? '');
                                            $isPublic = (int) ($it['is_public'] ?? 0);

                                            $name = 'settings[' . $key . ']';
                                            ?>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-start gap-3">
                                                    <div>
                                                        <div class="fw-semibold"><?= h($label) ?></div>
                                                        <div class="small text-muted">
                                                            <code><?= h($key) ?></code>
                                                            <?= $isPublic ? ' • Public' : '' ?>
                                                            <?= $desc !== '' ? ' • ' . h($desc) : '' ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-2">
                                                    <?php if ($type === 'bool'): ?>
                                                        <select class="form-select" name="<?= h($name) ?>">
                                                            <option value="1" <?= ($val === '1') ? 'selected' : '' ?>>Yes</option>
                                                            <option value="0" <?= ($val === '0') ? 'selected' : '' ?>>No</option>
                                                        </select>

                                                    <?php elseif ($type === 'int'): ?>
                                                        <input class="form-control" type="number" name="<?= h($name) ?>"
                                                            value="<?= h($val) ?>">

                                                    <?php elseif ($type === 'text'): ?>
                                                        <textarea class="form-control" rows="3"
                                                            name="<?= h($name) ?>"><?= h($val) ?></textarea>

                                                    <?php elseif ($type === 'json'): ?>
                                                        <textarea class="form-control font-monospace" rows="5" name="<?= h($name) ?>"
                                                            placeholder='{"example":true}'><?= h($val) ?></textarea>
                                                        <div class="form-text">Must be valid JSON.</div>

                                                    <?php else: ?>
                                                        <input class="form-control" type="text" name="<?= h($name) ?>"
                                                            value="<?= h($val) ?>">
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($it['updated_at'])): ?>
                                                    <div class="small text-muted mt-2">
                                                        Last updated:
                                                        <?= h(date('M d, Y H:i', strtotime((string) $it['updated_at']))) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <hr class="my-3">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="p-3 border-top d-flex justify-content-end">
                                    <button class="btn btn-dark" type="submit">
                                        <i class="bi bi-check2"></i> Save All
                                    </button>
                                </div>
                            </div>
                        </section>
                    <?php endforeach; ?>

                </form>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>