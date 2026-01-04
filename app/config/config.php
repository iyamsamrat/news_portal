<?php
declare(strict_types=1);

/**
 * Global App Config
 * Adjust BASE_URL if your folder name differs.
 */

define('APP_NAME', 'News Portal');

// If project path is: http://localhost/news_portal/public/
// then BASE_URL should be: /news_portal/public
define('BASE_URL', '/news_portal/public');

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'news_portal');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is empty
define('DB_CHARSET', 'utf8mb4');

// Sessions
define('SESSION_NAME', 'np_session');

// Uploads (public path)
define('UPLOAD_PATH', __DIR__ . '/../../public/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
// browser url

// Environment
define('APP_DEBUG', true); // set false on production
