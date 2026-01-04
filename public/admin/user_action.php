<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin']);
csrf_verify($_POST['csrf_token'] ?? '');

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

$id = (int) ($_POST['id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$back = trim((string) ($_POST['back'] ?? ($ADMIN_URL . '/users.php')));

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

function active_admin_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
}

function get_user(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id=:id LIMIT 1");
    $st->execute(['id' => $id]);
    $u = $st->fetch();
    return $u ?: null;
}

$target = get_user($pdo, $id);
if (!$target) {
    header('Location: ' . $back);
    exit;
}

$currentRole = (string) ($target['role'] ?? 'user');
$currentActive = (int) ($target['is_active'] ?? 1);

if ($action === 'toggle_active') {
    $newActive = $currentActive === 1 ? 0 : 1;

    // Safety: cannot deactivate last admin
    if ($currentRole === 'admin' && $currentActive === 1 && $newActive === 0) {
        if (active_admin_count($pdo) <= 1) {
            header('Location: ' . $back);
            exit;
        }
    }

    $st = $pdo->prepare("UPDATE users SET is_active=:a WHERE id=:id LIMIT 1");
    $st->execute(['a' => $newActive, 'id' => $id]);

} elseif ($action === 'set_role') {
    $newRole = trim((string) ($_POST['role'] ?? ''));
    $allowed = ['user', 'editor', 'admin'];

    if (!in_array($newRole, $allowed, true)) {
        header('Location: ' . $back);
        exit;
    }

    // Safety: cannot demote last active admin
    if ($currentRole === 'admin' && $newRole !== 'admin' && $currentActive === 1) {
        if (active_admin_count($pdo) <= 1) {
            header('Location: ' . $back);
            exit;
        }
    }

    $st = $pdo->prepare("UPDATE users SET role=:r WHERE id=:id LIMIT 1");
    $st->execute(['r' => $newRole, 'id' => $id]);

} elseif ($action === 'reset_password') {

    // Safety: allow resetting password for anyone
    $tempPassword = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2));
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $st = $pdo->prepare("UPDATE users SET password_hash=:p WHERE id=:id LIMIT 1");
    $st->execute(['p' => $hash, 'id' => $id]);

    // Flash once in users.php
    $_SESSION['admin_temp_password_notice'] = [
        'title' => 'Temporary password generated',
        'email' => (string) ($target['email'] ?? ''),
        'temp_password' => $tempPassword
    ];
}

header('Location: ' . $back);
exit;
