<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/UserBalance.php';

function alertRefundAssert(bool $condition, string $message): void
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

$adminId = (int)$db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId <= 0) {
    throw new RuntimeException('Un administrateur actif est nécessaire dans la base de test.');
}

$variants = $db->query("
    SELECT pv.id, pv.product_id, pv.stock_quantity, pv.price
    FROM product_variants pv
    INNER JOIN products p ON p.id = pv.product_id
    WHERE pv.is_active = 1
      AND p.is_active = 1
      AND pv.archived_at IS NULL
      AND p.archived_at IS NULL
      AND pv.stock_quantity > 0
      AND pv.price > 0
    ORDER BY pv.price ASC, pv.id ASC
    LIMIT 2
")->fetchAll(PDO::FETCH_ASSOC);

if (count($variants) < 2) {
    throw new RuntimeException('Deux variantes actives et disponibles sont nécessaires dans la base de test.');
}

$suffix = strtolower(bin2hex(random_bytes(5)));
$userId = 0;
$orderId = 0;
$alertId = 0;
$refundIds = [];
$stockBefore = [];
foreach ($variants as $variant) {
    $stockBefore[(int)$variant['id']] = (int)$variant['stock_quantity'];
}
$openingBalanceCents = -100000;

try {
    $insertUser = $db->prepare("
        INSERT INTO users (
            username, lastname, firstname, email, unit, password_hash,
            is_active, is_locked, email_verified_at, role, note
        )
        VALUES (?, 'Test', 'Signalement', ?, 'mineurs', ?, 1, 0, NOW(), 'user', ?)
    ");
    $username = '__alert_refund_' . $suffix;
    $insertUser->execute([
        $username,
        $username . '@example.test',
        password_hash('test-password', PASSWORD_DEFAULT),
        UserBalance::centsToDecimal($openingBalanceCents),
    ]);
    $userId = (int)$db->lastInsertId();

    $orderId = Order::createAdminMultiChargeForUser(
        $userId,
        array_map(
            static fn(array $variant): array => ['variant_id' => (int)$variant['id'], 'quantity' => 1],
            $variants
        ),
        $adminId
    );
    $orderItemStmt = $db->prepare('SELECT id, variant_id FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $orderItemStmt->execute([$orderId]);
    $orderItems = $orderItemStmt->fetchAll(PDO::FETCH_ASSOC);
    $orderItemIds = array_map(static fn(array $item): int => (int)$item['id'], $orderItems);

    alertRefundAssert(count($orderItemIds) === 2, 'Les lignes de commande de test sont introuvables.');
    alertRefundAssert(
        (string)$db->query('SELECT status FROM orders WHERE id = ' . $orderId)->fetchColumn() === 'paid',
        "La commande de test n'a pas été soldée par l'avoir."
    );

    $alertId = Alert::createUserReport([
        'type' => 'damaged_product',
        'priority' => 'medium',
        'source_context' => 'user_order',
        'order_id' => $orderId,
        'order_item_ids' => $orderItemIds,
        'reported_by_user_id' => $userId,
        'message' => 'Plusieurs produits endommagés pendant le test.',
    ]);

    $alert = Alert::findById($alertId);
    alertRefundAssert((int)($alert['order_item_id'] ?? 0) === 0, 'Une sélection multiple ne doit pas être réduite à une seule ligne.');
    alertRefundAssert((int)($alert['reported_by_user_id'] ?? 0) === $userId, 'Le signalant est incorrect.');
    alertRefundAssert(
        (int)$db->query('SELECT COUNT(*) FROM alert_items WHERE alert_id = ' . $alertId)->fetchColumn() === 2,
        'Les deux produits signalés ne sont pas mémorisés.'
    );

    $sameAlertId = Alert::createUserReport([
        'type' => 'damaged_product',
        'priority' => 'medium',
        'source_context' => 'user_order',
        'order_id' => $orderId,
        'all_products' => true,
        'reported_by_user_id' => $userId,
        'message' => 'Confirmation sur toute la commande.',
    ]);
    alertRefundAssert($sameAlertId === $alertId, "L'option toute la commande a créé un doublon actif.");

    $context = Alert::getRefundContext($alertId);
    alertRefundAssert(!empty($context['can_refund']), 'Les produits payés devraient être remboursables depuis le signalement.');
    alertRefundAssert(count((array)$context['items']) === 2, 'Le remboursement ne reprend pas toute la sélection signalée.');
    alertRefundAssert(count((array)$context['refundable_items']) === 2, 'Une ligne signalée manque dans les choix de remboursement.');

    $firstResult = Alert::refundReportedItems($alertId, [$orderItemIds[0] => 1], $adminId, 'consumed');
    $refundIds[] = (int)($firstResult['refunds'][0]['refund_id'] ?? 0);
    alertRefundAssert($refundIds[0] > 0, "Le premier remboursement n'a pas été enregistré.");
    alertRefundAssert(empty($firstResult['alert_fully_processed']), 'Un remboursement partiel ne doit pas clôturer tout le signalement.');
    alertRefundAssert(
        (string)$db->query('SELECT status FROM alerts WHERE id = ' . $alertId)->fetchColumn() === 'in_progress',
        "Le signalement n'est pas resté en cours après un remboursement partiel."
    );

    $partialContext = Alert::getRefundContext($alertId);
    alertRefundAssert(count((array)$partialContext['refunds']) === 1, 'Le premier reçu de remboursement est absent.');
    alertRefundAssert(count((array)$partialContext['refundable_items']) === 1, 'La ligne déjà remboursée est encore proposée.');

    $secondResult = Alert::refundReportedItems($alertId, [$orderItemIds[1] => 1], $adminId, 'consumed');
    $refundIds[] = (int)($secondResult['refunds'][0]['refund_id'] ?? 0);
    alertRefundAssert($refundIds[1] > 0, "Le second remboursement n'a pas été enregistré.");
    alertRefundAssert(!empty($secondResult['alert_fully_processed']), 'Le remboursement de toutes les lignes doit clôturer le signalement.');
    alertRefundAssert(
        (int)$db->query('SELECT COUNT(*) FROM alert_refunds WHERE alert_id = ' . $alertId)->fetchColumn() === 2,
        "Les liens d'audit entre signalement et remboursements sont incomplets."
    );
    alertRefundAssert(
        (string)$db->query('SELECT status FROM alerts WHERE id = ' . $alertId)->fetchColumn() === 'resolved',
        "Le signalement n'est pas clôturé après remboursement complet."
    );
    alertRefundAssert(
        UserBalance::decimalToCents($db->query('SELECT note FROM users WHERE id = ' . $userId)->fetchColumn()) === $openingBalanceCents,
        "L'avoir du signalant n'a pas été restauré."
    );

    foreach ($variants as $variant) {
        $variantId = (int)$variant['id'];
        $currentStock = (int)$db->query('SELECT stock_quantity FROM product_variants WHERE id = ' . $variantId)->fetchColumn();
        alertRefundAssert($currentStock === $stockBefore[$variantId] - 1, 'Un produit consommé a été remis au stock.');
    }

    $duplicateBlocked = false;
    try {
        Alert::refundReportedItem($alertId, $orderItemIds[0], 1, $adminId, 'consumed');
    } catch (RuntimeException $exception) {
        $duplicateBlocked = str_contains($exception->getMessage(), 'déjà');
    }
    alertRefundAssert($duplicateBlocked, 'Une même ligne signalée peut être remboursée deux fois.');

    echo "AlertRefundWorkflowTest: OK\n";
} finally {
    if ($alertId > 0) {
        $db->prepare('DELETE FROM alert_refunds WHERE alert_id = ?')->execute([$alertId]);
        $db->prepare('DELETE FROM alerts WHERE id = ?')->execute([$alertId]);
    }
    foreach ($refundIds as $refundId) {
        if ($refundId > 0) {
            $db->prepare('DELETE FROM refunds WHERE id = ?')->execute([$refundId]);
        }
    }
    if ($orderId > 0) {
        $db->prepare('DELETE FROM inventory_movements WHERE order_id = ?')->execute([$orderId]);
        $db->prepare('DELETE FROM payments WHERE order_id = ?')->execute([$orderId]);
        $db->prepare('DELETE FROM user_balance_movements WHERE order_id = ?')->execute([$orderId]);
        $db->prepare('DELETE FROM logs WHERE details LIKE ?')->execute(['%Commande #' . $orderId . '%']);
        $db->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    }
    if ($userId > 0) {
        $db->prepare('DELETE FROM user_balance_movements WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM payments WHERE payment_author_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM payment_batches WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
    foreach ($stockBefore as $variantId => $stockQuantity) {
        $db->prepare('UPDATE product_variants SET stock_quantity = ? WHERE id = ?')
            ->execute([$stockQuantity, $variantId]);
    }
    $modelDb->setValue(null, null);
}
