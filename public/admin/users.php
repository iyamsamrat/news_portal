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

$q = trim((string) ($_GET['q'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$active = trim((string) ($_GET['active'] ?? '')); // '1','0',''
$page = max(1, (int) ($_GET['page'] ?? 1));

$allowedRoles = ['user', 'editor', 'admin'];
if ($role !== '' && !in_array($role, $allowedRoles, true))
    $role = '';
if ($active !== '' && $active !== '1' && $active !== '0')
    $active = '';

$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];

if ($q !== '') {
    $where .= " AND (u.name LIKE :q OR u.email LIKE :q) ";
    $params['q'] = '%' . $q . '%';
}
if ($role !== '') {
    $where .= " AND u.role = :role ";
    $params['role'] = $role;
}
if ($active !== '') {
    $where .= " AND u.is_active = :active ";
    $params['active'] = (int) $active;
}

// Count
$st = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

// Data
$sql = "
  SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at
  FROM users u
  $where
  ORDER BY u.created_at DESC
  LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v)
    $st->bindValue(':' . $k, $v);
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll() ?: [];

// Admin count for safety banner
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();

// Flash: show temp password after reset/create (once)
$tempNotice = $_SESSION['admin_temp_password_notice'] ?? null;
if ($tempNotice)
    unset($_SESSION['admin_temp_password_notice']);

function build_url(array $overrides = []): string
{
    $base = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '')
            unset($base[$k]);
        else
            $base[$k] = $v;
    }
    return BASE_URL . '/admin/users.php?' . http_build_query($base);
}

$pageTitle = "Users - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Users & Roles</h1>
            <div class="small text-muted">Manage accounts, roles, and access to the CMS.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-dark" href="<?= h($ADMIN_URL) ?>/user_create.php">
                <i class="bi bi-person-plus"></i> Create User
            </a>
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($tempNotice): ?>
        <div class="alert alert-success">
            <div class="fw-semibold mb-1"><?= h($tempNotice['title'] ?? 'Temporary password created') ?></div>
            <div class="small">
                User: <strong><?= h($tempNotice['email'] ?? '') ?></strong><br>
                Temporary Password: <strong><?= h($tempNotice['temp_password'] ?? '') ?></strong>
            </div>
            <div class="small text-muted mt-2">Copy it now. This message will not be shown again.</div>
        </div>
    <?php endif; ?>

    <?php if ($adminCount <= 1): ?>
        <div class="alert alert-warning">
            <strong>Safety rule:</strong> Only 1 active admin exists. You cannot deactivate or demote the last admin.
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="np-card p-3 bg-white mb-3">
        <form class="row g-2 align-items-end" method="get" action="">
            <div class="col-12 col-lg-6">
                <label class="form-label small text-muted">Search</label>
                <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search name or email...">
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label small text-muted">Role</label>
                <select class="form-select" name="role">
                    <option value="">All</option>
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="editor" <?= $role === 'editor' ? 'selected' : '' ?>>Editor</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label small text-muted">Active</label>
                <select class="form-select" name="active">
                    <option value="">All</option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <div class="col-12 col-lg-1 d-grid">
                <button class="btn btn-dark" type="submit">Go</button>
            </div>
        </form>
    </div>

    <div class="small text-muted mb-2"><?= (int) $total ?> user(s)</div>

    <!-- Table -->
    <div class="np-card p-0 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="border-bottom">
                    <tr class="small text-muted">
                        <th style="width:60px;">#</th>
                        <th>User</th>
                        <th style="width:160px;">Role</th>
                        <th style="width:130px;">Active</th>
                        <th style="width:170px;">Created</th>
                        <th style="width:420px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="p-4 text-muted">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $u): ?>
                            <?php
                            $uid = (int) $u['id'];
                            $uRole = (string) ($u['role'] ?? 'user');
                            $uActive = (int) ($u['is_active'] ?? 1);

                            $roleBadge = 'text-bg-light border';
                            if ($uRole === 'admin')
                                $roleBadge = 'text-bg-dark';
                            elseif ($uRole === 'editor')
                                $roleBadge = 'text-bg-primary';
                            ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>

                                <td>
                                    <div class="fw-semibold"><?= h((string) $u['name']) ?></div>
                                    <div class="small text-muted"><?= h((string) $u['email']) ?></div>
                                </td>

                                <td><span class="badge <?= h($roleBadge) ?>"><?= h(ucfirst($uRole)) ?></span></td>

                                <td>
                                    <?php if ($uActive === 1): ?>
                                        <span class="badge text-bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-muted small">
                                    <?= h(date('M d, Y H:i', strtotime((string) $u['created_at']))) ?>
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                                        <a class="btn btn-sm btn-outline-dark"
                                            href="<?= h($ADMIN_URL) ?>/user_view.php?id=<?= $uid ?>">
                                            View
                                        </a>

                                        <!-- Change role -->
                                        <form method="post" action="<?= h($ADMIN_URL) ?>/user_action.php"
                                            class="d-inline-flex gap-1">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="set_role">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/users.php')) ?>">

                                            <select class="form-select form-select-sm" name="role" style="width: 140px;">
                                                <option value="user" <?= $uRole === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="editor" <?= $uRole === 'editor' ? 'selected' : '' ?>>Editor</option>
                                                <option value="admin" <?= $uRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>

                                            <button class="btn btn-sm btn-outline-dark" type="submit">Update Role</button>
                                        </form>

                                        <!-- Toggle active -->
                                        <form method="post" action="<?= h($ADMIN_URL) ?>/user_action.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/users.php')) ?>">
                                            <?php if ($uActive === 1): ?>
                                                <button class="btn btn-sm btn-outline-dark" type="submit">Deactivate</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-dark" type="submit">Activate</button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Reset password -->
                                        <form method="post" action="<?= h($ADMIN_URL) ?>/user_action.php" class="d-inline"
                                            onsubmit="return confirm('Generate a new temporary password for this user?');">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="back"
                                                value="<?= h($_SERVER['REQUEST_URI'] ?? ($ADMIN_URL . '/users.php')) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Reset Password</button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Users pagination">
            <ul class="pagination pagination-sm flex-wrap">
                <?php $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1); ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(build_url(['page' => $prev])) ?>">Prev</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(build_url(['page' => $next])) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>