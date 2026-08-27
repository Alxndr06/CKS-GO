<?php
require_once __DIR__ . '/../core/Model.php';

class Log extends Model
{
    private static function hasRequestContextColumns(): bool
    {
        static $available = null;

        if ($available === null) {
            $stmt = self::getDB()->query("SHOW COLUMNS FROM logs LIKE 'request_id'");
            $available = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $available;
    }

    public static function admin(int $adminId, string $action, string $details = ''): bool
    {
        $db = self::getDB();

        if (!self::hasRequestContextColumns()) {
            $stmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");

            return $stmt->execute([$adminId, trim($action), trim($details)]);
        }

        $stmt = $db->prepare("
            INSERT INTO logs (admin_id, action, details, ip_address, user_agent, request_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $ipAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = null;
        }

        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $requestId = trim((string)($_SERVER['CKSGO_REQUEST_ID'] ?? ''));

        return $stmt->execute([
            $adminId,
            trim($action),
            trim($details),
            $ipAddress,
            $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
            preg_match('/^[a-f0-9]{32}$/', $requestId) === 1 ? $requestId : null,
        ]);
    }

    private static function normalizeFilters(array|string|null $filters): array
    {
        if (is_string($filters) || $filters === null) {
            return ['q' => trim((string)$filters)];
        }

        return $filters;
    }

    private static function buildFilterSql(array|string|null $filters, array &$params): string
    {
        $filters = self::normalizeFilters($filters);
        $where = ['1 = 1'];
        $q = trim((string)($filters['q'] ?? ''));
        $category = trim((string)($filters['category'] ?? ''));
        $outcome = trim((string)($filters['outcome'] ?? ''));
        $actorId = (int)($filters['actor_id'] ?? 0);
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(l.action LIKE ? OR l.details LIKE ? OR u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $categoryPatterns = [
            'users' => ['%user%', '%registration%', '%permission%', '%password%'],
            'catalog' => ['%product%', '%variant%', '%category%', '%inventory%', '%stock%', '%shop%'],
            'billing' => ['%payment%', '%order%', '%invoice%', '%refund%', '%billing%', '%balance%', '%charge%'],
            'support' => ['%ticket%', '%alert%', '%support%'],
            'security' => ['%security%', '%login%', '%lock%', '%ban%', '%access%'],
            'settings' => ['%setting%', '%maintenance%', '%news%'],
        ];

        if (isset($categoryPatterns[$category])) {
            $where[] = '(' . implode(' OR ', array_fill(0, count($categoryPatterns[$category]), 'l.action LIKE ?')) . ')';
            array_push($params, ...$categoryPatterns[$category]);
        }

        if ($outcome === 'failure') {
            $where[] = "(l.action LIKE '%failed%' OR l.action LIKE '%error%' OR l.action LIKE '%rejected%')";
        } elseif ($outcome === 'success') {
            $where[] = "(l.action NOT LIKE '%failed%' AND l.action NOT LIKE '%error%' AND l.action NOT LIKE '%rejected%')";
        }

        if ($actorId > 0) {
            $where[] = 'l.admin_id = ?';
            $params[] = $actorId;
        }

        if ($dateFrom !== '') {
            $where[] = 'l.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $where[] = 'l.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $dateTo;
        }

        return implode(' AND ', $where);
    }

    public static function countAdminLogs(array|string|null $filters = null): int
    {
        $db = self::getDB();
        $params = [];
        $whereSql = self::buildFilterSql($filters, $params);

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM logs l
            LEFT JOIN users u ON u.id = l.admin_id
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAdminLogs(array|string|null $filters = null, int $limit = 25, int $offset = 0): array
    {
        $db = self::getDB();

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $params = [];
        $whereSql = self::buildFilterSql($filters, $params);

        $requestContextSelect = self::hasRequestContextColumns()
            ? 'l.ip_address, l.user_agent, l.request_id,'
            : 'NULL AS ip_address, NULL AS user_agent, NULL AS request_id,';

        $sql = "
            SELECT
                l.id,
                l.admin_id,
                l.action,
                l.details,
                {$requestContextSelect}
                l.created_at,
                u.username,
                u.firstname,
                u.lastname
            FROM logs l
            LEFT JOIN users u ON u.id = l.admin_id
            WHERE {$whereSql}
        ";

        $sql .= " ORDER BY l.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAdminLogStats(array|string|null $filters = null): array
    {
        $params = [];
        $whereSql = self::buildFilterSql($filters, $params);
        $stmt = self::getDB()->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS last_24h,
                SUM(CASE WHEN l.action LIKE '%failed%' OR l.action LIKE '%error%' OR l.action LIKE '%rejected%' THEN 1 ELSE 0 END) AS failures,
                COUNT(DISTINCT l.admin_id) AS actors
            FROM logs l
            LEFT JOIN users u ON u.id = l.admin_id
            WHERE {$whereSql}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'last_24h' => (int)($row['last_24h'] ?? 0),
            'failures' => (int)($row['failures'] ?? 0),
            'actors' => (int)($row['actors'] ?? 0),
        ];
    }

    public static function getAdminLogActors(): array
    {
        $stmt = self::getDB()->query('
            SELECT DISTINCT u.id, u.username, u.firstname, u.lastname
            FROM logs l
            INNER JOIN users u ON u.id = l.admin_id
            ORDER BY u.firstname ASC, u.lastname ASC, u.username ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
