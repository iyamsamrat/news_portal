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
    return $text !== '' ? $text : 'category';
}

function unique_slug(PDO $pdo, string $baseSlug, int $excludeId = 0): string
{
    $slug = $baseSlug;
    $i = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM categories WHERE slug=:slug";
        $params = ['slug' => $slug];

        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params['id'] = $excludeId;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $exists = (int) $st->fetchColumn() > 0;

        if (!$exists)
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
$description = trim((string) ($_POST['description'] ?? ''));
$slugInput = trim((string) ($_POST['slug'] ?? ''));
$metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
$metaDesc = trim((string) ($_POST['meta_description'] ?? ''));
$sortOrder = (int) ($_POST['sort_order'] ?? 0);
$isActive = (int) ($_POST['is_active'] ?? 1);
$isActive = $isActive === 1 ? 1 : 0;

if ($name === '') {
    header('Location: ' . $ADMIN_URL . '/categories.php');
    exit;
}

$baseSlug = $slugInput !== '' ? slugify($slugInput) : slugify($name);
$slug = unique_slug($pdo, $baseSlug, $id);

if ($isEdit) {
    $st = $pdo->prepare("
        UPDATE categories
        SET
          name=:name,
          description=:description,
          slug=:slug,
          meta_title=:meta_title,
          meta_description=:meta_description,
          sort_order=:sort_order,
          is_active=:is_active
        WHERE id=:id
        LIMIT 1
    ");
    $st->execute([
        'name' => $name,
        'description' => ($description !== '' ? $description : null),
        'slug' => $slug,
        'meta_title' => ($metaTitle !== '' ? $metaTitle : null),
        'meta_description' => ($metaDesc !== '' ? $metaDesc : null),
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
        'id' => $id
    ]);
} else {
    $st = $pdo->prepare("
        INSERT INTO categories
          (name, description, slug, meta_title, meta_description, sort_order, is_active, created_at)
        VALUES
          (:name, :description, :slug, :meta_title, :meta_description, :sort_order, :is_active, NOW())
    ");
    $st->execute([
        'name' => $name,
        'description' => ($description !== '' ? $description : null),
        'slug' => $slug,
        'meta_title' => ($metaTitle !== '' ? $metaTitle : null),
        'meta_description' => ($metaDesc !== '' ? $metaDesc : null),
        'sort_order' => $sortOrder,
        'is_active' => $isActive
    ]);
}

header('Location: ' . $ADMIN_URL . '/categories.php');
exit;
