<?php

// DATA BASE
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'cksgo_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// ENVIRONNEMENT
if (!defined('APP_ENV')) {
    define('APP_ENV', 'dev');
}

// Détection automatique du chemin
if (!defined('BASE_URL')) {
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    define('BASE_URL', rtrim($basePath, '/'));
}
