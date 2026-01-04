<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';

auth_logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
