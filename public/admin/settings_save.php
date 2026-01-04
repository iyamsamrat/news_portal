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

$incoming = $_POST['settings'] ?? [];
if (!is_array($incoming))
    $incoming = [];

function is_valid_json(string $s): bool
{
    json_decode($s, true);
    return json_last_error() === JSON_ERROR_NONE;
}

function json_stringify($value): string
{
    // Always produce valid JSON text (no escaping issues)
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Read types from DB
$types = [];
$st = $pdo->query("SELECT setting_key, type FROM settings");
foreach (($st->fetchAll() ?: []) as $r) {
    $types[(string) $r['setting_key']] = (string) ($r['type'] ?? 'string');
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("
        UPDATE settings
        SET setting_value = :v, updated_at = CURRENT_TIMESTAMP
        WHERE setting_key = :k
        LIMIT 1
    ");

    foreach ($incoming as $key => $val) {
        $key = trim((string) $key);
        if ($key === '' || !isset($types[$key]))
            continue;

        $type = $types[$key];

        // Normalize incoming
        $raw = is_string($val) ? $val : (string) $val;

        if ($type === 'bool') {
            $normalized = ($raw === '1' || $raw === 'true' || $raw === 'on') ? true : false;
            $store = json_stringify($normalized); // "true" or "false"
        } elseif ($type === 'int') {
            $store = json_stringify((int) $raw);   // 12
        } elseif ($type === 'json') {
            // user may paste JSON; must be valid JSON
            $raw = trim($raw);
            if ($raw === '')
                $raw = '{}';
            if (!is_valid_json($raw)) {
                throw new RuntimeException("Invalid JSON for setting: {$key}");
            }
            $store = $raw; // keep as-is
        } elseif ($type === 'text' || $type === 'string') {
            // store as JSON string: "News Portal"
            $store = json_stringify($raw);
        } else {
            $store = json_stringify($raw);
        }

        $update->execute(['v' => $store, 'k' => $key]);
    }

    $pdo->commit();
    $_SESSION['admin_settings_flash'] = 'Settings saved successfully.';
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['admin_settings_flash'] = 'Failed: ' . $e->getMessage();
}

header('Location: ' . $ADMIN_URL . '/settings.php');
exit;
