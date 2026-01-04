<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Bookmark
{
    public static function isBookmarked(int $userId, int $articleId): bool
    {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT 1 FROM bookmarks WHERE user_id = :u AND article_id = :a LIMIT 1");
        $stmt->execute(['u' => $userId, 'a' => $articleId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function add(int $userId, int $articleId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT IGNORE INTO bookmarks (user_id, article_id) VALUES (:u, :a)");
        $stmt->execute(['u' => $userId, 'a' => $articleId]);
    }

    public static function remove(int $userId, int $articleId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE user_id = :u AND article_id = :a");
        $stmt->execute(['u' => $userId, 'a' => $articleId]);
    }

    public static function toggle(int $userId, int $articleId): bool
    {
        if (self::isBookmarked($userId, $articleId)) {
            self::remove($userId, $articleId);
            return false;
        }
        self::add($userId, $articleId);
        return true;
    }

    public static function listForUser(int $userId, int $limit = 50): array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT
                a.id, a.title, a.slug, a.summary, a.cover_image, a.published_at,
                c.name AS category_name, c.slug AS category_slug,
                b.created_at AS bookmarked_at
            FROM bookmarks b
            INNER JOIN articles a ON a.id = b.article_id
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE b.user_id = :u AND a.status = 'published'
            ORDER BY b.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
