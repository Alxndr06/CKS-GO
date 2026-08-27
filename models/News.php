<?php
require_once __DIR__ . '/../core/Model.php';

class News extends Model
{
    private static array $allowedCategories = ['general', 'shop', 'stock', 'event', 'service'];
    private static array $allowedAudiences = ['all', 'authenticated', 'staff'];

    public static function getAllowedCategories(): array
    {
        return self::$allowedCategories;
    }

    public static function getAllowedAudiences(): array
    {
        return self::$allowedAudiences;
    }

    public static function getLatestPublished(int $limit = 5, string $audience = 'all'): array
    {
        $db = self::getDB();
        $limit = max(1, min($limit, 20));
        $audience = in_array($audience, self::$allowedAudiences, true) ? $audience : 'all';

        $audienceSql = match ($audience) {
            'staff' => "n.audience IN ('all', 'authenticated', 'staff')",
            'authenticated' => "n.audience IN ('all', 'authenticated')",
            default => "n.audience = 'all'",
        };

        $stmt = $db->query("
            SELECT
                n.id,
                n.title,
                n.content,
                n.summary,
                n.category,
                n.audience,
                n.is_pinned,
                n.published_at,
                n.created_at,
                CONCAT_WS(' ', author.firstname, author.lastname) AS author_name
            FROM news n
            LEFT JOIN users author ON author.id = n.author_id
            WHERE n.is_published = 1
              AND n.published_at IS NOT NULL
              AND n.published_at <= NOW()
              AND {$audienceSql}
            ORDER BY n.is_pinned DESC, n.published_at DESC, n.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAdminStats(): array
    {
        $row = self::getDB()->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN is_published = 1 AND is_pinned = 1 THEN 1 ELSE 0 END) AS pinned
            FROM news
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'published' => (int)($row['published'] ?? 0),
            'drafts' => (int)($row['drafts'] ?? 0),
            'pinned' => (int)($row['pinned'] ?? 0),
        ];
    }

