<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/core/auth.php';

auth_start(); // Only start session here. Do NOT redirect from partial.

$pageTitle = $pageTitle ?? ('Admin - ' . APP_NAME);

// ONE place for helpers
if (!function_exists('h')) {
    function h(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= h(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">

    <style>
        .admin-shell {
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            border-right: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 700;
        }

        .admin-brand .mark {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #111;
            color: #fff;
            font-size: 12px;
            letter-spacing: .08em;
        }

        .admin-link {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .55rem .75rem;
            border-radius: 12px;
            color: rgba(0, 0, 0, .78);
            text-decoration: none;
        }

        .admin-link:hover {
            background: rgba(0, 0, 0, .04);
            color: rgba(0, 0, 0, .92);
        }

        .admin-link.active {
            background: rgba(0, 0, 0, .06);
            color: rgba(0, 0, 0, .95);
            font-weight: 600;
        }

        .admin-topbar {
            border-bottom: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
    </style>
</head>

<body class="bg-light">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>
    <div class="admin-shell d-flex">