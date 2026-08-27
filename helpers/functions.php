<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Setting.php';

function appIsProduction(): bool
{
    return strtolower((string)APP_ENV) === 'prod';
}

function appIsDebug(): bool
{
    return (bool)APP_DEBUG;
}

function isHttpsRequest(): bool
{
    $directHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    if ($directHttps || !defined('TRUST_PROXY_HEADERS') || !TRUST_PROXY_HEADERS) {
        return $directHttps;
    }

    return (
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    );
}

function buildAppUrl(string $path = ''): string
{
    $baseUrl = rtrim(APP_URL, '/');
    $path = trim($path);

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function getRequestId(): string
{
    static $requestId = null;

    if ($requestId === null) {
        $requestId = bin2hex(random_bytes(16));
        $_SERVER['CKSGO_REQUEST_ID'] = $requestId;
    }

    return $requestId;
}

function getPasswordLength(string $password): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($password, 'UTF-8')
        : strlen($password);
}

function normalizeSearchQuery(mixed $value, int $maxLength = 100): string
{
    $query = trim((string)$value);
    $maxLength = max(1, min($maxLength, 500));

    return function_exists('mb_substr')
        ? mb_substr($query, 0, $maxLength, 'UTF-8')
        : substr($query, 0, $maxLength);
}

function validatePasswordPolicy(string $password, ?string $confirmation = null): ?string
{
    $length = getPasswordLength($password);

    if ($length < 15) {
        return "Le mot de passe doit contenir au moins 15 caractères.";
    }

    if ($length > 128 || strlen($password) > 1024) {
        return "Le mot de passe ne doit pas dépasser 128 caractères.";
    }

    $normalized = function_exists('mb_strtolower')
        ? mb_strtolower(trim($password), 'UTF-8')
        : strtolower(trim($password));
    $commonPasswords = [
        '123456789012345',
        'azertyuiop12345',
        'qwertyuiop12345',
        'motdepasse12345',
        'password123456',
        'administrateur',
        'cksgo1234567890',
    ];

    if (in_array($normalized, $commonPasswords, true)) {
        return "Ce mot de passe est trop courant. Choisissez une phrase de passe unique.";
    }

    if ($confirmation !== null && !hash_equals($password, $confirmation)) {
        return "Les mots de passe ne correspondent pas.";
    }

    return null;
}

function hashPassword(string $password): string
{
    if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 19456,
            'time_cost' => 2,
            'threads' => 1,
        ]);
    }

    return password_hash($password, PASSWORD_DEFAULT);
}

function passwordHashNeedsUpgrade(string $hash): bool
{
    if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 19456,
            'time_cost' => 2,
            'threads' => 1,
        ]);
    }

    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

function bootstrapApplication(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    error_reporting(E_ALL);

    if (appIsDebug()) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    } else {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
    }

    register_shutdown_function('handleApplicationShutdown');
    set_exception_handler('handleUnhandledThrowable');
}

