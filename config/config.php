<?php

$localConfig = __DIR__ . '/local.php';
$ignoreLocalConfig = filter_var(
    getenv('CKSGO_IGNORE_LOCAL_CONFIG') ?: false,
    FILTER_VALIDATE_BOOLEAN
);

if (!$ignoreLocalConfig && is_file($localConfig)) {
    require_once $localConfig;
}

$env = static function (string $name, mixed $default = null): mixed {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
};

$envBool = static function (string $name, bool $default) use ($env): bool {
    $value = $env($name);

    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
};

// DATA BASE
if (!defined('DB_HOST')) {
    define('DB_HOST', (string)$env('CKSGO_DB_HOST', 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string)$env('CKSGO_DB_NAME', 'cksgo_db'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string)$env('CKSGO_DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string)$env('CKSGO_DB_PASS', ''));
}

// ENVIRONNEMENT
if (!defined('APP_ENV')) {
    define('APP_ENV', (string)$env('CKSGO_APP_ENV', 'dev'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $envBool('CKSGO_APP_DEBUG', APP_ENV !== 'prod'));
}

if (!defined('APP_SESSION_NAME')) {
    define('APP_SESSION_NAME', (string)$env('CKSGO_SESSION_NAME', 'CKSGOSESSID'));
}

if (!defined('TRUST_PROXY_HEADERS')) {
    define('TRUST_PROXY_HEADERS', $envBool('CKSGO_TRUST_PROXY_HEADERS', false));
}

// URL DE L'APPLICATION
if (!defined('APP_URL')) {
    define('APP_URL', (string)$env(
        'CKSGO_APP_URL',
        APP_ENV === 'dev' ? 'http://localhost/CKS_GO' : 'https://cks.aulong.fr/v2'
    ));
}

// Détection automatique du chemin
if (!defined('BASE_URL')) {
    $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $basePath = str_replace('\\', '/', $basePath);
    define('BASE_URL', $basePath === '/' ? '' : rtrim($basePath, '/'));
}

// MAILING
if (!defined('MAIL_ENABLED')) {
    define('MAIL_ENABLED', $envBool('CKSGO_MAIL_ENABLED', true));
}

if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', (string)$env('CKSGO_MAIL_HOST', 'smtp.example.com'));
}

if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', (int)$env('CKSGO_MAIL_PORT', 465));
}

if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', (string)$env('CKSGO_MAIL_USERNAME', 'noreply@example.com'));
}

if (!defined('MAIL_PASSWORD')) {
    define('MAIL_PASSWORD', (string)$env('CKSGO_MAIL_PASSWORD', ''));
}

if (!defined('MAIL_ENCRYPTION')) {
    define('MAIL_ENCRYPTION', (string)$env('CKSGO_MAIL_ENCRYPTION', 'ssl'));
}

if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', (string)$env('CKSGO_MAIL_FROM_ADDRESS', 'noreply@example.com'));
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', (string)$env('CKSGO_MAIL_FROM_NAME', 'CKS GO'));
}

if (!defined('MAIL_REPLY_TO')) {
    define('MAIL_REPLY_TO', (string)$env('CKSGO_MAIL_REPLY_TO', 'noreply@example.com'));
}

if (!defined('MAIL_REPLY_TO_NAME')) {
    define('MAIL_REPLY_TO_NAME', (string)$env('CKSGO_MAIL_REPLY_TO_NAME', 'CKS GO'));
}

if (!defined('MAIL_ADMIN_NOTIFICATION')) {
    define('MAIL_ADMIN_NOTIFICATION', (string)$env('CKSGO_MAIL_ADMIN_NOTIFICATION', 'admin@example.com'));
}

unset($env, $envBool, $ignoreLocalConfig);
