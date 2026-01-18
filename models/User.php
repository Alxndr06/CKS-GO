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
                ':is_active' => (int) $data['is_active'],
                ':activation_token' => $data['activation_token'],
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur PDO dans User::create(): " . $e->getMessage());
            return false;
        }
    }

    public static function deleteById(int $id): void
    {
        $db   = self::getDB();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            redirectWithError("Utilisateur introuvable ou déjà supprimé.", 'user', 'allUsers');
        }
    }

    //Bool qui définit si l'email ou le pseudo sont déjà utilisés. A refacto.
    public static function checkUnicity(string $column, string $value): bool
    {
        $db = self::getDB();

        $allowed = ['email', 'username'];
        if (!in_array($column, $allowed)) {
            throw new InvalidArgumentException("Mauvaise colonne pasée en paramètre.");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchColumn() == 0;
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

        $stmt = $db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getInactiveCount(): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_active = ?");
        $stmt->execute([0]);
        return (int) $stmt->fetchColumn();
    }

    public static function getInactives(): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE is_active = ?");
        $stmt->execute([0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSumOfNotes() : int
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT COALESCE(SUM(note), 0) FROM users");
        $stmt->execute();
        return (int) $stmt->fetchColumn(0);
    }
}