function handleUnhandledThrowable(Throwable $throwable): void
{
    error_log(sprintf(
        'Unhandled exception: %s in %s:%d',
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (class_exists('Controller')) {
        Controller::renderError(500);
        exit;
    }

    exit('Une erreur interne est survenue.');
}

function handleApplicationShutdown(): void
{
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array((int)$error['type'], $fatalTypes, true)) {
        return;
    }

    error_log(sprintf(
        'Fatal error: %s in %s:%d',
        $error['message'] ?? 'Unknown fatal error',
        $error['file'] ?? 'unknown file',
        $error['line'] ?? 0
    ));

    if (headers_sent()) {
        return;
    }

    http_response_code(500);

    if (class_exists('Controller')) {
        Controller::renderError(500);
        return;
    }

    echo 'Une erreur interne est survenue.';
}

function isMaintenanceModeEnabled(): bool
{
    if (!Setting::getBool('maintenance_mode', false)) {
        return false;
    }

    $lastAdminActivity = trim((string)Setting::get('maintenance_last_admin_activity_at', ''));
    $maintenanceStartedAt = trim((string)Setting::get('maintenance_started_at', ''));
    $referenceActivity = $lastAdminActivity !== '' ? $lastAdminActivity : $maintenanceStartedAt;
    $lastAdminActivityTimestamp = $referenceActivity !== '' ? strtotime($referenceActivity) : false;

    if ($lastAdminActivityTimestamp === false) {
        $now = date('Y-m-d H:i:s');
        Setting::setMany([
            'maintenance_started_at' => $now,
            'maintenance_last_admin_activity_at' => $now,
        ]);
        return true;
    }

    if ($lastAdminActivityTimestamp !== false && time() - $lastAdminActivityTimestamp >= 1200) {
        Setting::setMany([
            'maintenance_mode' => '0',
            'maintenance_started_at' => '',
            'maintenance_last_admin_activity_at' => '',
        ]);

        return false;
    }

    return true;
}

function getRequestIpAddress(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function enforceAccessBans(): void
{
    require_once __DIR__ . '/../models/AccessBan.php';
    require_once __DIR__ . '/../models/User.php';

    $ip = getRequestIpAddress();
    $sessionUserId = (int)($_SESSION['user']['id'] ?? 0);
    $currentUser = $sessionUserId > 0 ? User::findByID($sessionUserId) : false;
    $sessionEmail = strtolower(trim((string)($currentUser['email'] ?? ($_SESSION['user']['email'] ?? ''))));
    $ipBanned = $ip !== '' && AccessBan::isIpBanned($ip);
    $emailBanned = $sessionEmail !== '' && AccessBan::isEmailBanned($sessionEmail);
    $accountBanned = $currentUser && (int)($currentUser['is_banned'] ?? 0) === 1;
    $accountUnavailable = $sessionUserId > 0 && (
        !$currentUser
        || (int)($currentUser['is_active'] ?? 0) !== 1
        || empty($currentUser['email_verified_at'])
    );

    if (!$ipBanned && !$emailBanned && !$accountBanned && !$accountUnavailable) {
        return;
    }

    if ($sessionUserId > 0) {
        session_unset();
        session_destroy();
    }

    if ($accountUnavailable && !$ipBanned && !$emailBanned && !$accountBanned) {
        redirectWithWarning("Votre session n'est plus autorisée. Veuillez vous reconnecter.", 'user', 'login');
    }

    http_response_code(403);
    require __DIR__ . '/../views/errors/403.php';
    exit;
}

function isShopLocked(): bool
{
    return Setting::getBool('shop_locked', false);
}

function getRegistrationMode(): string
{
    $mode = Setting::get('registration_mode', 'open');
    return in_array($mode, ['open', 'approval_required'], true) ? $mode : 'open';
}

function normalizeUserRole(?string $role): string
{
    $role = strtolower(trim((string)$role));

    return match ($role) {
        'helper' => 'assistant',
        'mod' => 'gestionnaire',
        'assistant', 'gestionnaire', 'responsable', 'admin' => $role,
        default => 'user',
    };
}

function getRoleDefinitions(): array
{
    return [
        'user' => [
            'label' => 'Utilisateur',
            'short_label' => 'Utilisateur',
            'rank' => 0,
            'description' => 'Accès à la boutique et à son espace personnel.',
        ],
        'assistant' => [
            'label' => 'Assistant',
            'short_label' => 'Assistant',
            'rank' => 10,
            'description' => 'Traite les tickets et les signalements de la boutique.',
        ],
        'gestionnaire' => [
            'label' => 'Gestionnaire',
            'short_label' => 'Gestionnaire',
            'rank' => 20,
            'description' => 'Pilote les stocks, commandes, paiements et validations courantes.',
        ],
        'responsable' => [
            'label' => 'Responsable',
            'short_label' => 'Responsable',
            'rank' => 30,
            'description' => 'Supervise l’équipe, les comptes, les contenus et les opérations sensibles.',
        ],
        'admin' => [
            'label' => 'Administrateur',
            'short_label' => 'Admin',
            'rank' => 40,
            'description' => 'Dispose de tous les droits, dont les réglages système et les suppressions.',
        ],
    ];
}

function getRoleLabel(?string $role, bool $short = false): string
{
    $normalizedRole = normalizeUserRole($role);
    $definition = getRoleDefinitions()[$normalizedRole] ?? getRoleDefinitions()['user'];

    return (string)$definition[$short ? 'short_label' : 'label'];
}

function getRoleDescription(?string $role): string
{
    $normalizedRole = normalizeUserRole($role);
    return (string)(getRoleDefinitions()[$normalizedRole]['description'] ?? '');
}

function getRoleRank(?string $role): int
{
    $normalizedRole = normalizeUserRole($role);
    return (int)(getRoleDefinitions()[$normalizedRole]['rank'] ?? 0);
}

function getCurrentUserRole(): string
{
    checkSession();
    return normalizeUserRole($_SESSION['user']['role'] ?? 'user');
}

function getPermissionDefinitions(): array
{
    return [
        'staff.access' => ['group' => 'Accès', 'label' => 'Accéder à Gestion', 'description' => 'Ouvre le tableau de bord et la navigation de gestion.'],
        'support.manage' => ['group' => 'Assistance', 'label' => 'Gérer le support', 'description' => 'Consulter et traiter les tickets utilisateurs.'],
        'alerts.manage' => ['group' => 'Assistance', 'label' => 'Gérer les signalements', 'description' => 'Traiter les incidents remontés depuis la boutique.'],
        'users.view' => ['group' => 'Utilisateurs', 'label' => 'Consulter les utilisateurs', 'description' => 'Accéder à l’annuaire et aux fiches utilisateurs.'],
        'users.approve' => ['group' => 'Utilisateurs', 'label' => 'Valider les inscriptions', 'description' => 'Accepter ou refuser les nouveaux comptes.'],
        'users.manage' => ['group' => 'Utilisateurs', 'label' => 'Modifier les utilisateurs', 'description' => 'Modifier les profils, rôles et accès des comptes inférieurs.'],
        'users.delete' => ['group' => 'Utilisateurs', 'label' => 'Supprimer les utilisateurs', 'description' => 'Supprimer définitivement un compte autorisé.'],
        'shop.manage' => ['group' => 'Boutique', 'label' => 'Gérer la boutique', 'description' => 'Administrer le catalogue, les catégories et les stocks.'],
        'inventory.adjust' => ['group' => 'Boutique', 'label' => 'Ajuster les stocks', 'description' => 'Réaliser les inventaires, entrées, sorties et corrections de stock.'],
        'catalog.delete' => ['group' => 'Boutique', 'label' => 'Archiver le catalogue', 'description' => 'Archiver et restaurer des produits et variantes en conservant leur historique.'],
        'orders.manage' => ['group' => 'Opérations', 'label' => 'Gérer les commandes', 'description' => 'Consulter les commandes et générer les factures.'],
        'orders.refund' => ['group' => 'Opérations', 'label' => 'Rembourser et annuler', 'description' => 'Effectuer les opérations financières sensibles sur les commandes.'],
        'billing.manage' => ['group' => 'Opérations', 'label' => 'Gérer les paiements', 'description' => 'Encaisser, facturer et consulter les paiements.'],
        'news.manage' => ['group' => 'Administration', 'label' => 'Gérer les actualités', 'description' => 'Publier et modifier les informations de l’accueil.'],
        'logs.view' => ['group' => 'Administration', 'label' => 'Consulter le journal', 'description' => 'Auditer les actions réalisées dans l’application.'],
        'settings.manage' => ['group' => 'Administration', 'label' => 'Gérer les réglages', 'description' => 'Modifier les paramètres système et les modes de fonctionnement.'],
    ];
}

function getRolePermissions(?string $role): array
{
    $role = normalizeUserRole($role);

    $permissions = [
        'user' => [],
        'assistant' => [
            'staff.access',
            'support.manage',
            'alerts.manage',
        ],
        'gestionnaire' => [
            'staff.access',
            'support.manage',
            'alerts.manage',
            'users.view',
            'users.approve',
            'shop.manage',
            'inventory.adjust',
            'orders.manage',
            'billing.manage',
        ],
        'responsable' => [
            'staff.access',
            'support.manage',
            'alerts.manage',
            'users.view',
            'users.approve',
            'users.manage',
            'shop.manage',
            'inventory.adjust',
            'catalog.delete',
            'orders.manage',
            'orders.refund',
            'billing.manage',
            'news.manage',
            'logs.view',
        ],
        'admin' => ['*'],
    ];

    return $permissions[$role] ?? [];
}

function getUserPermissionOverrides(int $userId): array
{
    static $cache = [];

    if ($userId <= 0) {
        return [];
    }

    if (!array_key_exists($userId, $cache)) {
        require_once __DIR__ . '/../models/UserPermissionOverride.php';
        $cache[$userId] = UserPermissionOverride::getForUser($userId);
    }

    return $cache[$userId];
}

function getUserPermissionMatrix(array $user): array
{
    $userId = (int)($user['id'] ?? 0);
    $role = normalizeUserRole($user['role'] ?? 'user');
    $rolePermissions = getRolePermissions($role);
    $roleHasAll = in_array('*', $rolePermissions, true);
    $overrides = $role === 'admin' ? [] : getUserPermissionOverrides($userId);
    $matrix = [];

    foreach (getPermissionDefinitions() as $permission => $definition) {
        $baseAllowed = $roleHasAll || in_array($permission, $rolePermissions, true);
        $override = $overrides[$permission] ?? 'inherit';
        $effectiveAllowed = $override === 'allow' || ($override !== 'deny' && $baseAllowed);

        $matrix[$permission] = $definition + [
            'permission' => $permission,
            'base_allowed' => $baseAllowed,
            'override' => $override,
            'effective_allowed' => $effectiveAllowed,
        ];
    }

    return $matrix;
}

function canAdministerPermission(string $permission): bool
{
    if (!array_key_exists($permission, getPermissionDefinitions())) {
        return false;
    }

    return getCurrentUserRole() === 'admin' || currentUserCan($permission);
}

function userHasPermission(string $permission, ?string $role = null): bool
{
    $explicitRole = $role !== null;
    $role = normalizeUserRole($role ?? getCurrentUserRole());
    $permissions = getRolePermissions($role);
    $baseAllowed = in_array('*', $permissions, true) || in_array($permission, $permissions, true);

    if ($explicitRole || $role === 'admin' || !isUserLoggedIn()) {
        return $baseAllowed;
    }

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $override = getUserPermissionOverrides($userId)[$permission] ?? 'inherit';

    if ($override === 'allow') {
        return true;
    }

    if ($override === 'deny') {
        return false;
    }

    return $baseAllowed;
}

function currentUserCan(string $permission): bool
{
    return isUserLoggedIn() && userHasPermission($permission);
}

function isStaff(): bool
{
    return currentUserCan('staff.access');
}

function getStaffRoutePermission(string $controller, string $action): ?string
{
    $routePermissions = [
        'admin' => [
            'dashboard' => 'staff.access',
            'serverSettings' => 'settings.manage',
            'updateServerSettings' => 'settings.manage',
            'addAccessBan' => 'settings.manage',
            'removeAccessBan' => 'settings.manage',
            'showAllUsers' => 'users.view',
            'showUser' => 'users.view',
            'sendUserPasswordResetLink' => 'users.manage',
            'addUser' => 'users.manage',
            'createUser' => 'users.manage',
            'editUser' => 'users.manage',
            'updateUser' => 'users.manage',
            'toggleUserLock' => 'users.manage',
            'deleteUser' => 'users.delete',
            'pendingUsers' => 'users.approve',
            'approveUser' => 'users.approve',
            'activateUser' => 'users.approve',
            'rejectUser' => 'users.approve',
            'payments' => 'billing.manage',
            'showPayment' => 'billing.manage',
            'capturePayment' => 'billing.manage',
            'captureUserPayment' => 'billing.manage',
            'captureSelectedPayments' => 'billing.manage',
            'captureUserBalance' => 'billing.manage',
            'captureUserCustomAmount' => 'billing.manage',
            'billing' => 'billing.manage',
            'billUserProduct' => 'billing.manage',
            'createUserProductCharge' => 'billing.manage',
            'logs' => 'logs.view',
            'tickets' => 'support.manage',
            'showTicket' => 'support.manage',
            'replyTicket' => 'support.manage',
            'assignTicket' => 'support.manage',
            'updateTicketStatus' => 'support.manage',
            'updateTicketPriority' => 'support.manage',
            'closeTicket' => 'support.manage',
            'reopenTicket' => 'support.manage',
            'alerts' => 'alerts.manage',
            'showAlert' => 'alerts.manage',
            'assignAlert' => 'alerts.manage',
            'refundAlertReporter' => 'orders.refund',
            'updateAlertStatus' => 'alerts.manage',
            'updateAlertPriority' => 'alerts.manage',
            'reopenAlert' => 'alerts.manage',
            'addAlertNote' => 'alerts.manage',
            'news' => 'news.manage',
            'createNews' => 'news.manage',
            'storeNews' => 'news.manage',
            'editNews' => 'news.manage',
            'updateNews' => 'news.manage',
            'toggleNewsPublication' => 'news.manage',
            'duplicateNews' => 'news.manage',
            'deleteNews' => 'news.manage',
            'orders' => 'orders.manage',
            'showOrder' => 'orders.manage',
            'showInvoice' => 'orders.manage',
            'generateInvoice' => 'orders.manage',
            'generateSelectedInvoices' => 'orders.manage',
            'refundOrder' => 'orders.refund',
            'refundOrderItem' => 'orders.refund',
            'cancelOrder' => 'orders.refund',
        ],
        'shop' => [
            'manageShop' => 'shop.manage',
            'allProducts' => 'shop.manage',
            'inventory' => 'inventory.adjust',
            'inventoryIssues' => 'inventory.adjust',
            'declareInventoryIssue' => 'inventory.adjust',
            'updateVariantStock' => 'inventory.adjust',
            'adjustVariantStock' => 'inventory.adjust',
            'categories' => 'shop.manage',
            'storeCategory' => 'shop.manage',
            'updateCategory' => 'shop.manage',
            'toggleCategory' => 'shop.manage',
            'addProduct' => 'shop.manage',
            'storeProduct' => 'shop.manage',
            'showAdminProduct' => 'shop.manage',
            'addVariant' => 'shop.manage',
            'storeVariant' => 'shop.manage',
            'editProduct' => 'shop.manage',
            'updateProduct' => 'shop.manage',
            'disableProduct' => 'shop.manage',
            'enableProduct' => 'shop.manage',
            'deleteProduct' => 'catalog.delete',
            'restoreProduct' => 'catalog.delete',
            'editVariant' => 'shop.manage',
            'updateVariant' => 'shop.manage',
            'deleteVariant' => 'catalog.delete',
            'restoreVariant' => 'catalog.delete',
        ],
        'user' => [
            'show' => 'users.view',
        ],
    ];

    return $routePermissions[$controller][$action] ?? null;
}

function checkPermission(string $permission): void
{
    checkConnect();

    if (userHasPermission($permission)) {
        return;
    }

    $_SESSION['error_message'] = "Vous n'avez pas les droits nécessaires pour cette action.";
    $destination = userHasPermission('staff.access')
        ? 'index.php?controller=admin&action=dashboard'
        : 'index.php?controller=user&action=dashboard';

    header('Location: ' . $destination);
    exit;
}

function getAssignableRoles(?string $actorRole = null): array
{
    $actorRole = normalizeUserRole($actorRole ?? getCurrentUserRole());
    $actorRank = getRoleRank($actorRole);
    $roles = [];

    foreach (getRoleDefinitions() as $role => $definition) {
        $roleRank = (int)$definition['rank'];
        $canAssign = $actorRole === 'admin' ? $roleRank <= $actorRank : $roleRank < $actorRank;

        if ($canAssign) {
            $roles[$role] = $definition;
        }
    }

    return $roles;
}

function canAssignRole(string $role, ?string $actorRole = null): bool
{
    return array_key_exists(normalizeUserRole($role), getAssignableRoles($actorRole));
}

function canManageUserAccount(?string $targetRole, ?int $targetUserId = null): bool
{
    if (!currentUserCan('users.manage')) {
        return false;
    }

    $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
    if ($targetUserId !== null && $targetUserId === $currentUserId) {
        return false;
    }

    return getRoleRank(getCurrentUserRole()) > getRoleRank($targetRole);
}

function canChangeUserRole(?string $targetRole, string $newRole, ?int $targetUserId = null): bool
{
    $normalizedTargetRole = normalizeUserRole($targetRole);
    $normalizedNewRole = normalizeUserRole($newRole);
    $currentUserId = (int)($_SESSION['user']['id'] ?? 0);

    if ($targetUserId !== null && $targetUserId === $currentUserId) {
        return $normalizedTargetRole === $normalizedNewRole;
    }

    return canManageUserAccount($normalizedTargetRole, $targetUserId)
        && canAssignRole($normalizedNewRole);
}

function getStaffNavigationItems(): array
{
    return [
        ['label' => 'Vue d’ensemble', 'short_label' => 'Vue d’ensemble', 'icon' => 'home', 'permission' => 'staff.access', 'controller' => 'admin', 'action' => 'dashboard'],
        ['label' => 'Support', 'short_label' => 'Support', 'icon' => 'support', 'permission' => 'support.manage', 'controller' => 'admin', 'action' => 'tickets'],
        ['label' => 'Signalements', 'short_label' => 'Alertes', 'icon' => 'alert', 'permission' => 'alerts.manage', 'controller' => 'admin', 'action' => 'alerts'],
        ['label' => 'Utilisateurs', 'short_label' => 'Utilisateurs', 'icon' => 'users', 'permission' => 'users.view', 'controller' => 'admin', 'action' => 'showAllUsers'],
        ['label' => 'Boutique & stocks', 'short_label' => 'Boutique', 'icon' => 'shop', 'permission' => 'shop.manage', 'controller' => 'shop', 'action' => 'manageShop'],
        ['label' => 'Commandes', 'short_label' => 'Commandes', 'icon' => 'orders', 'permission' => 'orders.manage', 'controller' => 'admin', 'action' => 'orders'],
        ['label' => 'Facturation', 'short_label' => 'Facturation', 'icon' => 'invoice', 'permission' => 'billing.manage', 'controller' => 'admin', 'action' => 'billing'],
        ['label' => 'Paiements', 'short_label' => 'Paiements', 'icon' => 'payment', 'permission' => 'billing.manage', 'controller' => 'admin', 'action' => 'payments'],
        ['label' => 'Actualités', 'short_label' => 'Actualités', 'icon' => 'news', 'permission' => 'news.manage', 'controller' => 'admin', 'action' => 'news'],
        ['label' => 'Journal', 'short_label' => 'Journal', 'icon' => 'logs', 'permission' => 'logs.view', 'controller' => 'admin', 'action' => 'logs'],
        ['label' => 'Réglages', 'short_label' => 'Réglages', 'icon' => 'settings', 'permission' => 'settings.manage', 'controller' => 'admin', 'action' => 'serverSettings'],
    ];
}

function ensureShopIsAvailable(): void
{
    if (!isShopLocked()) {
        return;
    }

    if (isUserLoggedIn() && userHasPermission('shop.manage')) {
        return;
    }

    $_SESSION['error_message'] = "La boutique est temporairement verrouillée.";
    header('Location: index.php?controller=home&action=index');
    exit;
}

function enforceMaintenanceMode(): void
{
    if (!isMaintenanceModeEnabled()) {
        return;
    }

    $controller = $_GET['controller'] ?? 'home';
    $action = $_GET['action'] ?? 'index';
    if (isUserLoggedIn() && userHasPermission('staff.access')) {
        $lastHeartbeat = trim((string)Setting::get('maintenance_last_admin_activity_at', ''));
        $lastHeartbeatTimestamp = $lastHeartbeat !== '' ? strtotime($lastHeartbeat) : false;

        if ($lastHeartbeatTimestamp === false || time() - $lastHeartbeatTimestamp >= 60) {
            Setting::set('maintenance_last_admin_activity_at', date('Y-m-d H:i:s'));
        }

        return;
    }

    $allowedRoutes = [
        'home:index',
        'user:login',
        'user:doLogin',
        'user:forgotPassword',
        'user:sendPasswordResetLink',
        'user:resetPassword',
        'user:doResetPassword',
        'home:maintenance',
    ];

    $currentRoute = $controller . ':' . $action;

    if (in_array($currentRoute, $allowedRoutes, true)) {
        return;
    }

    header('Location: index.php?controller=home&action=maintenance');
    exit;
}

function checkSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = isHttpsRequest();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_name(APP_SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function checkConnect(): void
{
    checkSession();

    $timeout = 1200;

    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_destroy();
        redirectWithWarning("Session expirée. Veuillez vous reconnecter.", "user", "login");
    }

    if (!isset($_SESSION['user'])) {
        redirectWithError("Veuillez vous connecter pour accéder à cette page.", "user", "login");
    }

    require_once __DIR__ . '/../models/User.php';
    require_once __DIR__ . '/../models/AccessBan.php';

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $user = $userId > 0 ? User::findByID($userId) : false;
    $email = strtolower(trim((string)($user['email'] ?? '')));
    $accessRevoked = !$user
        || (int)($user['is_active'] ?? 0) !== 1
        || (int)($user['is_banned'] ?? 0) === 1
        || empty($user['email_verified_at'])
        || ($email !== '' && AccessBan::isEmailBanned($email));

    if ($accessRevoked) {
        session_unset();
        session_destroy();
        redirectWithWarning("Votre session n'est plus autorisée. Veuillez vous reconnecter.", 'user', 'login');
    }

    $_SESSION['user'] = array_merge($_SESSION['user'], [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'lastname' => (string)$user['lastname'],
        'firstname' => (string)$user['firstname'],
        'email' => $email,
        'note' => (float)($user['note'] ?? 0),
        'role' => normalizeUserRole($user['role'] ?? 'user'),
        'unit' => (string)($user['unit'] ?? ''),
        'locked' => (int)($user['locked'] ?? ($user['is_locked'] ?? 0)),
        'created_at' => (string)($user['created_at'] ?? ''),
        'is_active' => 1,
    ]);

    $now = time();
    $_SESSION['last_activity'] = $now;

    if ($now - (int)($_SESSION['last_regeneration'] ?? 0) >= 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = $now;
    }
}

function getCsrfToken(): string
{
    checkSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function checkCsrfToken(): void
{
    checkSession();

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        redirectWithError("Le token CSRF est invalide.", 'home', 'index');
    }
}

function displayErrorOrSuccessMessage(): string
{
    $message = '';

    if (isset($_SESSION['success_message'])) {
        $message = sprintf(
            '<p class="success_message">%s</p>',
            htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['success_message']);
    } elseif (isset($_SESSION['success'])) {
        $message = sprintf(
            '<p class="success_message">%s</p>',
            htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['success']);
    } elseif (isset($_SESSION['error_message'])) {
        $message = sprintf(
            '<p class="error_message">%s</p>',
            htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['error_message']);
    } elseif (isset($_SESSION['error'])) {
        $message = sprintf(
            '<p class="error_message">%s</p>',
            htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['error']);
    } elseif (isset($_SESSION['warning_message'])) {
        $message = sprintf(
            '<p class="warning_message">%s</p>',
            htmlspecialchars($_SESSION['warning_message'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['warning_message']);
    } elseif (isset($_SESSION['warning'])) {
        $message = sprintf(
            '<p class="warning_message">%s</p>',
            htmlspecialchars($_SESSION['warning'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['warning']);
    } elseif (isset($_SESSION['info_message'])) {
        $message = sprintf(
            '<p class="info_message">%s</p>',
            htmlspecialchars($_SESSION['info_message'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['info_message']);
    } elseif (isset($_SESSION['information'])) {
        $message = sprintf(
            '<p class="info_message">%s</p>',
            htmlspecialchars($_SESSION['information'], ENT_QUOTES, 'UTF-8')
        );
        unset($_SESSION['information']);
    }

    return $message;
}

function validateString(string $str): bool
{
    return (bool)preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $str);
}

function redirectTo(string $controller, string $action): void
{
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithError(string $message, string $controller, string $action = 'index'): void
{
    checkSession();
    $_SESSION['error'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithSuccess(string $message, string $controller, string $action = 'index'): void
{
    checkSession();
    $_SESSION['success'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithWarning(string $message, string $controller, string $action = 'index'): void
{
    checkSession();
    $_SESSION['warning'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithInformation(string $message, string $controller, string $action = 'index'): void
{
    checkSession();
    $_SESSION['information'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectIfConnected(string $message): void
{
    if (isUserLoggedIn()) {
        redirectWithError($message, 'home', 'index');
    }
}

function checkRole(string $role): void
{
    checkConnect();
    $role = normalizeUserRole($role);
    $controller = strtolower((string)($_GET['controller'] ?? 'home'));
    $action = (string)($_GET['action'] ?? 'index');
    $routePermission = getStaffRoutePermission($controller, $action);

    if ($role === 'admin' && $routePermission !== null) {
        checkPermission($routePermission);
        return;
    }

    if (getCurrentUserRole() !== $role) {
        redirectWithError("Vous n'êtes pas autorisé à accéder à cette page.", 'home', 'index');
    }
}

function isUserLoggedIn(): bool
{
    checkSession();
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

function resolvePublicImageFilename(?string $filename, string $fallback = 'product-placeholder.svg'): string
{
    $filename = ltrim(str_replace('\\', '/', trim((string)$filename)), '/');
    $fallback = ltrim(str_replace('\\', '/', trim($fallback)), '/');
    $imageRoot = realpath(__DIR__ . '/../public/img');

    if ($imageRoot === false || $filename === '' || str_contains($filename, '..')) {
        return $fallback;
    }

    if (str_starts_with($filename, 'public/img/')) {
        $filename = substr($filename, strlen('public/img/'));
    }

    $resolvedImage = realpath($imageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filename));
    $normalizedRoot = rtrim(str_replace('\\', '/', $imageRoot), '/') . '/';
    $normalizedImage = $resolvedImage !== false ? str_replace('\\', '/', $resolvedImage) : '';

    if (
        $resolvedImage === false
        || !is_file($resolvedImage)
        || !str_starts_with($normalizedImage, $normalizedRoot)
    ) {
        return $fallback;
    }

    return $filename;
}

function isUserAuthorized(string $role): bool
{
    if (!isUserLoggedIn() || getCurrentUserRole() !== normalizeUserRole($role)) {
        return false;
    }

    return true;
}

function isAdmin(): bool
{
    return isUserLoggedIn() && getCurrentUserRole() === 'admin';
}

function isCurrentUserLocked(): bool
{
    checkSession();

    if (!isset($_SESSION['user']['id'])) {
        return false;
    }

    require_once __DIR__ . '/../models/User.php';

    return User::isLocked((int)$_SESSION['user']['id']);
}

function renderUiIcon(string $name, string $className = ''): string
{
    $icons = [
        'home' => '<path d="M3.5 10.5 12 3l8.5 7.5"/><path d="M5.5 9.5V21h13V9.5M9.5 21v-6h5v6"/>',
        'support' => '<path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v4a2 2 0 0 0 2 2h1v-7H6a2 2 0 0 0-2 1ZM20 13v4a2 2 0 0 1-2 2h-1v-7h1a2 2 0 0 1 2 1Z"/><path d="M17 19c0 1.1-.9 2-2 2h-3"/>',
        'alert' => '<path d="M10.3 4.1 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 4.1a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'shop' => '<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
        'cart' => '<path d="M3 4h2l2.4 11h9.7l2-8H6"/><circle cx="10" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>',
        'orders' => '<path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/>',
        'invoice' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'payment' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 9h19M6.5 15h4.5"/>',
        'news' => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'logs' => '<path d="M4 4v16h16"/><path d="m7 15 3-3 3 2 5-6"/><path d="M15 8h3v3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 9 19.35a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.07 14H3v-4h.09A1.7 1.7 0 0 0 4.65 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.02A1.7 1.7 0 0 0 10 3.07V3h4v.09A1.7 1.7 0 0 0 15 4.65a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.02A1.7 1.7 0 0 0 20.93 10H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'edit' => '<path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'unlock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 7-2.6"/>',
        'delete' => '<path d="M4 7h16M9 3h6M7 7l1 14h8l1-14M10 11v6M14 11v6"/>',
        'ticket' => '<path d="M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5Z"/><path d="M12 8v8"/>',
        'categories' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'inventory' => '<path d="M4 6h16v14H4zM8 3h8l2 3H6l2-3Z"/><path d="M9 11h6M12 8v6"/>',
        'back' => '<path d="m10 6-6 6 6 6"/><path d="M4 12h16"/>',
        'shield' => '<path d="M12 3 20 6v6c0 5-3.4 8-8 9-4.6-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M15 8l2 2M17 6l2 2"/>',
        'add' => '<path d="M12 5v14M5 12h14"/>',
    ];

    $paths = $icons[$name] ?? '';
    if ($paths === '') {
        return '';
    }

    $classAttribute = trim($className) !== ''
        ? ' class="' . htmlspecialchars(trim($className), ENT_QUOTES, 'UTF-8') . '"'
        : '';

    return '<svg' . $classAttribute . ' viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

function renderUserActionIcon(string $type): string
{
    return renderUiIcon(match ($type) {
        'show' => 'eye',
        'payment' => 'payment',
        'lock' => 'lock',
        'unlock' => 'unlock',
        'delete' => 'delete',
        default => $type,
    });
}

function sendSecurityHeaders(): void
{
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Origin-Agent-Cluster: ?1');
    header('X-Request-ID: ' . getRequestId());

    $csp = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; font-src 'self'; style-src 'self'; script-src 'self'";

    if (isHttpsRequest()) {
        $csp .= '; upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header('Content-Security-Policy: ' . $csp);
}

function sanitizeInternalRedirect(string $url, string $fallback = 'index.php?controller=home&action=index'): string
{
    $url = trim($url);

    if ($url === '') {
        return $fallback;
    }

    if (preg_match('/[\r\n]/', $url)) {
        return $fallback;
    }

    $parts = parse_url($url);

    if ($parts === false) {
        return $fallback;
    }

    if (
        isset($parts['scheme']) ||
        isset($parts['host']) ||
        isset($parts['user']) ||
        isset($parts['pass']) ||
        isset($parts['port'])
    ) {
        return $fallback;
    }

    $path = $parts['path'] ?? '';

    if ($path !== 'index.php') {
        return $fallback;
    }

    $safeUrl = 'index.php';

    if (!empty($parts['query'])) {
        $safeUrl .= '?' . $parts['query'];
    }

    if (!empty($parts['fragment'])) {
        $safeUrl .= '#' . $parts['fragment'];
    }

    return $safeUrl;
}
