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
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>

<body class="bg-light">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>
    <div class="admin-shell d-flex">