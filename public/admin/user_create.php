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

$errors = [];
$name = '';
$email = '';
$role = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? '');

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? 'user'));

    if ($name === '')
        $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Valid email is required.';
    if (!in_array($role, ['user', 'editor', 'admin'], true))
        $errors[] = 'Invalid role selected.';

    // Unique email
    if (!$errors) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=:e");
        $st->execute(['e' => $email]);
        if ((int) $st->fetchColumn() > 0)
            $errors[] = 'Email already exists.';
    }

    if (!$errors) {
        // Create with a temporary password
        $tempPassword = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)); // e.g. a1b2c3d4-e5f6
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $st = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, is_active, created_at)
            VALUES (:n, :e, :p, :r, 1, NOW())
        ");
        $st->execute([
            'n' => $name,
            'e' => $email,
            'p' => $hash,
            'r' => $role,
        ]);

        $_SESSION['admin_temp_password_notice'] = [
            'title' => 'User created successfully',
            'email' => $email,
            'temp_password' => $tempPassword
        ];

        header('Location: ' . $ADMIN_URL . '/users.php');
        exit;
    }
}

$pageTitle = "Create User - Admin";
require_once __DIR__ . '/_partials/admin_header.php';
require_once __DIR__ . '/_partials/admin_nav.php';
?>

<main id="main" class="container-fluid p-3 p-md-4" style="max-width: 980px;">

    <div
        class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Create User</h1>
            <div class="small text-muted">Admin creates a user and shares the temporary password.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= h($ADMIN_URL) ?>/users.php">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="np-card p-3 p-md-4 bg-white">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li>
                            <?= h($e) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?= h($name) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" name="email" value="<?= h($email) ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="editor" <?= $role === 'editor' ? 'selected' : '' ?>>Editor</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <div class="form-text">Admins can manage users. Editors can manage content.</div>
                </div>

                <div class="col-12">
                    <button class="btn btn-dark" type="submit">
                        <i class="bi bi-check2"></i> Create
                    </button>
                </div>
            </div>
        </form>
    </div>

</main>

<?php require_once __DIR__ . '/_partials/admin_footer.php'; ?>