<?php
require_once __DIR__ . '/../core/Model.php';

class Log extends Model
{
    public static function admin(int $adminId, string $action, string $details = ''): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            INSERT INTO logs (admin_id, action, details)
            VALUES (?, ?, ?)
        ");

        return $stmt->execute([
            $adminId,
            trim($action),
            trim($details)
        ]);
    }

    public static function countAdminLogs(?string $q = null): int
    {
        $db = self::getDB();

        $sql = "
            SELECT COUNT(*)
            FROM logs l
            LEFT JOIN users u ON u.id = l.admin_id
            WHERE 1
        ";

        $params = [];

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    l.action LIKE ?
                    OR l.details LIKE ?
                    OR u.username LIKE ?
                    OR u.firstname LIKE ?
                    OR u.lastname LIKE ?
                )
            ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAdminLogs(?string $q = null, int $limit = 25, int $offset = 0): array
    {
        $db = self::getDB();

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $sql = "
            SELECT
                l.id,
                l.admin_id,
                l.action,
                l.details,
                l.created_at,
                u.username,
                u.firstname,
                u.lastname
            FROM logs l
            LEFT JOIN users u ON u.id = l.admin_id
            WHERE 1
        ";

        $params = [];

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    l.action LIKE ?
                    OR l.details LIKE ?
                    OR u.username LIKE ?
                    OR u.firstname LIKE ?
                    OR u.lastname LIKE ?
                )
            ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY l.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}