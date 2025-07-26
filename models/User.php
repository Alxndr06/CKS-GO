<?php
require_once __DIR__ . '/../core/model.php';
class User extends Model
{
    public static function create(array $data) : bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("INSERT INTO users (username, lastname, firstname, email, password, unit, is_active, activation_token) VALUES (:username, :lastname, :firstname, :email, :password, :unit, :is_active, :activation_token)");

        try {
            $stmt->execute([
                ':username' => $data['username'],
                ':lastname' => $data['lastname'],
                ':firstname' => $data['firstname'],
                ':email' => $data['email'],
                ':password' => $data['password'],
                ':unit' => $data['unit'],
                ':is_active' => $data['is_active'],
                ':activation_token' => $data['activation_token']
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur PDO dans la fonction User::create() : " . $e->getMessage());
            return false;
        }
    }

    //Bool qui définit si l'email ou le pseudo sont déjà utilisés. A refacto.
    public static function checkUnicity(string $column, string $value): bool {
        $db = self::getDB();

        $allowed = ['email', 'username'];
        if (!in_array($column, $allowed)) {
            throw new InvalidArgumentException("Mauvaise colonne pasée en paramètre.");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $column = ?");
        $stmt->execute([$value]);
        return $stmt->fetchColumn() == 0;
    }

    public static function findByMail($email) : array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findByUsername($username) : array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function findById($id) : array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getAll() : array
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}