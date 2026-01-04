<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Use one consistent session name
    session_name('NEWS_PORTAL_SESS');

    // Make session cookie valid for BOTH /public and /admin
    $cookieParams = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/', // IMPORTANT: share across whole site
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function auth_user(): ?array
{
    auth_start();
    return $_SESSION['user'] ?? null;
}

function auth_login(array $user): void
{
    auth_start();
    session_regenerate_id(true);

    // Store only what you need
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? 'user'),
        'is_active' => (int) ($user['is_active'] ?? 1),
    ];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function auth_require_login(): void
{
    auth_start();
    if (!auth_user()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function auth_require_role(array $roles): void
{
    auth_start();
    $user = auth_user();

    if (!$user) {
        // Not logged in (or cookie not shared)
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    $role = $user['role'] ?? 'user';

    if (!in_array($role, $roles, true)) {
        // IMPORTANT: do NOT silently redirect to index; show the real issue
        http_response_code(403);
        echo "<h3>403 Forbidden</h3>";
        echo "<p>Logged in as: <b>" . htmlspecialchars($user['email'] ?? '') . "</b></p>";
        echo "<p>Your role: <b>" . htmlspecialchars($role) . "</b></p>";
        echo "<p>Required role: <b>" . htmlspecialchars(implode(', ', $roles)) . "</b></p>";
        echo "<p>Fix: update your user's role to admin/editor, then logout and login again.</p>";
        exit;
    }
}
