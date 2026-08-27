<?php

require_once __DIR__ . '/../core/Model.php';

class UserPermissionOverride extends Model
{
    public static function getForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $stmt = self::getDB()->prepare("
                SELECT permission, effect
                FROM user_permission_overrides
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);

            $overrides = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $permission = (string)($row['permission'] ?? '');
                $effect = (string)($row['effect'] ?? '');

                if ($permission !== '' && in_array($effect, ['allow', 'deny'], true)) {
                    $overrides[$permission] = $effect;
                }
            }

            return $overrides;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                error_log('Migration user_permission_overrides non appliquée.');
                return [];
            }

            throw $e;
        }
    }

    public static function replaceForUser(int $userId, array $overrides, int $grantedBy): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }

        $db = self::getDB();
        $db->beginTransaction();

        try {
            $deleteStmt = $db->prepare('DELETE FROM user_permission_overrides WHERE user_id = ?');
            $deleteStmt->execute([$userId]);

            if ($overrides !== []) {
                $insertStmt = $db->prepare("
                    INSERT INTO user_permission_overrides (user_id, permission, effect, granted_by)
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($overrides as $permission => $effect) {
                    if (!in_array($effect, ['allow', 'deny'], true)) {
                        continue;
                    }

                    $insertStmt->execute([$userId, $permission, $effect, $grantedBy ?: null]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}
