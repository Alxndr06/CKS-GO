<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/UserBalance.php';
require_once __DIR__ . '/../services/PaymentService.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' / attendu=' . var_export($expected, true) . ' obtenu=' . var_export($actual, true));
    }
}

function fetchScalar(PDO $db, string $sql, array $params = [])
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

$suffix = bin2hex(random_bytes(5));
$username = '__finance_test_' . $suffix;
$email = $username . '@example.test';
$userId = 0;
$orderIds = [];
$variantId = 0;
$variantStockBefore = null;

try {
    assertSameValue(1234, UserBalance::decimalToCents('12.34'), 'Conversion en centimes incorrecte');
    assertSameValue('-12.34', UserBalance::centsToDecimal(-1234), 'Conversion en décimal incorrecte');

    $adminId = (int)fetchScalar($db, "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($adminId <= 0) {
        throw new RuntimeException('Un compte administrateur est nécessaire pour le test.');
    }

    $insertUser = $db->prepare("
        INSERT INTO users (
            username, lastname, firstname, email, unit, password_hash,
            is_active, is_locked, email_verified_at, role, note
        )
        VALUES (?, 'Test', 'Finance', ?, 'mineurs', ?, 1, 0, NOW(), 'user', 10.00)
    ");
    $insertUser->execute([$username, $email, password_hash('test-password', PASSWORD_DEFAULT)]);
    $userId = (int)$db->lastInsertId();

    $insertOrder = $db->prepare("
        INSERT INTO orders (user_id, status, currency, total_price, created_at)
        VALUES (?, 'pending_payment', 'EUR', 10.00, NOW())
    ");
    $insertOrder->execute([$userId]);
    $firstOrderId = (int)$db->lastInsertId();
    $orderIds[] = $firstOrderId;

    $token = bin2hex(random_bytes(24));
    $capture = PaymentService::captureForUser(
        $userId,
        $adminId,
        'orders',
        [$firstOrderId],
        null,
        'cash',
        null,
        'TEST-' . $suffix,
        $token
    );

    assertSameValue(0, UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])), 'La dette n’a pas été soldée');
    assertSameValue('paid', (string)fetchScalar($db, 'SELECT status FROM orders WHERE id = ?', [$firstOrderId]), 'La commande n’est pas marquée payée');
    assertSameValue(1000, (int)round((float)$capture['applied_amount'] * 100), 'Le montant encaissé est incorrect');

    $duplicate = PaymentService::captureForUser(
        $userId,
        $adminId,
        'orders',
        [$firstOrderId],
        null,
        'cash',
        null,
        'TEST-' . $suffix,
        $token
    );
    assertSameValue(true, (bool)$duplicate['duplicate'], 'La double soumission n’a pas été reconnue');
    assertSameValue(1, (int)fetchScalar($db, 'SELECT COUNT(*) FROM payment_batches WHERE idempotency_key = ?', [$token]), 'Un lot en double a été créé');

    $db->beginTransaction();
    UserBalance::applyMovement(
        $db,
        $userId,
        700,
        'manual_adjustment',
        'legacy-balance-test-' . $suffix,
        $adminId,
        null,
        null,
        'Simulation d’un ancien solde sans commande associée'
    );
    $db->commit();

    $legacyBalanceCapture = PaymentService::captureForUser(
        $userId,
        $adminId,
        'free',
        [],
        700,
        'cash',
        null,
        'LEGACY-BALANCE-' . $suffix,
        bin2hex(random_bytes(24))
    );

    assertSameValue(
        0,
        UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])),
        'Le solde historique sans commande n’a pas été encaissé'
    );
    assertSameValue(
        700,
        (int)round((float)$legacyBalanceCapture['unallocated_amount'] * 100),
        'Le reliquat historique encaissé ne devrait être affecté à aucune commande'
    );

    $creditCapture = PaymentService::captureForUser(
        $userId,
        $adminId,
        'free',
        [],
        500,
        'cash',
        null,
        'CREDIT-' . $suffix,
        bin2hex(random_bytes(24))
    );
    assertSameValue(-500, UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])), 'L’avoir de 5 euros n’a pas été créé');
    assertSameValue(500, (int)round((float)$creditCapture['unallocated_amount'] * 100), 'La part non affectée est incorrecte');

    $variantStmt = $db->query("
        SELECT pv.id, pv.price, pv.stock_quantity
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        WHERE pv.is_active = 1
          AND p.is_active = 1
          AND pv.stock_quantity > 0
          AND pv.price > 0
        ORDER BY pv.id ASC
        LIMIT 1
    ");
    $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException('Une variante active en stock est nécessaire pour tester l’utilisation de l’avoir.');
    }

    $variantId = (int)$variant['id'];
    $variantStockBefore = (int)$variant['stock_quantity'];
    $variantPriceCents = UserBalance::decimalToCents($variant['price']);
    $currentCreditCents = max(0, -UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])));
    $targetCreditCents = $variantPriceCents + 200;

    if ($currentCreditCents < $targetCreditCents) {
        PaymentService::captureForUser(
            $userId,
            $adminId,
            'free',
            [],
            $targetCreditCents - $currentCreditCents,
            'cash',
            null,
            'CREDIT-TOPUP-' . $suffix,
            bin2hex(random_bytes(24))
        );
    }

    $balanceBeforeOrderCents = UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId]));
    $creditOrderId = Order::createAdminMultiChargeForUser($userId, [['variant_id' => $variantId, 'quantity' => 1]], $adminId);
    $orderIds[] = $creditOrderId;

    assertSameValue('paid', (string)fetchScalar($db, 'SELECT status FROM orders WHERE id = ?', [$creditOrderId]), 'L’avoir n’a pas soldé la nouvelle commande');
    assertSameValue($variantPriceCents, UserBalance::decimalToCents(fetchScalar($db, "SELECT amount_paid FROM payments WHERE order_id = ? AND method = 'credit' LIMIT 1", [$creditOrderId])), 'L’affectation de l’avoir est incorrecte');
    assertSameValue($balanceBeforeOrderCents + $variantPriceCents, UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])), 'Le solde après consommation de l’avoir est incorrect');

    Payment::refundOrderFull($creditOrderId, $adminId, 'restock');
    assertSameValue($balanceBeforeOrderCents, UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])), 'L’avoir n’a pas été restauré après remboursement');
    assertSameValue($variantStockBefore, (int)fetchScalar($db, 'SELECT stock_quantity FROM product_variants WHERE id = ?', [$variantId]), 'Le stock n’a pas été restauré après remboursement');

    $guardOrderStmt = $db->prepare("
        INSERT INTO orders (user_id, status, currency, total_price, created_at)
        VALUES (?, 'pending_payment', 'EUR', 10.00, NOW())
    ");
    $guardOrderStmt->execute([$userId]);
    $guardOrderId = (int)$db->lastInsertId();
    $orderIds[] = $guardOrderId;

    $productId = (int)fetchScalar($db, 'SELECT product_id FROM product_variants WHERE id = ?', [$variantId]);
    $itemStmt = $db->prepare("
        INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, currency)
        VALUES (?, ?, ?, 1, 10.00, 'EUR')
    ");
    $itemStmt->execute([$guardOrderId, $productId, $variantId]);
    $paymentStmt = $db->prepare("
        INSERT INTO payments (order_id, payment_author_id, admin_id, amount_paid, method, status, currency)
        VALUES (?, ?, ?, 5.00, 'cash', 'captured', 'EUR')
    ");
    $paymentStmt->execute([$guardOrderId, $userId, $adminId]);

    $guardTriggered = false;
    try {
        Payment::refundOrderFull($guardOrderId, $adminId, 'restock');
    } catch (RuntimeException $e) {
        $guardTriggered = str_contains($e->getMessage(), 'ne couvre pas tous les produits');
    }
    assertSameValue(true, $guardTriggered, 'Le remboursement intégral supérieur au montant encaissé n’a pas été bloqué');

    $balanceBeforeCustomChargeCents = UserBalance::decimalToCents(
        fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])
    );
    $stockBeforeCustomCharge = (int)fetchScalar(
        $db,
        'SELECT stock_quantity FROM product_variants WHERE id = ?',
        [$variantId]
    );
    $customOrderId = Order::createAdminMultiChargeForUser(
        $userId,
        [],
        $adminId,
        [['label' => 'Participation exceptionnelle', 'amount' => '12,34']]
    );
    $orderIds[] = $customOrderId;

    assertSameValue(
        1234,
        UserBalance::decimalToCents(fetchScalar($db, 'SELECT total_price FROM orders WHERE id = ?', [$customOrderId])),
        'Le total de la facturation libre est incorrect'
    );
    assertSameValue(
        'custom',
        (string)fetchScalar($db, 'SELECT line_type FROM order_items WHERE order_id = ? LIMIT 1', [$customOrderId]),
        'La ligne libre n’est pas identifiée comme telle'
    );
    assertSameValue(
        1,
        (int)fetchScalar($db, 'SELECT product_id IS NULL AND variant_id IS NULL FROM order_items WHERE order_id = ? LIMIT 1', [$customOrderId]),
        'La ligne libre reste liée au catalogue'
    );
    assertSameValue(
        'Participation exceptionnelle',
        (string)fetchScalar($db, 'SELECT product_name_snapshot FROM order_items WHERE order_id = ? LIMIT 1', [$customOrderId]),
        'Le libellé libre n’a pas été conservé'
    );
    assertSameValue(
        $balanceBeforeCustomChargeCents + 1234,
        UserBalance::decimalToCents(fetchScalar($db, 'SELECT note FROM users WHERE id = ?', [$userId])),
        'La facturation libre n’a pas été portée au solde utilisateur'
    );
    assertSameValue(
        $stockBeforeCustomCharge,
        (int)fetchScalar($db, 'SELECT stock_quantity FROM product_variants WHERE id = ?', [$variantId]),
        'Une facturation libre a modifié le stock'
    );

} finally {
    if ($userId > 0) {
        $storedOrderIds = $db->prepare('SELECT id FROM orders WHERE user_id = ?');
        $storedOrderIds->execute([$userId]);
        $orderIds = array_values(array_unique(array_merge($orderIds, array_map('intval', $storedOrderIds->fetchAll(PDO::FETCH_COLUMN)))));

        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $db->prepare("DELETE FROM refunds WHERE order_id IN ({$placeholders})")->execute($orderIds);
            $db->prepare("DELETE FROM payments WHERE order_id IN ({$placeholders}) OR payment_author_id = ?")
                ->execute(array_merge($orderIds, [$userId]));
            $db->prepare("DELETE FROM inventory_movements WHERE order_id IN ({$placeholders})")
                ->execute($orderIds);

            foreach ($orderIds as $cleanupOrderId) {
                $db->prepare("DELETE FROM inventory_movements WHERE meta LIKE ?")
                    ->execute(['%order_id=' . $cleanupOrderId . '%']);
                $db->prepare("DELETE FROM logs WHERE details LIKE ?")
                    ->execute(['%Commande #' . $cleanupOrderId . '%']);
            }

            $db->prepare("DELETE FROM orders WHERE id IN ({$placeholders})")->execute($orderIds);
        }

        $db->prepare('DELETE FROM user_balance_movements WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM payments WHERE payment_author_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM payment_batches WHERE user_id = ?')->execute([$userId]);
        $db->prepare("DELETE FROM logs WHERE details LIKE ? OR details LIKE ?")
            ->execute(['%utilisateur #' . $userId . '%', '%user #' . $userId . '%']);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }

    if ($variantId > 0 && $variantStockBefore !== null) {
        $db->prepare('UPDATE product_variants SET stock_quantity = ? WHERE id = ?')->execute([$variantStockBefore, $variantId]);
    }

    $modelDb->setValue(null, null);
}

