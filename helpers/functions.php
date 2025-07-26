<?php

function checkSession() : void {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
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
        die("Invalid CSRF token.");
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
    }
    return $message;
}

/* GESTION DES INPUTS */
function validateString(string $str) : string {
    return preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $str);
}

/* GESTION DES REDIRECTIONS */
function redirectTo($controller, $action) {
    header("Location: index.php?controller=$controller&action=$action");
    exit;
}

function redirectWithError(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $_SESSION['error'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit;
}

function redirectWithSuccess(string $message, string $controller, string $action = 'index'): void {
    checkSession();
    $_SESSION['success'] = $message;
    header("Location: index.php?controller=$controller&action=$action");
    exit;
}

/* CONTROLE DE SESSION */
function isUserLoggedIn(): bool {
    checkSession();
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}