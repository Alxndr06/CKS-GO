<?php
require_once __DIR__ . '/../config/config.php';
class Model
{
    protected static $db;

    // Methode de connexion unique
    protected static function getDB()
    {
        // Si déjà connecté, on renvoie à la co.
        if (self::$db) {
            return self::$db;
        }

        require __DIR__ . '/../config/config.php';

        try {
            self::$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return self::$db;
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
}