$consistencyChecks = [
    'Des utilisateurs temporaires subsistent après le test' => "
        SELECT COUNT(*)
        FROM users
        WHERE username LIKE '__finance\\_test\\_%'
    ",
    'Le journal financier ne correspond plus au solde utilisateur' => "
        SELECT COUNT(*)
        FROM users u
        INNER JOIN (
            SELECT user_id, MAX(id) AS movement_id
            FROM user_balance_movements
            WHERE user_id IS NOT NULL
            GROUP BY user_id
        ) latest ON latest.user_id = u.id
        INNER JOIN user_balance_movements m ON m.id = latest.movement_id
        WHERE ABS(u.note - m.balance_after) > 0.009
    ",
    'Une commande a reçu plus de paiements que son total' => "
        SELECT COUNT(*)
        FROM (
            SELECT o.id
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'captured'
            GROUP BY o.id, o.total_price
            HAVING COALESCE(SUM(p.amount_paid), 0) - o.total_price > 0.009
        ) inconsistent_orders
    ",
    'Une commande payée conserve un montant dû' => "
        SELECT COUNT(*)
        FROM (
            SELECT o.id
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'captured'
            WHERE o.status = 'paid'
            GROUP BY o.id, o.total_price
            HAVING o.total_price - COALESCE(SUM(p.amount_paid), 0) > 0.009
        ) inconsistent_orders
    ",
];

foreach ($consistencyChecks as $message => $sql) {
    assertSameValue(0, (int)fetchScalar($db, $sql), $message);
}

echo "FinancialWorkflowTest: OK\n";
