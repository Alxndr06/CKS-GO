<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Inventory.php';

function inventoryAssertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' / attendu=' . var_export($expected, true) . ' obtenu=' . var_export($actual, true)
        );
    }
}

function inventoryFetchScalar(PDO $db, string $sql, array $params = [])
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
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

$adminId = (int)inventoryFetchScalar($db, "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
if ($adminId <= 0) {
    throw new RuntimeException('Un compte administrateur est nécessaire dans la base de test.');
}

$suffix = strtoupper(bin2hex(random_bytes(4)));
$productId = 0;
$variantId = 0;

try {
    $productId = Product::createProductWithVariant(
        [
            'name' => '__TEST INVENTAIRE ' . $suffix,
            'description' => 'Produit temporaire de validation du stock.',
            'category_id' => null,
            'image' => '',
            'is_active' => 1,
            'visibility' => 'admin_only',
        ],
        [
            'name' => 'Standard',
            'flavor' => '',
            'sku' => '',
            'price' => 1.25,
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_active' => 1,
            'image' => '',
            'sort_order' => 0,
        ],
        $adminId
    );

    $variantId = (int)inventoryFetchScalar(
        $db,
        'SELECT id FROM product_variants WHERE product_id = ? LIMIT 1',
        [$productId]
    );
    $sku = (string)inventoryFetchScalar($db, 'SELECT sku FROM product_variants WHERE id = ?', [$variantId]);

    inventoryAssertSame(true, $variantId > 0, 'La variante initiale n’a pas été créée');
    inventoryAssertSame(true, $sku !== '', 'Le SKU automatique est vide');
    inventoryAssertSame(1, (int)inventoryFetchScalar(
        $db,
        'SELECT COUNT(*) FROM inventory_movements WHERE variant_id = ?',
        [$variantId]
    ), 'Le stock initial n’a pas généré de mouvement');

    Product::adjustVariantStock($variantId, 'increase', 3, 'restock', 'Réassort test', $adminId);
    Product::adjustVariantStock($variantId, 'decrease', 2, 'correction', 'Correction test', $adminId);
    Product::adjustVariantStock($variantId, 'set', 4, 'count', 'Comptage test', $adminId);

    inventoryAssertSame(4, (int)inventoryFetchScalar(
        $db,
        'SELECT stock_quantity FROM product_variants WHERE id = ?',
        [$variantId]
    ), 'Le stock final est incorrect');
    inventoryAssertSame(4, (int)inventoryFetchScalar(
        $db,
        'SELECT COUNT(*) FROM inventory_movements WHERE variant_id = ?',
        [$variantId]
    ), 'L’historique des ajustements est incomplet');

    $dashboard = Product::getInventoryDashboard($sku, 'all');
    inventoryAssertSame(1, count($dashboard['items']), 'La recherche par SKU ne retrouve pas la variante');

    Product::deleteVariant($variantId, $adminId);
    inventoryAssertSame(true, inventoryFetchScalar(
        $db,
        'SELECT archived_at IS NOT NULL FROM product_variants WHERE id = ?',
        [$variantId]
    ) == 1, 'La variante n’a pas été archivée');
    inventoryAssertSame(4, (int)inventoryFetchScalar(
        $db,
        'SELECT COUNT(*) FROM inventory_movements WHERE variant_id = ?',
        [$variantId]
    ), 'L’archivage de la variante a supprimé son historique');

    Product::restoreVariant($variantId, $adminId);
    Product::deleteProduct($productId, $adminId);

    $editBlocked = false;
    try {
        Product::updateProduct($productId, [
            'category_id' => null,
            'name' => '__TEST MODIFICATION INTERDITE',
            'description' => '',
            'image' => '',
            'is_active' => 1,
            'visibility' => 'admin_only',
        ], $adminId);
    } catch (RuntimeException $e) {
        $editBlocked = true;
    }
    inventoryAssertSame(true, $editBlocked, 'Un produit archivé peut encore être modifié');

    $db->beginTransaction();
    Inventory::adjustStock($db, $variantId, 1, 'refund', [
        'admin_id' => $adminId,
        'allow_archived' => true,
        'note' => 'Simulation de retour après archivage',
    ]);
    $db->commit();

    inventoryAssertSame(5, (int)inventoryFetchScalar(
        $db,
        'SELECT stock_quantity FROM product_variants WHERE id = ?',
        [$variantId]
    ), 'Un retour lié à une commande archivée ne restaure pas le stock');
    inventoryAssertSame(5, (int)inventoryFetchScalar(
        $db,
        'SELECT COUNT(*) FROM inventory_movements WHERE variant_id = ?',
        [$variantId]
    ), 'Le retour après archivage n’a pas été historisé');

    Product::restoreProduct($productId, $adminId);

    echo "InventoryWorkflowTest: OK\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    if ($productId > 0) {
        $cleanup = $db->prepare('DELETE FROM products WHERE id = ?');
        $cleanup->execute([$productId]);
    }

    $modelDb->setValue(null, null);
}
