<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Rating
{
    public static function getUserRating(int $userId, int $articleId): ?int
    {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT rating FROM ratings WHERE user_id = :u AND article_id = :a LIMIT 1");
        $stmt->execute(['u' => $userId, 'a' => $articleId]);
        $r = $stmt->fetchColumn();
        return $r !== false ? (int) $r : null;
    }

    public static function upsert(int $userId, int $articleId, int $rating): void
    {
        $rating = max(1, min(5, $rating));

        $pdo = db();
        // Works on MySQL: primary key (user_id, article_id)
        $stmt = $pdo->prepare("
            INSERT INTO ratings (user_id, article_id, rating)
            VALUES (:u, :a, :r)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute(['u' => $userId, 'a' => $articleId, 'r' => $rating]);
    }

    public static function remove(int $userId, int $articleId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM ratings WHERE user_id = :u AND article_id = :a");
        $stmt->execute(['u' => $userId, 'a' => $articleId]);
    }

    public static function listForUser(int $userId, int $limit = 60): array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT
                a.id, a.title, a.slug, a.summary, a.cover_image, a.published_at,
                c.name AS category_name, c.slug AS category_slug,
                r.rating, r.updated_at
            FROM ratings r
            INNER JOIN articles a ON a.id = r.article_id
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE r.user_id = :u AND a.status = 'published'
            ORDER BY r.updated_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
