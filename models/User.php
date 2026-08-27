<?php
require_once __DIR__ . '/../core/Model.php';

class User extends Model
{
    private static function hashActivationToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function normalizeActivationTokenForStorage(?string $token): ?string
    {
        $token = trim((string)$token);

        if ($token === '') {
            return null;
        }

        return self::hashActivationToken($token);
    }

    private static function buildActivationTokenLookupCandidates(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return [];
        }

        return array_values(array_unique([
            $token,
            self::hashActivationToken($token),
        ]));
    }

    public static function create(array $data): bool
    {
        $db = self::getDB();

        $sql = "INSERT INTO users (
                    username,
                    firstname,
                    lastname,
                    email,
                    unit,
                    password_hash,
                    is_active,
                    is_locked,
                    activation_token,
                    email_verified_at
                ) VALUES (
                    :username,
                    :firstname,
                    :lastname,
                    :email,
                    :unit,
                    :password_hash,
                    :is_active,
                    :is_locked,
                    :activation_token,
                    :email_verified_at
                )";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':username' => $data['username'],
                ':firstname' => $data['firstname'],
                ':lastname' => $data['lastname'],
                ':email' => $data['email'],
                ':unit' => $data['unit'],
                ':password_hash' => $data['password_hash'],
                ':is_active' => (int)$data['is_active'],
                ':is_locked' => isset($data['is_locked']) ? (int)$data['is_locked'] : 0,
                ':activation_token' => self::normalizeActivationTokenForStorage($data['activation_token'] ?? null),
                ':email_verified_at' => $data['email_verified_at'] ?? null,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur PDO dans User::create(): " . $e->getMessage());
            return false;
        }
    }

    public static function createByAdmin(array $data): int
    {
        $db = self::getDB();

        $sql = "
            INSERT INTO users (
                username,
                firstname,
                lastname,
                email,
                unit,
                password_hash,
                role,
                note,
                is_active,
                is_locked,
                activation_token,
                email_verified_at
            ) VALUES (
                :username,
                :firstname,
                :lastname,
                :email,
                :unit,
                :password_hash,
                :role,
                :note,
                :is_active,
                :is_locked,
                :activation_token,
                :email_verified_at
            )
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':firstname' => $data['firstname'],
            ':lastname' => $data['lastname'],
            ':email' => $data['email'],
            ':unit' => $data['unit'],
            ':password_hash' => $data['password_hash'],
            ':role' => $data['role'],
            ':note' => (float)$data['note'],
            ':is_active' => (int)$data['is_active'],
            ':is_locked' => isset($data['is_locked']) ? (int)$data['is_locked'] : 0,
            ':activation_token' => self::normalizeActivationTokenForStorage($data['activation_token'] ?? null),
            ':email_verified_at' => $data['email_verified_at'] ?? null,
        ]);

        return (int)$db->lastInsertId();
    }

    public static function updateById(int $id, array $data): bool
    {
        $db = self::getDB();

        $sql = "
            UPDATE users
            SET
                username = :username,
                firstname = :firstname,
                lastname = :lastname,
                email = :email,
                unit = :unit,
                role = :role,
                note = :note,
                is_active = :is_active,
                is_locked = :is_locked
            WHERE id = :id
        ";

        $stmt = $db->prepare($sql);

        return $stmt->execute([
            ':username' => $data['username'],
            ':firstname' => $data['firstname'],
            ':lastname' => $data['lastname'],
            ':email' => $data['email'],
            ':unit' => $data['unit'],
            ':role' => $data['role'],
            ':note' => (float)$data['note'],
            ':is_active' => (int)$data['is_active'],
            ':is_locked' => isset($data['is_locked']) ? (int)$data['is_locked'] : 0,
            ':id' => $id
        ]);
    }

    public static function updatePasswordById(int $id, string $passwordHash): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = ?,
                password_reset_token_hash = NULL,
                password_reset_requested_at = NULL,
                password_reset_expires_at = NULL,
                failed_login_attempts = 0,
                last_failed_login_at = NULL,
                login_locked_until = NULL
            WHERE id = ?
        " );

        return $stmt->execute([$passwordHash, $id]);
    }

    public static function deleteById(int $id): void
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $db->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$id]);
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                redirectWithError("Utilisateur introuvable ou déjà supprimé.", 'admin', 'showAllUsers');
            }

            $db->commit();
        } catch (Throwable $throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $throwable;
        }
    }

    public static function checkUnicity(string $column, string $value): bool
    {
        $db = self::getDB();

        $allowed = ['email', 'username'];
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException("Mauvaise colonne passée en paramètre.");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $column = ?");
        $stmt->execute([$value]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public static function checkUnicityForUpdate(string $column, string $value, int $excludeId): bool
    {
        $db = self::getDB();

        $allowed = ['email', 'username'];
        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException("Mauvaise colonne passée en paramètre.");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $column = ? AND id != ?");
        $stmt->execute([$value, $excludeId]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public static function findByMail($email): array|false
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByUsername(string $username): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function findByID($id): array|false
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByActivationToken(string $token): ?array
    {
        $candidates = self::buildActivationTokenLookupCandidates($token);

        if (empty($candidates)) {
            return null;
        }

        $db = self::getDB();
        $placeholders = implode(', ', array_fill(0, count($candidates), '?'));

        $stmt = $db->prepare("
            SELECT *
            FROM users
            WHERE activation_token IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute($candidates);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function markEmailAsVerified(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET email_verified_at = NOW(),
                activation_token = NULL
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public static function isEmailVerified(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT email_verified_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        return !empty($stmt->fetchColumn());
    }

    public static function createPasswordResetTokenForUser(
        int $id,
        int $ttlMinutes = 60,
        int $cooldownSeconds = 300
    ): array {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id, email, firstname, lastname, username, password_reset_requested_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'issued' => false,
                'reason' => 'not_found',
                'remaining_seconds' => 0,
            ];
        }

        $requestedAt = !empty($user['password_reset_requested_at'])
            ? strtotime((string)$user['password_reset_requested_at'])
            : false;

        if ($requestedAt !== false) {
            $remainingSeconds = ($requestedAt + $cooldownSeconds) - time();

            if ($remainingSeconds > 0) {
                return [
                    'issued' => false,
                    'reason' => 'cooldown',
                    'remaining_seconds' => $remainingSeconds,
                ];
            }
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $throwable) {
            error_log('User::createPasswordResetTokenForUser random_bytes failed: ' . $throwable->getMessage());

            return [
                'issued' => false,
                'reason' => 'random_failure',
                'remaining_seconds' => 0,
            ];
        }

        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $update = $db->prepare("
            UPDATE users
            SET password_reset_token_hash = ?,
                password_reset_requested_at = NOW(),
                password_reset_expires_at = ?
            WHERE id = ?
        ");

        $success = $update->execute([$tokenHash, $expiresAt, $id]);

        if (!$success) {
            return [
                'issued' => false,
                'reason' => 'db_failure',
                'remaining_seconds' => 0,
            ];
        }

        return [
            'issued' => true,
            'token' => $token,
            'expires_at' => $expiresAt,
            'remaining_seconds' => 0,
        ];
    }

    public static function findByPasswordResetToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $db = self::getDB();
        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare("
            SELECT *
            FROM users
            WHERE password_reset_token_hash = ?
              AND password_reset_expires_at IS NOT NULL
              AND password_reset_expires_at >= NOW()
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function clearPasswordResetToken(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET password_reset_token_hash = NULL,
                password_reset_requested_at = NULL,
                password_reset_expires_at = NULL
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public static function getAll(): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users ORDER BY id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function searchAll(?string $q = null): array
    {
        $db = self::getDB();

        $sql = "SELECT * FROM users WHERE 1";
        $params = [];

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    username LIKE ?
                    OR firstname LIKE ?
                    OR lastname LIKE ?
                    OR email LIKE ?
                    OR unit LIKE ?
                    OR role LIKE ?
                )
            ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function buildAdminUserFilterSql(array $filters, array &$params): string
    {
        $where = ['1 = 1'];
        $q = trim((string)($filters['q'] ?? ''));
        $role = normalizeUserRole($filters['role'] ?? '');
        $status = trim((string)($filters['status'] ?? ''));
        $unit = trim((string)($filters['unit'] ?? ''));

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(username LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        if (($filters['role'] ?? '') !== '' && array_key_exists($role, getRoleDefinitions())) {
            $where[] = 'role = ?';
            $params[] = $role;
        }

        if (in_array($unit, ['mineurs', 'vif', 'syndicat'], true)) {
            $where[] = 'unit = ?';
            $params[] = $unit;
        }

        if ($status === 'active') {
            $where[] = 'is_active = 1 AND is_locked = 0';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        } elseif ($status === 'locked') {
            $where[] = 'is_locked = 1';
        }

        return implode(' AND ', $where);
    }

    public static function searchAdminUsers(array $filters, int $limit = 12, int $offset = 0): array
    {
        $db = self::getDB();
        $params = [];
        $whereSql = self::buildAdminUserFilterSql($filters, $params);
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $db->prepare("
            SELECT *
            FROM users
            WHERE {$whereSql}
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countAdminUsers(array $filters): int
    {
        $db = self::getDB();
        $params = [];
        $whereSql = self::buildAdminUserFilterSql($filters, $params);
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAdminDirectoryStats(): array
    {
        $row = self::getDB()->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 AND is_locked = 0 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) AS locked,
                SUM(CASE WHEN role <> 'user' THEN 1 ELSE 0 END) AS staff
            FROM users
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'locked' => (int)($row['locked'] ?? 0),
            'staff' => (int)($row['staff'] ?? 0),
        ];
    }

    public static function getInactiveCount(): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_active = ?");
        $stmt->execute([0]);
        return (int)$stmt->fetchColumn();
    }

    public static function getActiveStaffDirectory(): array
    {
        $stmt = self::getDB()->query("
            SELECT id, username, firstname, lastname, role
            FROM users
            WHERE is_active = 1
              AND is_locked = 0
              AND role <> 'user'
            ORDER BY firstname ASC, lastname ASC, username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getInactives(): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE is_active = ?");
        $stmt->execute([0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSumOfNotes(): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT COALESCE(SUM(note), 0) FROM users");
        $stmt->execute();
        return (int)$stmt->fetchColumn(0);
    }

    public static function getTopDebtors(int $limit = 5): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT *
            FROM users
            WHERE note > 0
            ORDER BY note DESC, id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isLocked(int $userId): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT is_locked
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn() === 1;
    }

    public static function lockById(int $userId): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET is_locked = 1
            WHERE id = ?
        ");

        return $stmt->execute([$userId]);
    }

    public static function unlockById(int $userId): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET is_locked = 0
            WHERE id = ?
        ");

        return $stmt->execute([$userId]);
    }

    public static function searchPending(?string $q = null): array
    {
        $db = self::getDB();

        $sql = "SELECT * FROM users WHERE is_active = 0";
        $params = [];

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
            AND (
                username LIKE ?
                OR firstname LIKE ?
                OR lastname LIKE ?
                OR email LIKE ?
                OR unit LIKE ?
                OR role LIKE ?
            )
        ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY created_at ASC, id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activateById(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET is_active = 1
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public static function deactivateById(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET is_active = 0
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public static function updateEmailForUser(int $id, string $email, string $activationToken): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
        UPDATE users
        SET email = ?,
            email_verified_at = NULL,
            activation_token = ?
        WHERE id = ?
    ");

        return $stmt->execute([
            $email,
            self::normalizeActivationTokenForStorage($activationToken),
            $id
        ]);
    }

    public static function deleteOwnAccountById(int $id): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $db->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$id]);
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$id]) && $stmt->rowCount() === 1;

            if (!$success) {
                $db->rollBack();
                return false;
            }

            $db->commit();
            return true;
        } catch (Throwable $throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $throwable;
        }
    }

    public static function incrementFailedLoginAttempts(int $id): void
    {
        $db = self::getDB();

        $stmt = $db->prepare("
        UPDATE users
        SET failed_login_attempts = failed_login_attempts + 1,
            last_failed_login_at = NOW()
        WHERE id = ?
    ");
        $stmt->execute([$id]);
    }

    public static function resetFailedLoginAttempts(int $id): void
    {
        $db = self::getDB();

        $stmt = $db->prepare("
        UPDATE users
        SET failed_login_attempts = 0,
            last_failed_login_at = NULL,
            login_locked_until = NULL
        WHERE id = ?
    ");
        $stmt->execute([$id]);
    }

    public static function isLoginTemporarilyLocked(array $user): bool
    {
        if (empty($user['login_locked_until'])) {
            return false;
        }

        $lockedUntil = strtotime((string)$user['login_locked_until']);

        if ($lockedUntil === false) {
            return false;
        }

        return $lockedUntil > time();
    }

    public static function getLoginLockRemainingSeconds(array $user): int
    {
        if (!self::isLoginTemporarilyLocked($user)) {
            return 0;
        }

        $lockedUntil = strtotime((string)$user['login_locked_until']);

        if ($lockedUntil === false) {
            return 0;
        }

        return max(0, $lockedUntil - time());
    }

    public static function registerFailedLoginAttempt(
        int $id,
        int $maxAttempts = 5,
        int $observationWindowMinutes = 15,
        int $lockoutMinutes = 15
    ): array {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id, failed_login_attempts, last_failed_login_at, login_locked_until
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
                'login_locked_until' => null,
                'is_locked' => false,
            ];
        }

        $now = time();
        $currentAttempts = (int)($user['failed_login_attempts'] ?? 0);
        $lastFailedAt = !empty($user['last_failed_login_at']) ? strtotime((string)$user['last_failed_login_at']) : false;

        if ($lastFailedAt === false || ($now - $lastFailedAt) > ($observationWindowMinutes * 60)) {
            $currentAttempts = 0;
        }

        $currentAttempts++;
        $lockedUntil = null;

        if ($currentAttempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', $now + ($lockoutMinutes * 60));
        }

        $update = $db->prepare("
            UPDATE users
            SET failed_login_attempts = ?,
                last_failed_login_at = NOW(),
                login_locked_until = ?
            WHERE id = ?
        ");
        $update->execute([$currentAttempts, $lockedUntil, $id]);

        return [
            'failed_login_attempts' => $currentAttempts,
            'last_failed_login_at' => date('Y-m-d H:i:s', $now),
            'login_locked_until' => $lockedUntil,
            'is_locked' => $lockedUntil !== null,
        ];
    }

    public static function isBannedById(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
        SELECT is_banned
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->execute([$id]);

        return (int)$stmt->fetchColumn() === 1;
    }
}
