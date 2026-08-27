<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/AccessBan.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Setting.php';

function accessAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

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

$modelDb = new ReflectionProperty(Model::class, 'db');
$modelDb->setAccessible(true);
$modelDb->setValue(null, $db);

$adminId = (int)$db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($adminId <= 0) {
    throw new RuntimeException('Un compte administrateur est nécessaire dans la base de test.');
}

$suffix = strtolower(bin2hex(random_bytes(5)));
$productIds = [];
$banId = 0;
$ipBanId = 0;
$userId = 0;
$originalSettings = [
    'maintenance_mode' => Setting::get('maintenance_mode', '0'),
    'maintenance_started_at' => Setting::get('maintenance_started_at', ''),
    'maintenance_last_admin_activity_at' => Setting::get('maintenance_last_admin_activity_at', ''),
];

try {
    $banEmail = 'blocked-' . $suffix . '@example.test';
    $banId = AccessBan::create('email', strtoupper($banEmail), 'Test automatisé', $adminId);
    accessAssert($banId > 0, 'Le bannissement e-mail n’a pas été créé.');
    accessAssert(AccessBan::isEmailBanned($banEmail), 'Le bannissement e-mail n’est pas détecté.');
    accessAssert(AccessBan::deleteById($banId), 'Le bannissement e-mail n’a pas été supprimé.');
    $banId = 0;
    accessAssert(!AccessBan::isEmailBanned($banEmail), 'Le débannissement e-mail n’est pas pris en compte.');

    $ipBanId = AccessBan::create('ip', '192.0.2.42', 'Test automatisé IP', $adminId);
    accessAssert($ipBanId > 0, 'Le bannissement IP n’a pas été créé.');
    accessAssert(AccessBan::isIpBanned('192.0.2.42'), 'Le bannissement IP n’est pas détecté.');
    accessAssert(AccessBan::deleteById($ipBanId), 'Le bannissement IP n’a pas été supprimé.');
    $ipBanId = 0;
    accessAssert(!AccessBan::isIpBanned('192.0.2.42'), 'Le débannissement IP n’est pas pris en compte.');

    $invalidIpRejected = false;
    try {
        AccessBan::create('ip', '999.999.999.999', 'IP invalide', $adminId);
    } catch (InvalidArgumentException) {
        $invalidIpRejected = true;
    }
    accessAssert($invalidIpRejected, 'Une adresse IP invalide peut être bannie.');

    foreach (['public', 'authenticated', 'admin_only'] as $visibility) {
        $productIds[] = Product::createProduct([
            'name' => '__AUDIENCE_' . $suffix . '_' . $visibility,
            'description' => 'Validation automatisée des audiences.',
            'category_id' => null,
            'image' => '',
            'is_active' => 1,
            'visibility' => $visibility,
        ], [
            'name' => 'Standard',
            'flavor' => '',
            'sku' => '',
            'price' => 1,
            'stock_quantity' => 1,
            'low_stock_threshold' => 0,
            'sort_order' => 0,
            'is_active' => 1,
            'image' => '',
        ], $adminId);
    }

    accessAssert(count(Product::search(null, '__AUDIENCE_' . $suffix, 'guest')) === 1, 'Un visiteur voit un produit non public.');
    accessAssert(count(Product::search(null, '__AUDIENCE_' . $suffix, 'authenticated')) === 2, 'Un membre ne voit pas les bonnes audiences.');
    accessAssert(count(Product::search(null, '__AUDIENCE_' . $suffix, 'staff')) === 3, 'Le staff ne voit pas tout le catalogue actif.');

    $username = '__access_test_' . $suffix;
    $insertUser = $db->prepare("
        INSERT INTO users (
            username, lastname, firstname, email, unit, password_hash,
            is_active, is_locked, email_verified_at, role, note
        )
        VALUES (?, 'Test', 'Accès', ?, 'mineurs', ?, 1, 0, NOW(), 'user', 0)
    ");
    $insertUser->execute([$username, $username . '@example.test', password_hash('test-password', PASSWORD_DEFAULT)]);
    $userId = (int)$db->lastInsertId();

    $publicProductId = (int)$productIds[0];
    $variantStmt = $db->prepare('SELECT id FROM product_variants WHERE product_id = ? ORDER BY id ASC LIMIT 1');
    $variantStmt->execute([$publicProductId]);
    $variantId = (int)$variantStmt->fetchColumn();
    Cart::addItem($userId, $publicProductId, $variantId, 1);
    $db->prepare("UPDATE products SET visibility = 'admin_only' WHERE id = ?")->execute([$publicProductId]);

    $checkoutWasBlocked = false;
    try {
        Order::createFromCart($userId);
    } catch (RuntimeException $exception) {
        $checkoutWasBlocked = str_contains($exception->getMessage(), 'staff');
    }
    accessAssert($checkoutWasBlocked, 'Un ancien panier permet encore de commander un produit réservé au staff.');

    Setting::setMany([
        'maintenance_mode' => '1',
        'maintenance_started_at' => date('Y-m-d H:i:s', time() - 1300),
        'maintenance_last_admin_activity_at' => date('Y-m-d H:i:s', time() - 1300),
    ]);
    accessAssert(!isMaintenanceModeEnabled(), 'La maintenance ne s’arrête pas après 20 minutes sans activité admin.');
    accessAssert(!Setting::getBool('maintenance_mode', true), 'Le réglage de maintenance reste actif après expiration.');

    echo "AccessControlWorkflowTest: OK\n";
} finally {
    if ($banId > 0) {
        $stmt = $db->prepare('DELETE FROM access_bans WHERE id = ?');
        $stmt->execute([$banId]);
    }

    if ($ipBanId > 0) {
        $stmt = $db->prepare('DELETE FROM access_bans WHERE id = ?');
        $stmt->execute([$ipBanId]);
    }

    if ($userId > 0) {
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
    }

    foreach ($productIds as $productId) {
        $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$productId]);
    }

    Setting::setMany($originalSettings);
    $modelDb->setValue(null, null);
}