    public static function countAll(?string $q = null, ?string $state = null, ?string $category = null): int
    {
        [$where, $params] = self::buildFilters($q, $state, $category);
        $stmt = self::getDB()->prepare("SELECT COUNT(*) FROM news n WHERE {$where}");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAll(
        ?string $q = null,
        ?string $state = null,
        ?string $category = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $db = self::getDB();
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        [$where, $params] = self::buildFilters($q, $state, $category);

        $stmt = $db->prepare("
            SELECT
                n.*,
                CONCAT_WS(' ', author.firstname, author.lastname) AS author_name,
                CONCAT_WS(' ', editor.firstname, editor.lastname) AS editor_name
            FROM news n
            LEFT JOIN users author ON author.id = n.author_id
            LEFT JOIN users editor ON editor.id = n.updated_by_id
            WHERE {$where}
            ORDER BY n.is_pinned DESC, COALESCE(n.published_at, n.updated_at, n.created_at) DESC, n.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::getDB()->prepare("
            SELECT
                n.*,
                CONCAT_WS(' ', author.firstname, author.lastname) AS author_name,
                CONCAT_WS(' ', editor.firstname, editor.lastname) AS editor_name
            FROM news n
            LEFT JOIN users author ON author.id = n.author_id
            LEFT JOIN users editor ON editor.id = n.updated_by_id
            WHERE n.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);

        return $news ?: null;
    }

    public static function create(array $data): int
    {
        $clean = self::validate($data);
        $authorId = (int)($data['author_id'] ?? 0);

        if ($authorId <= 0) {
            throw new RuntimeException("L'auteur de l'actualité est invalide.");
        }

        $stmt = self::getDB()->prepare("
            INSERT INTO news (
                title, content, summary, category, audience, is_published,
                is_pinned, author_id, updated_by_id, published_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 1 THEN NOW() ELSE NULL END)
        ");
        $stmt->execute([
            $clean['title'],
            $clean['content'],
            $clean['summary'],
            $clean['category'],
            $clean['audience'],
            $clean['is_published'],
            $clean['is_pinned'],
            $authorId,
            $authorId,
            $clean['is_published'],
        ]);

        return (int)self::getDB()->lastInsertId();
    }

    public static function updateById(int $id, array $data): bool
    {
        if (!self::findById($id)) {
            throw new RuntimeException("Actualité introuvable.");
        }

        $clean = self::validate($data);
        $editorId = (int)($data['updated_by_id'] ?? 0);

        if ($editorId <= 0) {
            throw new RuntimeException("L'éditeur de l'actualité est invalide.");
        }

        $stmt = self::getDB()->prepare("
            UPDATE news
            SET title = ?,
                content = ?,
                summary = ?,
                category = ?,
                audience = ?,
                is_published = ?,
                is_pinned = ?,
                updated_by_id = ?,
                published_at = CASE
                    WHEN ? = 1 THEN COALESCE(published_at, NOW())
                    ELSE NULL
                END
            WHERE id = ?
        ");

        return $stmt->execute([
            $clean['title'],
            $clean['content'],
            $clean['summary'],
            $clean['category'],
            $clean['audience'],
            $clean['is_published'],
            $clean['is_pinned'],
            $editorId,
            $clean['is_published'],
            $id,
        ]);
    }

    public static function deleteById(int $id): bool
    {
        $stmt = self::getDB()->prepare("DELETE FROM news WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function setPublished(int $id, bool $published, int $editorId): bool
    {
        $stmt = self::getDB()->prepare("
            UPDATE news
            SET is_published = ?,
                updated_by_id = ?,
                published_at = CASE
                    WHEN ? = 1 THEN COALESCE(published_at, NOW())
                    ELSE NULL
                END
            WHERE id = ?
        ");

        return $stmt->execute([$published ? 1 : 0, $editorId, $published ? 1 : 0, $id]);
    }

    public static function duplicateById(int $id, int $authorId): int
    {
        $source = self::findById($id);
        if (!$source) {
            throw new RuntimeException("Actualité introuvable.");
        }

        return self::create([
            'title' => mb_strimwidth('Copie - ' . (string)$source['title'], 0, 160, ''),
            'content' => (string)$source['content'],
            'summary' => (string)($source['summary'] ?? ''),
            'category' => (string)($source['category'] ?? 'general'),
            'audience' => (string)($source['audience'] ?? 'all'),
            'is_published' => 0,
            'is_pinned' => 0,
            'author_id' => $authorId,
        ]);
    }

    private static function buildFilters(?string $q, ?string $state, ?string $category): array
    {
        $where = ['1'];
        $params = [];

        if ($q !== null && trim($q) !== '') {
            $like = '%' . trim($q) . '%';
            $where[] = '(n.title LIKE ? OR n.summary LIKE ? OR n.content LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        if ($state === 'published') {
            $where[] = 'n.is_published = 1';
        } elseif ($state === 'draft') {
            $where[] = 'n.is_published = 0';
        } elseif ($state === 'pinned') {
            $where[] = 'n.is_published = 1 AND n.is_pinned = 1';
        }

        if ($category !== null && in_array($category, self::$allowedCategories, true)) {
            $where[] = 'n.category = ?';
            $params[] = $category;
        }

        return [implode(' AND ', $where), $params];
    }

    private static function validate(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        $summary = trim((string)($data['summary'] ?? ''));
        $category = trim((string)($data['category'] ?? 'general'));
        $audience = trim((string)($data['audience'] ?? 'all'));

        if ($title === '' || mb_strlen($title) > 160) {
            throw new RuntimeException("Le titre est obligatoire et limité à 160 caractères.");
        }

        if ($content === '') {
            throw new RuntimeException("Le contenu de l'actualité est obligatoire.");
        }

        if (mb_strlen($content) > 12000) {
            throw new RuntimeException("Le contenu est limité à 12 000 caractères.");
        }

        if (mb_strlen($summary) > 280) {
            throw new RuntimeException("Le résumé est limité à 280 caractères.");
        }

        if (!in_array($category, self::$allowedCategories, true)) {
            $category = 'general';
        }

        if (!in_array($audience, self::$allowedAudiences, true)) {
            $audience = 'all';
        }

        return [
            'title' => $title,
            'content' => $content,
            'summary' => $summary !== '' ? $summary : null,
            'category' => $category,
            'audience' => $audience,
            'is_published' => !empty($data['is_published']) ? 1 : 0,
            'is_pinned' => !empty($data['is_pinned']) ? 1 : 0,
        ];
    }
}
