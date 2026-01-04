<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function setting(string $key, $default = null)
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $pdo = db();
            $rows = $pdo->query("SELECT setting_key, setting_value, type FROM settings")->fetchAll() ?: [];
            foreach ($rows as $r) {
                $k = (string) $r['setting_key'];
                $v = (string) ($r['setting_value'] ?? '');
                $t = (string) ($r['type'] ?? 'string');

                if ($t === 'bool')
                    $cache[$k] = ($v === '1');
                elseif ($t === 'int')
                    $cache[$k] = (int) $v;
                elseif ($t === 'json')
                    $cache[$k] = (trim($v) === '') ? null : json_decode($v, true);
                else
                    $cache[$k] = $v;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }

    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}
