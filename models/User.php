<?php
require_once __DIR__ . '/../core/Model.php';

class User extends Model
{
    public static function create(array $data): bool
    {
        $db = self::getDB();

        $sql = "INSERT INTO users (username, firstname, lastname, email, unit, password_hash, is_active, activation_token)
            VALUES (:username, :firstname, :lastname, :email, :unit, :password_hash, :is_active, :activation_token)";

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
                ':activation_token' => $data['activation_token'],
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
                activation_token
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
                :activation_token
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
            ':activation_token' => $data['activation_token'],
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
                is_active = :is_active
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
            ':id' => $id
        ]);
    }

    public static function updatePasswordById(int $id, string $passwordHash): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = ?
            WHERE id = ?
        ");

        return $stmt->execute([$passwordHash, $id]);
    }

    public static function deleteById(int $id): void
    {
        $db = self::getDB();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            redirectWithError("Utilisateur introuvable ou déjà supprimé.", 'admin', 'showAllUsers');
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

    public static function findByMail($email): array
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

    public static function findByID($id): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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

    public static function getInactiveCount(): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_active = ?");
        $stmt->execute([0]);
        return (int)$stmt->fetchColumn();
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
}