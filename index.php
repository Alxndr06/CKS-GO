<?php
require_once __DIR__ . '/helpers/functions.php';
// On lance la session
checkSession();

// On charge la configuration
require_once 'config/config.php';
// On charge le système de routing
require_once 'core/Router.php';

// Instanciation du routeur
$router = new Router();
// Traitement de requête
$router->handleRequest();