<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Read a setting from DB.
 * - Your DB stores JSON in setting_value, so we decode automatically.
 * - Returns:
 *   - array/bool/int/string (decoded JSON) when JSON is valid
 *   - raw string fallback when JSON invalid
 */
function setting(string $key, $default = null)
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $pdo = db();
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() ?: [];
            foreach ($rows as $r) {
                $k = (string) $r['setting_key'];
                $raw = (string) ($r['setting_value'] ?? '');

                if ($raw === '') {
                    $cache[$k] = null;
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cache[$k] = $decoded; // could be string/int/bool/array
                } else {
                    // fallback
                    $cache[$k] = $raw;
                }
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }

    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/** Convenience: force string output */
function setting_str(string $key, string $default = ''): string
{
    $v = setting($key, $default);
    if (is_string($v))
        return $v;
    if (is_bool($v))
        return $v ? '1' : '0';
    if (is_int($v) || is_float($v))
        return (string) $v;
    return $default;
}

/** Convenience: force int output */
function setting_int(string $key, int $default = 0): int
{
    $v = setting($key, $default);
    if (is_int($v))
        return $v;
    if (is_numeric($v))
        return (int) $v;
    return $default;
}

/** Convenience: force bool output */
function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key, $default);
    if (is_bool($v))
        return $v;
    if (is_int($v))
        return $v === 1;
    if (is_string($v))
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    return $default;
}
