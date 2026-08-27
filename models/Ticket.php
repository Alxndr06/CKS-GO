<?php
require_once __DIR__ . '/../core/Model.php';

class Ticket extends Model
{
    private static array $allowedStatuses = ['open', 'in_progress', 'closed'];
    private static array $allowedPriorities = ['low', 'medium', 'high'];
    private static array $allowedCategories = ['account', 'order', 'payment', 'shop', 'technical', 'other'];

    public static function getAllowedStatuses(): array
    {
        return self::$allowedStatuses;
    }

    public static function getAllowedPriorities(): array
    {
        return self::$allowedPriorities;
    }

    public static function getAllowedCategories(): array
    {
        return self::$allowedCategories;
    }

    public static function exists(int $ticketId): bool
    {
        $stmt = self::getDB()->prepare('SELECT COUNT(*) FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function getDashboardStats(): array
    {
        $row = self::getDB()->query("
            SELECT
                COUNT(*) AS total_tickets,
                SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
                SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tickets,
                SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets,
                SUM(CASE WHEN t.priority = 'high' AND t.status <> 'closed' THEN 1 ELSE 0 END) AS high_priority_active_tickets,
                SUM(CASE WHEN t.assigned_admin_id IS NULL AND t.status <> 'closed' THEN 1 ELSE 0 END) AS unassigned_tickets,
                SUM(CASE
                    WHEN t.status <> 'closed'
                     AND (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) IS NULL
                    THEN 1 ELSE 0
                END) AS awaiting_staff_tickets,
                ROUND(AVG(CASE
                    WHEN t.first_response_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at)
                    ELSE NULL
                END)) AS average_first_response_minutes
            FROM tickets t
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_tickets' => (int)($row['total_tickets'] ?? 0),
            'open_tickets' => (int)($row['open_tickets'] ?? 0),
            'in_progress_tickets' => (int)($row['in_progress_tickets'] ?? 0),
            'closed_tickets' => (int)($row['closed_tickets'] ?? 0),
            'high_priority_active_tickets' => (int)($row['high_priority_active_tickets'] ?? 0),
            'unassigned_tickets' => (int)($row['unassigned_tickets'] ?? 0),
            'awaiting_staff_tickets' => (int)($row['awaiting_staff_tickets'] ?? 0),
            'average_first_response_minutes' => (int)($row['average_first_response_minutes'] ?? 0),
        ];
    }

    public static function getAdminStats(): array
    {
        return self::getDashboardStats();
    }

    public static function findById(int $ticketId): ?array
    {
        return self::findDetailed($ticketId, null);
    }

    public static function findByIdForUser(int $ticketId, int $userId): ?array
    {
        return self::findDetailed($ticketId, $userId);
    }

    public static function countAll(
        ?string $status = null,
        ?string $priority = null,
        ?string $q = null,
        ?string $category = null,
        ?string $assignment = null,
        ?int $currentAdminId = null,
        ?string $waiting = null
    ): int {
        [$where, $params] = self::buildAdminFilters(
            $status,
            $priority,
            $q,
            $category,
            $assignment,
            $currentAdminId,
            $waiting
        );
        $stmt = self::getDB()->prepare("
            SELECT COUNT(*)
            FROM tickets t
            INNER JOIN users u ON u.id = t.user_id
            WHERE {$where}
        ");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function countForUser(int $userId, ?string $status = null, ?string $q = null): int
    {
        $where = ['t.user_id = ?'];
        $params = [$userId];

        if ($status !== null && in_array($status, self::$allowedStatuses, true)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        if ($q !== null && trim($q) !== '') {
            $where[] = 't.subject LIKE ?';
            $params[] = '%' . trim($q) . '%';
        }

        $stmt = self::getDB()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAll(
        ?string $status = null,
        ?string $priority = null,
        ?string $q = null,
        int $limit = 20,
        int $offset = 0,
        ?string $category = null,
        ?string $assignment = null,
        ?int $currentAdminId = null,
        ?string $waiting = null
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        [$where, $params] = self::buildAdminFilters(
            $status,
            $priority,
            $q,
            $category,
            $assignment,
            $currentAdminId,
            $waiting
        );

        $stmt = self::getDB()->prepare("
            SELECT
                t.*,
                u.username,
                u.firstname,
                u.lastname,
                u.email,
                assigned.username AS assigned_admin_username,
                assigned.firstname AS assigned_admin_firstname,
                assigned.lastname AS assigned_admin_lastname,
                (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS message_count,
                (SELECT LEFT(tm.message, 180) FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_preview,
                (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_admin_id,
                (SELECT tm.user_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_user_id
            FROM tickets t
            INNER JOIN users u ON u.id = t.user_id
            LEFT JOIN users assigned ON assigned.id = t.assigned_admin_id
            WHERE {$where}
            ORDER BY
                CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END,
                CASE WHEN t.priority = 'high' THEN 0 WHEN t.priority = 'medium' THEN 1 ELSE 2 END,
                t.last_message_at DESC,
                t.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllForUser(
        int $userId,
        ?string $status = null,
        ?string $q = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = ['t.user_id = ?'];
        $params = [$userId];

        if ($status !== null && in_array($status, self::$allowedStatuses, true)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        if ($q !== null && trim($q) !== '') {
            $where[] = 't.subject LIKE ?';
            $params[] = '%' . trim($q) . '%';
        }

        $stmt = self::getDB()->prepare("
            SELECT
                t.*,
                assigned.firstname AS assigned_admin_firstname,
                assigned.lastname AS assigned_admin_lastname,
                (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS message_count,
                (SELECT LEFT(tm.message, 180) FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_preview,
                (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_admin_id,
                (SELECT tm.user_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_user_id
            FROM tickets t
            LEFT JOIN users assigned ON assigned.id = t.assigned_admin_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END, t.last_message_at DESC, t.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(
        int $userId,
        string $subject,
        string $message,
        string $priority = 'medium',
        string $category = 'other'
    ): int {
        $db = self::getDB();
        $subject = trim($subject);
        $message = trim($message);
        $priority = in_array($priority, self::$allowedPriorities, true) ? $priority : 'medium';
        $category = in_array($category, self::$allowedCategories, true) ? $category : 'other';

        if ($userId <= 0) {
            throw new RuntimeException('Utilisateur invalide.');
        }
        if ($subject === '' || mb_strlen($subject) > 150) {
            throw new RuntimeException('Le sujet est obligatoire et limité à 150 caractères.');
        }
        if ($message === '' || mb_strlen($message) > 10000) {
            throw new RuntimeException('Le message est obligatoire et limité à 10 000 caractères.');
        }

        $db->beginTransaction();
        try {
            $insertTicket = $db->prepare("
                INSERT INTO tickets (user_id, subject, category, status, priority, last_message_at)
                VALUES (?, ?, ?, 'open', ?, NOW())
            ");
            $insertTicket->execute([$userId, $subject, $category, $priority]);
            $ticketId = (int)$db->lastInsertId();

            $insertMessage = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, admin_id, message)
                VALUES (?, ?, NULL, ?)
            ");
            $insertMessage->execute([$ticketId, $userId, $message]);
            $db->commit();

            return $ticketId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updateStatus(int $ticketId, string $status, ?int $adminId = null): bool
    {
        if (!in_array($status, self::$allowedStatuses, true)) {
            throw new RuntimeException('Statut de ticket invalide.');
        }

        $stmt = self::getDB()->prepare("
            UPDATE tickets
            SET status = ?,
                closed_at = CASE WHEN ? = 'closed' THEN NOW() ELSE NULL END,
                closed_by_admin_id = CASE WHEN ? = 'closed' THEN ? ELSE NULL END
            WHERE id = ?
        ");
        return $stmt->execute([$status, $status, $status, $adminId, $ticketId]);
    }

    public static function updatePriority(int $ticketId, string $priority): bool
    {
        if (!in_array($priority, self::$allowedPriorities, true)) {
            throw new RuntimeException('Priorité de ticket invalide.');
        }

        $stmt = self::getDB()->prepare('UPDATE tickets SET priority = ? WHERE id = ?');
        return $stmt->execute([$priority, $ticketId]);
    }

    public static function assign(int $ticketId, ?int $adminId): bool
    {
        if ($ticketId <= 0 || !self::exists($ticketId)) {
            throw new RuntimeException('Ticket introuvable.');
        }

        if ($adminId !== null) {
            $check = self::getDB()->prepare("
                SELECT COUNT(*)
                FROM users
                WHERE id = ? AND is_active = 1 AND is_locked = 0 AND role <> 'user'
            ");
            $check->execute([$adminId]);
            if ((int)$check->fetchColumn() === 0) {
                throw new RuntimeException("Ce membre de l'équipe ne peut pas recevoir le ticket.");
            }
        }

        $stmt = self::getDB()->prepare("
            UPDATE tickets
            SET assigned_admin_id = ?,
                status = CASE WHEN ? IS NOT NULL AND status = 'open' THEN 'in_progress' ELSE status END
            WHERE id = ?
        ");
        return $stmt->execute([$adminId, $adminId, $ticketId]);
    }

    public static function touch(int $ticketId): bool
    {
        $stmt = self::getDB()->prepare('UPDATE tickets SET last_message_at = NOW() WHERE id = ?');
        return $stmt->execute([$ticketId]);
    }

    public static function reopen(int $ticketId): bool
    {
        return self::updateStatus($ticketId, 'open');
    }

    public static function close(int $ticketId, ?int $adminId = null): bool
    {
        return self::updateStatus($ticketId, 'closed', $adminId);
    }

    public static function markInProgress(int $ticketId): bool
    {
        return self::updateStatus($ticketId, 'in_progress');
    }

    private static function findDetailed(int $ticketId, ?int $userId): ?array
    {
        $where = ['t.id = ?'];
        $params = [$ticketId];
        if ($userId !== null) {
            $where[] = 't.user_id = ?';
            $params[] = $userId;
        }

        $stmt = self::getDB()->prepare("
            SELECT
                t.*,
                u.username,
                u.firstname,
                u.lastname,
                u.email,
                assigned.username AS assigned_admin_username,
                assigned.firstname AS assigned_admin_firstname,
                assigned.lastname AS assigned_admin_lastname,
                (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS message_count,
                (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.user_id IS NOT NULL) AS user_message_count,
                (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id AND tm.admin_id IS NOT NULL) AS admin_message_count,
                (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_admin_id,
                (SELECT tm.user_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) AS last_message_user_id
            FROM tickets t
            INNER JOIN users u ON u.id = t.user_id
            LEFT JOIN users assigned ON assigned.id = t.assigned_admin_id
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ");
        $stmt->execute($params);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ticket ?: null;
    }

    private static function buildAdminFilters(
        ?string $status,
        ?string $priority,
        ?string $q,
        ?string $category,
        ?string $assignment,
        ?int $currentAdminId,
        ?string $waiting
    ): array {
        $where = ['1'];
        $params = [];

        if ($status !== null && in_array($status, self::$allowedStatuses, true)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($priority !== null && in_array($priority, self::$allowedPriorities, true)) {
            $where[] = 't.priority = ?';
            $params[] = $priority;
        }
        if ($category !== null && in_array($category, self::$allowedCategories, true)) {
            $where[] = 't.category = ?';
            $params[] = $category;
        }
        if ($q !== null && trim($q) !== '') {
            $like = '%' . trim($q) . '%';
            $where[] = '(t.subject LIKE ? OR u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($assignment === 'mine' && $currentAdminId !== null && $currentAdminId > 0) {
            $where[] = 't.assigned_admin_id = ?';
            $params[] = $currentAdminId;
        } elseif ($assignment === 'unassigned') {
            $where[] = 't.assigned_admin_id IS NULL';
        }
        if ($waiting === 'staff') {
            $where[] = "t.status <> 'closed' AND (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) IS NULL";
        } elseif ($waiting === 'user') {
            $where[] = "t.status <> 'closed' AND (SELECT tm.admin_id FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY tm.id DESC LIMIT 1) IS NOT NULL";
        }

        return [implode(' AND ', $where), $params];
    }
}
