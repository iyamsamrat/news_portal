<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/settings.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Settings
$siteName = setting_str('site_name');

// Title rule:
// - If page sets $pageTitle, use it
// - Else use Site Name from settings (fallback APP_NAME)
$pageTitle = $siteName;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $pageTitle) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- App CSS -->
    <link href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">

    <meta name="color-scheme" content="light dark">
</head>

<body class="bg-light">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>