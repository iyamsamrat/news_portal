<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

final class Comment
{
    public static function defaultStatus(): string
    {
        // Default fallback: pending
        $pdo = db();
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'comment_policy' LIMIT 1");
            $stmt->execute();
            $json = $stmt->fetchColumn();
            if (!$json)
                return 'pending';

            $data = json_decode((string) $json, true);
            $status = $data['default_status'] ?? 'pending';

            if (in_array($status, ['pending', 'approved', 'hidden'], true)) {
                return $status;
            }
        } catch (Throwable $e) {
            // ignore
        }
        return 'pending';
    }

    public static function create(int $articleId, int $userId, string $comment): void
    {
        $comment = trim($comment);
        if ($comment === '')
            return;

        // basic length limits
        if (mb_strlen($comment) > 1000) {
            $comment = mb_substr($comment, 0, 1000);
        }

        $status = self::defaultStatus();
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO comments (article_id, user_id, comment, status)
            VALUES (:a, :u, :c, :s)
        ");
        $stmt->execute([
            'a' => $articleId,
            'u' => $userId,
            'c' => $comment,
            's' => $status,
        ]);
    }

    public static function listApprovedForArticle(int $articleId, int $limit = 50): array
    {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT
              c.id, c.comment, c.created_at,
              u.name AS user_name
            FROM comments c
            INNER JOIN users u ON u.id = c.user_id
            WHERE c.article_id = :a AND c.status = 'approved'
            ORDER BY c.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':a', $articleId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listForModeration(string $status, int $limit = 100): array
    {
        if (!in_array($status, ['pending', 'approved', 'hidden'], true)) {
            $status = 'pending';
        }

        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT
              c.id, c.comment, c.status, c.created_at,
              a.title AS article_title, a.slug AS article_slug,
              u.name AS user_name, u.email AS user_email
            FROM comments c
            INNER JOIN articles a ON a.id = c.article_id
            INNER JOIN users u ON u.id = c.user_id
            WHERE c.status = :s
            ORDER BY c.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':s', $status, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function setStatus(int $commentId, string $status): void
    {
        if (!in_array($status, ['pending', 'approved', 'hidden'], true))
            return;

        $pdo = db();
        $stmt = $pdo->prepare("UPDATE comments SET status = :s WHERE id = :id");
        $stmt->execute(['s' => $status, 'id' => $commentId]);
    }

    public static function delete(int $commentId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = :id");
        $stmt->execute(['id' => $commentId]);
    }
}
