<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/functions.php';

bootstrapApplication();
checkSession();
sendSecurityHeaders();

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit;
}

enforceAccessBans();
enforceMaintenanceMode();

require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Router.php';

$router = new Router();
$router->handleRequest();
