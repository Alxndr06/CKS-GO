<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

$databaseName = trim((string)getenv('CKSGO_TEST_DB'));
$command = $argv[1] ?? '--create';
$allowedCommands = ['--create', '--reset', '--drop'];

if (!in_array($command, $allowedCommands, true)) {
    throw new InvalidArgumentException('Commande attendue : --create, --reset ou --drop.');
}

if (
    $databaseName === ''
    || $databaseName === DB_NAME
    || preg_match('/^cksgo_test_[a-z0-9_]{1,40}$/', $databaseName) !== 1
) {
    throw new RuntimeException(
        'CKSGO_TEST_DB doit être distincte de la base active et respecter le format cksgo_test_*.'
    );
}

$server = new PDO(
    'mysql:host=' . DB_HOST . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]
);
$quotedDatabase = '`' . $databaseName . '`';

if ($command === '--reset' || $command === '--drop') {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}

if ($command === '--drop') {
    echo "Base de test supprimée.\n";
    exit(0);
}

$server->exec(
    'CREATE DATABASE IF NOT EXISTS ' . $quotedDatabase
    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . $databaseName . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]
);

$schemaPath = __DIR__ . '/../database/schema.sql';
$schema = is_file($schemaPath) ? (string)file_get_contents($schemaPath) : '';

if ($schema === '') {
    throw new RuntimeException('Le schéma database/schema.sql est absent ou vide.');
}

$db->exec($schema);
$db->beginTransaction();

try {
    $passwordHash = hashPassword('Audit CKS GO 2026!');
    $users = [
        [1, '__audit_user', 'Audit', 'User', '__audit_user@example.test', 'user'],
        [2, '__audit_assistant', 'Audit', 'Assistant', '__audit_assistant@example.test', 'assistant'],
        [3, '__audit_gestionnaire', 'Audit', 'Gestionnaire', '__audit_gestionnaire@example.test', 'gestionnaire'],
        [4, '__audit_responsable', 'Audit', 'Responsable', '__audit_responsable@example.test', 'responsable'],
        [5, '__audit_admin', 'Audit', 'Admin', '__audit_admin@example.test', 'admin'],
        [6, '__audit_other', 'Audit', 'Other', '__audit_other@example.test', 'user'],
    ];
    $userStatement = $db->prepare("
        INSERT INTO users (
            id, username, firstname, lastname, email, unit, password_hash,
            role, note, is_active, is_locked, is_banned, email_verified_at
        ) VALUES (?, ?, ?, ?, ?, 'mineurs', ?, ?, 0, 1, 0, 0, NOW())
    ");

    foreach ($users as [$id, $username, $firstname, $lastname, $email, $role]) {
        $userStatement->execute([$id, $username, $firstname, $lastname, $email, $passwordHash, $role]);
    }

    $db->exec("
        INSERT INTO categories (id, name, slug, is_active, sort_order)
        VALUES (1, 'Tests', 'tests', 1, 1)
    ");
    $db->exec("
        INSERT INTO products (
            id, name, description, price, restricted, is_active, visibility, category_id
        ) VALUES
            (
                1, 'Produit de contrôle', 'Produit générique réservé aux tests automatisés.',
                2.50, 0, 1, 'public', 1
            ),
            (
                2, 'Biscuits au germe de blé aux pépites de chocolat',
                'Description volontairement longue pour vérifier la réduction visuelle avec des points de suspension.',
                1.20, 0, 1, 'public', 1
            ),
            (
                3, 'Boisson de contrôle', 'Une seconde carte pour valider la grille responsive.',
                0.90, 0, 1, 'public', 1
            )
    ");
    $db->exec("
        INSERT INTO product_variants (
            id, product_id, sort_order, sku, name, price, stock_quantity,
            low_stock_threshold, is_active
        ) VALUES
            (1, 1, 1, 'AUDIT-STD', 'Standard', 2.50, 100, 5, 1),
            (2, 1, 2, 'AUDIT-ALT', 'Alternative', 2.80, 100, 5, 1),
            (3, 2, 1, 'AUDIT-BIS', 'Standard', 1.20, 100, 5, 1),
            (4, 3, 1, 'AUDIT-DRK', 'Standard', 0.90, 100, 5, 1)
    ");
    $db->exec("INSERT INTO carts (id, user_id, session_id, is_locked) VALUES (1, 1, NULL, 0)");
    $db->exec("INSERT INTO cart_items (id, cart_id, variant_id, quantity) VALUES (1, 1, 1, 1)");
    $db->exec("
        INSERT INTO orders (id, user_id, status, currency, total_price)
        VALUES
            (137, 6, 'paid', 'EUR', 2.50),
            (138, 1, 'pending_payment', 'EUR', 5.00)
    ");
    $db->exec("
        INSERT INTO order_items (
            id, order_id, product_id, variant_id, line_type, product_name_snapshot,
            variant_name_snapshot, sku_snapshot, quantity, unit_price, currency
        ) VALUES
            (1, 137, 1, 1, 'product', 'Produit de contrôle', 'Standard', 'AUDIT-STD', 1, 2.50, 'EUR'),
            (2, 138, 1, 1, 'product', 'Produit de contrôle', 'Standard', 'AUDIT-STD', 2, 2.50, 'EUR')
    ");
    $db->exec("
        INSERT INTO payments (
            id, order_id, payment_author_id, admin_id, amount_paid, method, status, currency
        ) VALUES (1, 137, 6, 5, 2.50, 'audit', 'captured', 'EUR')
    ");

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    throw $exception;
}

$tableCount = (int)$db->query("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
")->fetchColumn();

if ($tableCount !== 33) {
    throw new RuntimeException('Le schéma de test doit contenir exactement 33 tables, obtenu : ' . $tableCount . '.');
}

echo "Base de test prête : 33 tables et fixtures génériques.\n";
