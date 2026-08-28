<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';

$readRequiredEnvironment = static function (string $name): string {
    $value = trim((string)getenv($name));

    if ($value === '') {
        throw new RuntimeException('Variable obligatoire absente : ' . $name . '.');
    }

    return $value;
};

$username = preg_replace('/\s+/', '', $readRequiredEnvironment('CKSGO_ADMIN_USERNAME'));
$firstname = $readRequiredEnvironment('CKSGO_ADMIN_FIRSTNAME');
$lastname = $readRequiredEnvironment('CKSGO_ADMIN_LASTNAME');
$email = strtolower($readRequiredEnvironment('CKSGO_ADMIN_EMAIL'));
$password = (string)getenv('CKSGO_ADMIN_PASSWORD');
$unit = trim((string)(getenv('CKSGO_ADMIN_UNIT') ?: 'mineurs'));

if ($username === '' || mb_strlen($username) > 50) {
    throw new RuntimeException('Le pseudo administrateur est invalide.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException("L'adresse e-mail administrateur est invalide.");
}

if (!in_array($unit, ['mineurs', 'vif', 'syndicat'], true)) {
    throw new RuntimeException('CKSGO_ADMIN_UNIT doit valoir mineurs, vif ou syndicat.');
}

$passwordError = validatePasswordPolicy($password);

if ($passwordError !== null) {
    throw new RuntimeException($passwordError);
}

if (!User::checkUnicity('username', $username) || !User::checkUnicity('email', $email)) {
    throw new RuntimeException('Le pseudo ou l’adresse e-mail existe déjà.');
}

$userId = User::createByAdmin([
    'username' => $username,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'email' => $email,
    'unit' => $unit,
    'password_hash' => hashPassword($password),
    'role' => 'admin',
    'note' => 0,
    'is_active' => 1,
    'is_locked' => 0,
    'activation_token' => null,
    'email_verified_at' => date('Y-m-d H:i:s'),
]);

echo 'Administrateur créé avec l’identifiant #' . $userId . ".\n";
