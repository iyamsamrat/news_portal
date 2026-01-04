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

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function redirect_articles(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text));
    $text = preg_replace('~[^\pL\pN]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'article';
}

function unique_slug(PDO $pdo, string $baseSlug, int $excludeId = 0): string
{
    $slug = $baseSlug;
    $i = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM articles WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params['id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = (int) $stmt->fetchColumn() > 0;

        if (!$exists)
            return $slug;

        $slug = $baseSlug . '-' . $i;
        $i++;
        if ($i > 9999) { // safety
            $slug = $baseSlug . '-' . time();
            return $slug;
        }
    }
}

function normalize_tags(string $csv): array
{
    $parts = array_map('trim', explode(',', $csv));
    $parts = array_filter($parts, fn($t) => $t !== '');
    // unique case-insensitive
    $unique = [];
    foreach ($parts as $t) {
        $k = mb_strtolower($t);
        $unique[$k] = $t;
    }
    return array_values($unique);
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
        $stmt->execute(['t' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$id = (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;

$title = trim((string) ($_POST['title'] ?? ''));
$slugInput = trim((string) ($_POST['slug'] ?? ''));
$summary = trim((string) ($_POST['summary'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$categoryId = (int) ($_POST['category_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'draft'));
$sourceName = trim((string) ($_POST['source_name'] ?? ''));
$sourceUrl = trim((string) ($_POST['source_url'] ?? ''));
$mediaType = trim((string) ($_POST['media_type'] ?? 'none'));
$mediaUrl = trim((string) ($_POST['media_url'] ?? ''));
$metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
$metaDesc = trim((string) ($_POST['meta_description'] ?? ''));
$tagsCsv = trim((string) ($_POST['tags'] ?? ''));

$isFeatured = isset($_POST['is_featured']) ? 1 : 0;
$allowComments = isset($_POST['allow_comments']) ? 1 : 0;

$allowedStatus = ['draft', 'published', 'archived'];
if (!in_array($status, $allowedStatus, true))
    $status = 'draft';

$allowedMedia = ['none', 'image', 'video', 'audio'];
if (!in_array($mediaType, $allowedMedia, true))
    $mediaType = 'none';

// Validation
$errors = [];
if ($title === '')
    $errors[] = 'Title is required.';
if ($content === '')
    $errors[] = 'Content is required.';

// Load existing article (edit mode)
$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id=:id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $errors[] = 'Article not found.';
        $isEdit = false;
        $id = 0;
    }
}

// If errors, go back to form (simple approach: redirect back with query param)
if ($errors) {
    // For now: redirect back; later we can implement flash messages
    $to = $ADMIN_URL . '/article_form.php' . ($id ? ('?id=' . $id) : '');
    redirect_articles($to);
}

// Slug
$baseSlug = $slugInput !== '' ? slugify($slugInput) : slugify($title);
$slug = unique_slug($pdo, $baseSlug, $id);

// Determine published_at logic
$publishedAt = null;

// If editing and already published and still published: keep existing published_at
if ($isEdit && $existing && ($existing['status'] ?? '') === 'published' && $status === 'published') {
    $publishedAt = $existing['published_at'] ?? date('Y-m-d H:i:s');
}

// If changing from not published -> published: set now
if ($status === 'published') {
    if (!$publishedAt) {
        $publishedAt = date('Y-m-d H:i:s');
    }
}

// If status not published: published_at should be NULL (or keep if you want history)
if ($status !== 'published') {
    $publishedAt = null;
}

// Cover upload (optional)
$coverFileName = $isEdit && $existing ? (string) ($existing['cover_image'] ?? '') : '';

$uploadDir = defined('UPLOAD_PATH') ? rtrim(UPLOAD_PATH, '/\\') : '';
$canUpload = $uploadDir !== '' && is_dir($uploadDir) && is_writable($uploadDir);

if (!empty($_FILES['cover_image']['name']) && isset($_FILES['cover_image']['tmp_name']) && $canUpload) {
    if ((int) $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = (string) $_FILES['cover_image']['tmp_name'];
        $size = (int) $_FILES['cover_image']['size'];

        // Size limit 3MB
        if ($size > 3 * 1024 * 1024) {
            // ignore upload if too big (later: flash error)
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp) : '';
            if ($finfo)
                finfo_close($finfo);

            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $newName = 'cover_' . $slug . '_' . time() . '.' . $ext;
                $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;

                if (move_uploaded_file($tmp, $dest)) {
                    $coverFileName = $newName;
                }
            }
        }
    }
}

// Save
$user = auth_user();
$createdBy = (int) ($user['id'] ?? 0);

if ($isEdit) {
    $sql = "
        UPDATE articles SET
            title=:title,
            slug=:slug,
            summary=:summary,
            content=:content,
            category_id=:category_id,
            status=:status,
            published_at=:published_at,
            is_featured=:is_featured,
            allow_comments=:allow_comments,
            cover_image=:cover_image,
            source_name=:source_name,
            source_url=:source_url,
            media_type=:media_type,
            media_url=:media_url,
            meta_title=:meta_title,
            meta_description=:meta_description,
            updated_at=NOW()
        WHERE id=:id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'slug' => $slug,
        'summary' => $summary,
        'content' => $content,
        'category_id' => $categoryId > 0 ? $categoryId : null,
        'status' => $status,
        'published_at' => $publishedAt,
        'is_featured' => $isFeatured,
        'allow_comments' => $allowComments,
        'cover_image' => $coverFileName !== '' ? $coverFileName : null,
        'source_name' => $sourceName !== '' ? $sourceName : null,
        'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        'media_type' => $mediaType,
        'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
        'meta_title' => $metaTitle !== '' ? $metaTitle : null,
        'meta_description' => $metaDesc !== '' ? $metaDesc : null,
        'id' => $id,
    ]);

    $articleId = $id;
} else {
    $sql = "
        INSERT INTO articles (
            title, slug, summary, content,
            category_id, status, published_at,
            is_featured, allow_comments,
            cover_image,
            source_name, source_url,
            media_type, media_url,
            meta_title, meta_description,
            created_by, created_at, updated_at
        ) VALUES (
            :title, :slug, :summary, :content,
            :category_id, :status, :published_at,
            :is_featured, :allow_comments,
            :cover_image,
            :source_name, :source_url,
            :media_type, :media_url,
            :meta_title, :meta_description,
            :created_by, NOW(), NOW()
        )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'slug' => $slug,
        'summary' => $summary,
        'content' => $content,
        'category_id' => $categoryId > 0 ? $categoryId : null,
        'status' => $status,
        'published_at' => $publishedAt,
        'is_featured' => $isFeatured,
        'allow_comments' => $allowComments,
        'cover_image' => $coverFileName !== '' ? $coverFileName : null,
        'source_name' => $sourceName !== '' ? $sourceName : null,
        'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        'media_type' => $mediaType,
        'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
        'meta_title' => $metaTitle !== '' ? $metaTitle : null,
        'meta_description' => $metaDesc !== '' ? $metaDesc : null,
        'created_by' => $createdBy,
    ]);

    $articleId = (int) $pdo->lastInsertId();
}

// Tags sync (optional-safe)
$hasTags = table_exists($pdo, 'tags') && table_exists($pdo, 'article_tags');

if ($hasTags) {
    $tags = normalize_tags($tagsCsv);

    $pdo->beginTransaction();
    try {
        // Clear existing
        $del = $pdo->prepare("DELETE FROM article_tags WHERE article_id=:aid");
        $del->execute(['aid' => $articleId]);

        foreach ($tags as $tagName) {
            $tagSlug = slugify($tagName);

            // Find or create
            $find = $pdo->prepare("SELECT id FROM tags WHERE slug=:slug LIMIT 1");
            $find->execute(['slug' => $tagSlug]);
            $tagId = (int) ($find->fetchColumn() ?: 0);

            if ($tagId === 0) {
                $ins = $pdo->prepare("INSERT INTO tags (name, slug, created_at) VALUES (:name, :slug, NOW())");
                $ins->execute(['name' => $tagName, 'slug' => $tagSlug]);
                $tagId = (int) $pdo->lastInsertId();
            }

            $link = $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (:aid, :tid)");
            $link->execute(['aid' => $articleId, 'tid' => $tagId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        // If tag sync fails, article is still saved.
    }
}

// Redirect to edit page (so user can keep editing)
redirect_articles($ADMIN_URL . '/article_form.php?id=' . $articleId);
