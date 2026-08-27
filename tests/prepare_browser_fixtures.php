<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

$databaseName = trim((string)getenv('CKSGO_TEST_DB'));
if ($databaseName === '' || $databaseName === DB_NAME) {
    throw new RuntimeException('CKSGO_TEST_DB doit cibler une base de test distincte de la base active.');
}

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . $databaseName . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$password = 'Audit CKS GO 2026!';
$passwordHash = hashPassword($password);
$roles = ['user', 'assistant', 'gestionnaire', 'responsable', 'admin'];
$statement = $db->prepare("
    INSERT INTO users (
        username, firstname, lastname, email, unit, password_hash,
        role, note, is_active, is_locked, is_banned, email_verified_at
    ) VALUES (?, 'Audit', ?, ?, 'mineurs', ?, ?, 0, 1, 0, 0, NOW())
    ON DUPLICATE KEY UPDATE
        firstname = VALUES(firstname),
        lastname = VALUES(lastname),
        email = VALUES(email),
        password_hash = VALUES(password_hash),
        role = VALUES(role),
        is_active = 1,
        is_locked = 0,
        is_banned = 0,
        email_verified_at = NOW(),
        failed_login_attempts = 0,
        last_failed_login_at = NULL,
        login_locked_until = NULL
");

foreach ($roles as $role) {
    $username = '__audit_' . $role;
    $statement->execute([
        $username,
        ucfirst($role),
        $username . '@example.test',
        $passwordHash,
        $role,
    ]);
}

echo "Browser fixtures: OK\n";
