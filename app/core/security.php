<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function csrf_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function csrf_token(): string
{
    csrf_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): void
{
    csrf_start();
    $valid = isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}
