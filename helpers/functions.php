<?php
require_once __DIR__ . '/../config/config.php';

function checkSession() : void {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function checkConnect(): void {
    checkSession();
    // Durée max d'inactivité (20mn)
    $timeout = 1200;

    // Vérifie l'inactivité
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_destroy();
        redirectWithWarning("Session expirée. Veuillez vous reconnecter.", "user", "login");
    }

    // Mise à jour de l'activité
    $_SESSION['last_activity'] = time();

    // Vérifie que l'utilisateur est connecté
    if (!isset($_SESSION['user'])) {
        redirectWithError("Veuillez vous connecter pour accéder à cette page.", "user", "login");
    }
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrfToken(): void {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        redirectWithError("Le token CSRF est invalide.", 'home', 'index');
    }
}

/* DISPLAY */

function displayErrorOrSuccessMessage() : string {
    $message = '';

    if (isset($_SESSION['success'])) {
        $message = sprintf('<p class="success_message">%s</p>', $_SESSION['success']);
        unset($_SESSION['success']);
    } elseif (isset($_SESSION['error'])) {
        $message = sprintf('<p class="error_message">%s</p>', $_SESSION['error']);
        unset($_SESSION['error']);
    } elseif (isset($_SESSION['warning'])) {
        $message = sprintf('<p class="warning_message">%s</p>', $_SESSION['warning']);
        unset($_SESSION['warning']);
    } elseif (isset($_SESSION['information'])) {
        $message = sprintf('<p class="info_message">%s</p>', $_SESSION['information']);
        unset($_SESSION['information']);
    }
    return $message;
}

/* GESTION DES INPUTS */
function validateString(string $str) : bool {
    return preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $str);
}

/* GESTION DES REDIRECTIONS */
function redirectTo(string $controller, string $action) {
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithError(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $symbole = "⛔ ";
    $message = $symbole . $message;
    $_SESSION['error'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithSuccess(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $symbole = "✅ ";
    $message = $symbole . $message;
    $_SESSION['success'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}
function redirectWithWarning(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $symbole = "⚠️ ";
    $message = $symbole . $message;
    $_SESSION['warning'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectWithInformation(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $symbole = "🪧 ";
    $message = $symbole . $message;
    $_SESSION['information'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit();
}

function redirectIfConnected(string $message) : void {
    if (isUserLoggedIn()) {
        redirectWithError($message, 'home', 'index');
    }
}

function checkRole(string $role): void {
    checkConnect();
    $role = strtolower($role);
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $role) {
        redirectWithError("Vous n'êtes pas autorisé à accéder à cette page.", 'home', 'index');
    }
}

/* CONTROLE DE SESSION */
function isUserLoggedIn(): bool
{
    checkSession();
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

function isUserAuthorized(string $role): bool
{
    if (!isUserLoggedIn() || $_SESSION['user']['role'] !== $role) {
        return false;
    }
    return true;
}

function isAdmin(): bool
{
    return isUserLoggedIn()
        && isset($_SESSION['user']['role'])
        && $_SESSION['user']['role'] === 'admin';
}
