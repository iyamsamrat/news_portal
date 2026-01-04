<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/security.php';
require_once __DIR__ . '/../app/core/auth.php';

auth_start();

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? '');

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($name === '' || mb_strlen($name) < 2)
        $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Enter a valid email.';
    if (mb_strlen($pass) < 6)
        $errors[] = 'Password must be at least 6 characters.';

    if (!$errors) {
        $pdo = db();

        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists. Please login.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, 'user')");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'hash' => $hash
            ]);

            // Auto-login
            $id = (int) $pdo->lastInsertId();
            auth_login(['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'user']);

            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

$pageTitle = "Register - " . APP_NAME;
require_once __DIR__ . '/../app/views/partials/header.php';
require_once __DIR__ . '/../app/views/partials/navbar.php';
?>

<main id="main" class="container my-4" style="max-width: 520px;">
    <div class="np-card p-4">
        <h1 class="h4 mb-1">Create account</h1>
        <div class="text-muted small mb-3">Register to bookmark, comment, and rate articles.</div>

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
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" minlength="6" required>
                <div class="form-text">Minimum 6 characters.</div>
            </div>

            <button class="btn btn-dark w-100" type="submit">Register</button>

            <div class="text-center small mt-3">
                Already have an account? <a href="<?= htmlspecialchars(BASE_URL) ?>/login.php">Login</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../app/views/partials/footer.php'; ?>