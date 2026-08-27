<?php
require_once __DIR__ . '/../core/Model.php';

class AccessBan extends Model
{
    public static function normalizeValue(string $type, string $value): string
    {
        $type = strtolower(trim($type));
        $value = trim($value);

        if ($type === 'email') {
            return mb_strtolower($value);
        }

        if ($type === 'ip') {
            return strtolower($value);
        }

        return '';
    }

    public static function validate(string $type, string $value): ?string
    {
        $type = strtolower(trim($type));
        $value = self::normalizeValue($type, $value);

        if (!in_array($type, ['email', 'ip'], true)) {
            return 'Type de bannissement invalide.';
        }

        if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }

        if ($type === 'ip' && !filter_var($value, FILTER_VALIDATE_IP)) {
            return 'Adresse IP invalide.';
        }

        return null;
    }

    public static function exists(string $type, string $value): bool
    {
        $value = self::normalizeValue($type, $value);

        if ($value === '') {
            return false;
        }

        $stmt = self::getDB()->prepare('
            SELECT 1
            FROM access_bans
            WHERE ban_type = ? AND ban_value = ?
            LIMIT 1
        ');
        $stmt->execute([$type, $value]);

        return (bool)$stmt->fetchColumn();
    }

    public static function isEmailBanned(?string $email): bool
    {
        return self::exists('email', (string)$email);
    }

    public static function isIpBanned(?string $ip): bool
    {
        return self::exists('ip', (string)$ip);
    }

    public static function create(string $type, string $value, string $reason, int $adminId): int
    {
        $type = strtolower(trim($type));
        $value = self::normalizeValue($type, $value);
        $validationError = self::validate($type, $value);

        if ($validationError !== null) {
            throw new InvalidArgumentException($validationError);
        }

        $stmt = self::getDB()->prepare('
            INSERT INTO access_bans (ban_type, ban_value, reason, created_by)
            VALUES (?, ?, ?, ?)
        ');

        try {
            $stmt->execute([
                $type,
                $value,
                mb_substr(trim($reason), 0, 255),
                $adminId > 0 ? $adminId : null,
            ]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                throw new RuntimeException('Cette adresse est déjà bannie.');
            }

            throw $exception;
        }

        return (int)self::getDB()->lastInsertId();
    }

    public static function deleteById(int $id): bool
    {
        $stmt = self::getDB()->prepare('DELETE FROM access_bans WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::getDB()->prepare('
            SELECT id, ban_type, ban_value, reason, created_by, created_at
            FROM access_bans
            WHERE id = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $ban = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ban ?: null;
    }

    public static function all(): array
    {
        $stmt = self::getDB()->query('
            SELECT
                b.id,
                b.ban_type,
                b.ban_value,
                b.reason,
                b.created_by,
                b.created_at,
                u.username AS created_by_username,
                u.firstname AS created_by_firstname,
                u.lastname AS created_by_lastname
            FROM access_bans b
            LEFT JOIN users u ON u.id = b.created_by
            ORDER BY b.created_at DESC, b.id DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
