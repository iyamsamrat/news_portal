<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/security.php';

auth_start();
auth_require_role(['admin', 'editor']);
csrf_verify($_POST['csrf_token'] ?? '');

$pdo = db();
$ADMIN_URL = rtrim(BASE_URL, '/') . '/admin';

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text));
    $text = preg_replace('~[^\pL\pN]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'tag';
}

function unique_slug(PDO $pdo, string $baseSlug, int $excludeId = 0): string
{
    $slug = $baseSlug;
    $i = 2;
    while (true) {
        $sql = "SELECT COUNT(*) FROM tags WHERE slug=:slug";
        $params = ['slug' => $slug];
        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params['id'] = $excludeId;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        if ((int) $st->fetchColumn() === 0)
            return $slug;

        $slug = $baseSlug . '-' . $i;
        $i++;
        if ($i > 9999)
            return $baseSlug . '-' . time();
    }
}

$id = (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;

$name = trim((string) ($_POST['name'] ?? ''));
$slugInput = trim((string) ($_POST['slug'] ?? ''));

if ($name === '') {
    header('Location: ' . $ADMIN_URL . '/tags.php');
    exit;
}

$baseSlug = $slugInput !== '' ? slugify($slugInput) : slugify($name);
$slug = unique_slug($pdo, $baseSlug, $id);

if ($isEdit) {
    $st = $pdo->prepare("UPDATE tags SET name=:name, slug=:slug WHERE id=:id LIMIT 1");
    $st->execute(['name' => $name, 'slug' => $slug, 'id' => $id]);
} else {
    $st = $pdo->prepare("INSERT INTO tags (name, slug, created_at) VALUES (:name, :slug, NOW())");
    $st->execute(['name' => $name, 'slug' => $slug]);
}

header('Location: ' . $ADMIN_URL . '/tags.php');
exit;
