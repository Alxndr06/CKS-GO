<?php

require_once __DIR__ . '/../config/config.php';

class Model
{
    protected static $db;

    protected static function getDB(): PDO
    {
        if (self::$db instanceof PDO) {
            return self::$db;
        }

        try {
            self::$db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );

            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return self::$db;
        } catch (PDOException $e) {
            error_log(sprintf(
                'Database connection failed: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            if (class_exists('Controller')) {
                Controller::renderError(500);
                exit;
            }

            http_response_code(500);
            exit('Une erreur interne est survenue.');
        }
    }
}