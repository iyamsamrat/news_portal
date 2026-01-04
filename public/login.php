<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/security.php';
require_once __DIR__ . '/../app/core/auth.php';

auth_start();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? '');

    $email = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Enter a valid email.';
    if ($pass === '')
        $errors[] = 'Enter your password.';

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active'] || !password_verify($pass, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            auth_login($user);
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

$pageTitle = "Login - " . APP_NAME;
require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main" class="container my-4" style="max-width: 520px;">
    <div class="np-card p-4">
        <h1 class="h4 mb-1">Login</h1>
        <div class="text-muted small mb-3">Access your bookmarks, comments, and ratings.</div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required>
            </div>

            <button class="btn btn-dark w-100" type="submit">Login</button>

            <div class="text-center small mt-3">
                New here? <a href="<?= htmlspecialchars(BASE_URL) ?>/register.php">Create account</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>