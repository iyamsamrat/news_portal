<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Article
{
    public static function findPublishedBySlug(string $slug): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT a.*,
                   c.name AS category_name, c.slug AS category_slug,
                   u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.slug = :slug AND a.status = 'published'
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT a.*,
                   c.name AS category_name, c.slug AS category_slug,
                   u.name AS author_name
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.created_by
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function incrementView(int $articleId, ?int $userId, ?string $sessionId, ?string $ipHash, ?string $userAgent): void
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO article_views (article_id, user_id, session_id, ip_hash, user_agent)
            VALUES (:article_id, :user_id, :session_id, :ip_hash, :user_agent)
        ");
        $stmt->execute([
            'article_id' => $articleId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    public static function getAvgRating(int $articleId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT
              COUNT(*) AS ratings_count,
              COALESCE(AVG(rating), 0) AS avg_rating
            FROM ratings
            WHERE article_id = :article_id
        ");
        $stmt->execute(['article_id' => $articleId]);
        $row = $stmt->fetch();
        return [
            'ratings_count' => (int) ($row['ratings_count'] ?? 0),
            'avg_rating' => (float) ($row['avg_rating'] ?? 0),
        ];
    }

    public static function getCommentsCount(int $articleId): int
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM comments
            WHERE article_id = :article_id AND status = 'approved'
        ");
        $stmt->execute(['article_id' => $articleId]);
        return (int) $stmt->fetchColumn();
    }
    public static function getTags(int $articleId): array
    {
        $pdo = db();
        $st = $pdo->prepare("
        SELECT t.id, t.name, t.slug
        FROM tags t
        INNER JOIN article_tags atg ON atg.tag_id = t.id
        WHERE atg.article_id = :aid
        ORDER BY t.name ASC
    ");
        $st->execute(['aid' => $articleId]);
        return $st->fetchAll() ?: [];
    }

}
