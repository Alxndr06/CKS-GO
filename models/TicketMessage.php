<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Ticket.php';

class TicketMessage extends Model
{
    public static function getByTicketId(int $ticketId): array
    {
        $stmt = self::getDB()->prepare("
            SELECT
                tm.*,
                user_author.username AS user_username,
                user_author.firstname AS user_firstname,
                user_author.lastname AS user_lastname,
                admin_author.username AS admin_username,
                admin_author.firstname AS admin_firstname,
                admin_author.lastname AS admin_lastname
            FROM ticket_messages tm
            LEFT JOIN users user_author ON user_author.id = tm.user_id
            LEFT JOIN users admin_author ON admin_author.id = tm.admin_id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC, tm.id ASC
        ");
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countByTicketId(int $ticketId): int
    {
        $stmt = self::getDB()->prepare('SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ?');
        $stmt->execute([$ticketId]);
        return (int)$stmt->fetchColumn();
    }

    public static function addUserMessage(int $ticketId, int $userId, string $message): int
    {
        $ticket = Ticket::findByIdForUser($ticketId, $userId);
        self::validateMessage($ticketId, $userId, $message, $ticket);

        $db = self::getDB();
        $db->beginTransaction();
        try {
            $insert = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, admin_id, message, created_at)
                VALUES (?, ?, NULL, ?, NOW())
            ");
            $insert->execute([$ticketId, $userId, trim($message)]);
            $messageId = (int)$db->lastInsertId();

            $update = $db->prepare("
                UPDATE tickets
                SET last_message_at = NOW(),
                    updated_at = NOW(),
                    closed_at = NULL,
                    closed_by_admin_id = NULL
                WHERE id = ?
            ");
            $update->execute([$ticketId]);
            $db->commit();

            return $messageId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function addAdminMessage(int $ticketId, int $adminId, string $message, bool $setInProgress = true): int
    {
        $ticket = Ticket::findById($ticketId);
        self::validateMessage($ticketId, $adminId, $message, $ticket);

        $db = self::getDB();
        $db->beginTransaction();
        try {
            $insert = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, admin_id, message, created_at)
                VALUES (?, NULL, ?, ?, NOW())
            ");
            $insert->execute([$ticketId, $adminId, trim($message)]);
            $messageId = (int)$db->lastInsertId();
            $newStatus = $setInProgress ? 'in_progress' : (string)$ticket['status'];

            $update = $db->prepare("
                UPDATE tickets
                SET status = ?,
                    last_message_at = NOW(),
                    updated_at = NOW(),
                    closed_at = NULL,
                    closed_by_admin_id = NULL,
                    assigned_admin_id = COALESCE(assigned_admin_id, ?),
                    first_response_at = COALESCE(first_response_at, NOW())
                WHERE id = ?
            ");
            $update->execute([$newStatus, $adminId, $ticketId]);
            $db->commit();

            return $messageId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function validateMessage(int $ticketId, int $authorId, string $message, ?array $ticket): void
    {
        if ($ticketId <= 0 || $authorId <= 0) {
            throw new RuntimeException('Ticket ou auteur invalide.');
        }

        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 10000) {
            throw new RuntimeException('Le message est obligatoire et limité à 10 000 caractères.');
        }

        if (!$ticket) {
            throw new RuntimeException('Ticket introuvable.');
        }

        if (($ticket['status'] ?? '') === 'closed') {
            throw new RuntimeException('Impossible de répondre à un ticket fermé.');
        }
    }
}
