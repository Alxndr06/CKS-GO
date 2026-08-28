<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/UserBalance.php';
require_once __DIR__ . '/Inventory.php';

class Order extends Model
{
    public static function createFromCart(int $userId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $balanceBeforeCents = UserBalance::lockBalance($db, $userId);

            $roleStmt = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $roleStmt->execute([$userId]);
            $role = strtolower((string)$roleStmt->fetchColumn());
            $isStaffCheckout = in_array($role, ['assistant', 'gestionnaire', 'responsable', 'admin'], true);

            $cartStmt = $db->prepare("
                SELECT
                    ci.id AS cart_item_id,
                    pv.product_id AS product_id,
                    ci.variant_id,
                    ci.quantity,
                    pv.name AS variant_name,
                    pv.sku AS variant_sku,
                    COALESCE(
                        (
                            SELECT MAX(va.attr_value)
                            FROM variant_attributes va
                            WHERE va.variant_id = pv.id
                              AND va.attr_name = 'flavor'
                        ),
                        NULLIF(pv.name, ''),
                        'Standard'
                    ) AS variant_display_name,
                    pv.price AS unit_price,
                    pv.stock_quantity,
                    pv.is_active AS variant_is_active,
                    p.name AS product_name,
                    p.is_active AS product_is_active,
                    p.visibility AS product_visibility
                FROM cart_items ci
                INNER JOIN carts c ON c.id = ci.cart_id
                INNER JOIN product_variants pv ON pv.id = ci.variant_id
                INNER JOIN products p ON p.id = pv.product_id
                WHERE c.user_id = ?
                  AND pv.archived_at IS NULL
                  AND p.archived_at IS NULL
                ORDER BY ci.id ASC
                FOR UPDATE
            ");
            $cartStmt->execute([$userId]);
            $items = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new RuntimeException("Le panier est vide.");
            }

            $totalCents = 0;

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $stock = (int)$item['stock_quantity'];

                if ($qty <= 0) {
                    throw new RuntimeException("Quantité invalide dans le panier.");
                }

                if ((int)($item['product_is_active'] ?? 0) !== 1) {
                    throw new RuntimeException("Le produit " . ($item['product_name'] ?? 'sélectionné') . " n'est plus disponible.");
                }

                if (($item['product_visibility'] ?? 'public') === 'admin_only' && !$isStaffCheckout) {
                    throw new RuntimeException("Ce produit est désormais réservé au staff.");
                }

                if ((int)($item['variant_is_active'] ?? 0) !== 1) {
                    throw new RuntimeException("Une variante du produit " . ($item['product_name'] ?? 'sélectionné') . " n'est plus disponible.");
                }

                if ($stock < $qty) {
                    throw new RuntimeException("Stock insuffisant pour " . ($item['product_name'] ?? 'un produit') . ".");
                }

                $totalCents += UserBalance::decimalToCents($item['unit_price']) * $qty;
            }

            $total = UserBalance::centsToDecimal($totalCents);

            $orderStmt = $db->prepare("
                INSERT INTO orders (user_id, total_price, status, currency, created_at)
                VALUES (?, ?, 'pending_payment', 'EUR', NOW())
            ");
            $orderStmt->execute([$userId, $total]);

            $orderId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    variant_id,
                    product_name_snapshot,
                    variant_name_snapshot,
                    sku_snapshot,
                    quantity,
                    unit_price,
                    currency
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'EUR')
            ");

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $unitPrice = (string)$item['unit_price'];
                $variantId = (int)$item['variant_id'];

                $itemStmt->execute([
                    $orderId,
                    (int)$item['product_id'],
                    $variantId,
                    (string)$item['product_name'],
                    (string)$item['variant_display_name'],
                    (string)($item['variant_sku'] ?? ''),
                    $qty,
                    $unitPrice
                ]);

                Inventory::adjustStock(
                    $db,
                    $variantId,
                    -$qty,
                    'order_checkout',
                    [
                        'order_id' => $orderId,
                        'meta' => 'user_id=' . $userId . ';source=shop_checkout',
                        'note' => 'Validation de la commande #' . $orderId,
                    ]
                );
            }

            self::recordChargeAndApplyCredit(
                $db,
                $userId,
                $orderId,
                $totalCents,
                $balanceBeforeCents,
                null
            );

            $clearCartStmt = $db->prepare("
                DELETE ci
                FROM cart_items ci
                INNER JOIN carts c ON c.id = ci.cart_id
                WHERE c.user_id = ?
            ");
            $clearCartStmt->execute([$userId]);

            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createAdminMultiChargeForUser(
        int $userId,
        array $lines,
        int $adminId,
        array $customLines = []
    ): int {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($userId <= 0) {
                throw new RuntimeException("Utilisateur invalide.");
            }

            if ($adminId <= 0) {
                throw new RuntimeException("Administrateur invalide.");
            }

            if (empty($lines) && empty($customLines)) {
                throw new RuntimeException("Aucune ligne à facturer.");
            }

            $balanceBeforeCents = UserBalance::lockBalance($db, $userId);
            $normalizedLines = [];

            foreach ($lines as $line) {
                $variantId = (int)($line['variant_id'] ?? 0);
                $quantity = (int)($line['quantity'] ?? 0);

                if ($variantId <= 0 || $quantity <= 0) {
                    continue;
                }

                $normalizedLines[$variantId] = ($normalizedLines[$variantId] ?? 0) + $quantity;
            }

            $normalizedCustomLines = [];

            foreach ($customLines as $line) {
                $normalizedLabel = preg_replace('/\s+/u', ' ', trim((string)($line['label'] ?? '')));
                $label = is_string($normalizedLabel) ? $normalizedLabel : '';
                $amount = trim((string)($line['amount'] ?? ''));

                if ($label === '' && $amount === '') {
                    continue;
                }

                if ($label === '') {
                    throw new RuntimeException("Le libellé de chaque montant libre est obligatoire.");
                }

                $labelLength = function_exists('mb_strlen')
                    ? mb_strlen($label, 'UTF-8')
                    : strlen($label);

                if ($labelLength > 150) {
                    throw new RuntimeException("Le libellé d’un montant libre ne peut pas dépasser 150 caractères.");
                }

                try {
                    $amountCents = UserBalance::decimalToCents($amount);
                } catch (InvalidArgumentException $e) {
                    throw new RuntimeException("Le montant libre « {$label} » est invalide.");
                }

                if ($amountCents <= 0) {
                    throw new RuntimeException("Le montant libre « {$label} » doit être supérieur à 0.");
                }

                if ($amountCents > 9999999999) {
                    throw new RuntimeException("Le montant libre « {$label} » dépasse la limite autorisée.");
                }

                $normalizedCustomLines[] = [
                    'label' => $label,
                    'amount_cents' => $amountCents,
                ];
            }

            if (empty($normalizedLines) && empty($normalizedCustomLines)) {
                throw new RuntimeException("Aucune ligne valide à facturer.");
            }

            $variants = [];

            if (!empty($normalizedLines)) {
                $placeholders = implode(',', array_fill(0, count($normalizedLines), '?'));
                $variantStmt = $db->prepare("
                    SELECT
                        pv.id,
                        pv.product_id,
                        pv.sku AS variant_sku,
                        pv.name AS variant_name,
                        COALESCE(
                            (
                                SELECT MAX(va.attr_value)
                                FROM variant_attributes va
                                WHERE va.variant_id = pv.id
                                  AND va.attr_name = 'flavor'
                            ),
                            NULLIF(pv.name, ''),
                            'Standard'
                        ) AS variant_display_name,
                        pv.price AS unit_price,
                        pv.stock_quantity,
                        p.name AS product_name
                    FROM product_variants pv
                    INNER JOIN products p ON p.id = pv.product_id
                    WHERE pv.id IN ($placeholders)
                      AND pv.archived_at IS NULL
                      AND p.archived_at IS NULL
                    FOR UPDATE
                ");
                $variantStmt->execute(array_keys($normalizedLines));
                $variants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($variants) !== count($normalizedLines)) {
                    throw new RuntimeException("Une ou plusieurs variantes sont introuvables.");
                }
            }

            $variantsById = [];
            foreach ($variants as $variant) {
                $variantsById[(int)$variant['id']] = $variant;
            }

            $totalCents = 0;

            foreach ($normalizedLines as $variantId => $quantity) {
                if (!isset($variantsById[$variantId])) {
                    throw new RuntimeException("Variante introuvable (#{$variantId}).");
                }

                $variant = $variantsById[$variantId];
                $stock = (int)$variant['stock_quantity'];

                if ($stock < $quantity) {
                    throw new RuntimeException(
                        "Stock insuffisant pour " . ($variant['product_name'] ?? 'un produit') . "."
                    );
                }

                $totalCents += UserBalance::decimalToCents($variant['unit_price']) * $quantity;
            }

            foreach ($normalizedCustomLines as $customLine) {
                $totalCents += (int)$customLine['amount_cents'];
            }

            if ($totalCents > 9999999999) {
                throw new RuntimeException("Le total de la facturation dépasse la limite autorisée.");
            }

            $total = UserBalance::centsToDecimal($totalCents);

            $orderStmt = $db->prepare("
                INSERT INTO orders (user_id, total_price, status, currency, created_at)
                VALUES (?, ?, 'pending_payment', 'EUR', NOW())
            ");
            $orderStmt->execute([$userId, $total]);
            $orderId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    variant_id,
                    line_type,
                    product_name_snapshot,
                    variant_name_snapshot,
                    sku_snapshot,
                    quantity,
                    unit_price,
                    currency
                )
                VALUES (?, ?, ?, 'product', ?, ?, ?, ?, ?, 'EUR')
            ");

            foreach ($normalizedLines as $variantId => $quantity) {
                $variant = $variantsById[$variantId];
                $unitPrice = (string)$variant['unit_price'];

                $itemStmt->execute([
                    $orderId,
                    (int)$variant['product_id'],
                    $variantId,
                    (string)$variant['product_name'],
                    (string)$variant['variant_display_name'],
                    (string)($variant['variant_sku'] ?? ''),
                    $quantity,
                    $unitPrice
                ]);

                Inventory::adjustStock(
                    $db,
                    $variantId,
                    -$quantity,
                    'order_checkout',
                    [
                        'admin_id' => $adminId,
                        'order_id' => $orderId,
                        'meta' => 'user_id=' . $userId . ';source=admin_multi_charge',
                        'note' => 'Facturation directe, commande #' . $orderId,
                    ]
                );
            }

            $customItemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    variant_id,
                    line_type,
                    product_name_snapshot,
                    variant_name_snapshot,
                    sku_snapshot,
                    quantity,
                    unit_price,
                    currency
                )
                VALUES (?, NULL, NULL, 'custom', ?, 'Montant libre', NULL, 1, ?, 'EUR')
            ");

            foreach ($normalizedCustomLines as $customLine) {
                $customItemStmt->execute([
                    $orderId,
                    (string)$customLine['label'],
                    UserBalance::centsToDecimal((int)$customLine['amount_cents']),
                ]);
            }

            self::recordChargeAndApplyCredit(
                $db,
                $userId,
                $orderId,
                $totalCents,
                $balanceBeforeCents,
                $adminId
            );

            $db->commit();

            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function cancelOrderByAdmin(int $orderId, int $adminId): void
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($orderId <= 0 || $adminId <= 0) {
                throw new RuntimeException("Requête invalide.");
            }

            $ownerStmt = $db->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$orderId]);
            $ownerId = (int)$ownerStmt->fetchColumn();

            if ($ownerId <= 0) {
                throw new RuntimeException("Commande introuvable.");
            }

            UserBalance::lockBalance($db, $ownerId);
            $lockOrderStmt = $db->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
            $lockOrderStmt->execute([$orderId]);

            $orderStmt = $db->prepare("
                SELECT
                    o.id,
                    o.user_id,
                    o.total_price,
                    o.status,
                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS captured_total
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.id = ?
                GROUP BY o.id, o.user_id, o.total_price, o.status
                LIMIT 1
            ");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException("Commande introuvable.");
            }

            if (($order['status'] ?? '') === 'cancelled') {
                throw new RuntimeException("Cette commande est déjà annulée.");
            }

            if ((float)$order['captured_total'] > 0) {
                throw new RuntimeException("Impossible d’annuler une commande déjà encaissée. Utilisez le remboursement.");
            }

            $itemsStmt = $db->prepare("
                SELECT variant_id, quantity
                FROM order_items
                WHERE order_id = ?
                ORDER BY id ASC
            ");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new RuntimeException("Aucune ligne de commande à annuler.");
            }

            foreach ($items as $item) {
                $variantId = (int)$item['variant_id'];
                $quantity = (int)$item['quantity'];

                if ($variantId > 0 && $quantity > 0) {
                    Inventory::adjustStock(
                        $db,
                        $variantId,
                        $quantity,
                        'adjustment',
                        [
                            'admin_id' => $adminId,
                            'order_id' => $orderId,
                            'meta' => 'source=admin_cancel_order',
                            'note' => 'Restock après annulation de la commande #' . $orderId,
                            'allow_archived' => true,
                        ]
                    );
                }
            }

            UserBalance::applyMovement(
                $db,
                (int)$order['user_id'],
                -UserBalance::decimalToCents($order['total_price']),
                'order_cancellation',
                'order-cancellation-' . $orderId,
                $adminId,
                $orderId,
                null,
                'Annulation de la commande #' . $orderId
            );

            $updateOrderStmt = $db->prepare("
                UPDATE orders
                SET status = 'cancelled'
                WHERE id = ?
            ");
            $updateOrderStmt->execute([$orderId]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function recordChargeAndApplyCredit(
        PDO $db,
        int $userId,
        int $orderId,
        int $totalCents,
        int $balanceBeforeCents,
        ?int $adminId
    ): void {
        UserBalance::applyMovement(
            $db,
            $userId,
            $totalCents,
            'order_charge',
            'order-charge-' . $orderId,
            $adminId,
            $orderId,
            null,
            'Création de la commande #' . $orderId
        );

        $availableCreditCents = max(0, -$balanceBeforeCents);
        $creditAppliedCents = min($availableCreditCents, $totalCents);

        if ($creditAppliedCents <= 0) {
            return;
        }

        $paymentStmt = $db->prepare("
            INSERT INTO payments (
                order_id,
                payment_author_id,
                admin_id,
                amount_paid,
                method,
                provider,
                provider_ref,
                status,
                currency
            )
            VALUES (?, ?, ?, ?, 'credit', 'CKS GO', ?, 'captured', 'EUR')
        ");
        $paymentStmt->execute([
            $orderId,
            $userId,
            $adminId,
            UserBalance::centsToDecimal($creditAppliedCents),
            'AVOIR-' . $orderId,
        ]);

        $status = $creditAppliedCents >= $totalCents ? 'paid' : 'pending_payment';
        $statusStmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $statusStmt->execute([$status, $orderId]);

        $logStmt = $db->prepare("INSERT INTO logs (admin_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $adminId,
            'credit_applied_to_order',
            'Commande #' . $orderId . ' / avoir utilisé=' . UserBalance::centsToDecimal($creditAppliedCents),
        ]);
    }

    public static function getOrderSummary(int $orderId, int $userId): ?array
    {
        $db = self::getDB();

        $orderStmt = $db->prepare("
            SELECT *
            FROM orders
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $orderStmt->execute([$orderId, $userId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.*,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name) AS product_name,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name) AS variant_name,
                (oi.quantity * oi.unit_price) AS line_total
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute([$orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    public static function getRecentOrders(int $limit = 6): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                o.id,
                o.user_id,
                o.total_price,
                o.status,
                o.currency,
                o.created_at,
                u.username,
                u.firstname,
                u.lastname,
                COUNT(oi.id) AS items_count
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            GROUP BY
                o.id, o.user_id, o.total_price, o.status, o.currency, o.created_at,
                u.username, u.firstname, u.lastname
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentByUserId(int $userId, int $limit = 5): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                o.id,
                o.user_id,
                o.total_price,
                o.status,
                o.currency,
                o.created_at,
                COUNT(oi.id) AS items_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY
                o.id, o.user_id, o.total_price, o.status, o.currency, o.created_at
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAdminStats(): array
    {
        $db = self::getDB();

        $stats = [
            'orders_total' => 0,
            'orders_pending_payment' => 0,
            'orders_paid' => 0,
            'orders_cancelled' => 0,
            'orders_revenue_gross' => 0.0,
            'orders_revenue_net' => 0.0,
            'orders_refunded' => 0,
            'orders_partially_refunded' => 0,
        ];

        $statusStmt = $db->query("
            SELECT status, COUNT(*) AS total
            FROM orders
            GROUP BY status
        ");
        $rows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            $count = (int)($row['total'] ?? 0);
            $stats['orders_total'] += $count;

            if ($status === 'pending_payment') {
                $stats['orders_pending_payment'] = $count;
            } elseif ($status === 'paid') {
                $stats['orders_paid'] = $count;
            } elseif ($status === 'cancelled') {
                $stats['orders_cancelled'] = $count;
            } elseif ($status === 'refunded') {
                $stats['orders_refunded'] = $count;
            } elseif ($status === 'partially_refunded') {
                $stats['orders_partially_refunded'] = $count;
            }
        }

        $amountStmt = $db->query("
            SELECT
                COALESCE((
                    SELECT SUM(amount_paid)
                    FROM payments
                    WHERE status = 'captured'
                ), 0) AS captured_total,
                COALESCE((
                    SELECT SUM(amount)
                    FROM refunds
                ), 0) AS refunded_total,
                COALESCE((
                    SELECT SUM(t.remaining_due)
                    FROM (
                        SELECT
                            o.id,
                            GREATEST(
                                o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0),
                                0
                            ) AS remaining_due
                        FROM orders o
                        LEFT JOIN payments p ON p.order_id = o.id
                        WHERE o.status IN ('pending_payment', 'paid', 'partially_refunded')
                        GROUP BY o.id, o.total_price
                    ) AS t
                ), 0) AS pending_total
        ");
        $amounts = $amountStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $capturedTotal = (float)($amounts['captured_total'] ?? 0);
        $refundedTotal = (float)($amounts['refunded_total'] ?? 0);
        $netRevenue = max($capturedTotal - $refundedTotal, 0);

        $stats['orders_revenue_gross'] = $capturedTotal;
        $stats['orders_revenue_net'] = $netRevenue;
        $stats['pending_amount'] = (float)($amounts['pending_total'] ?? 0);

        return $stats;
    }

    private static function buildAdminOrderFilters(?string $q, ?string $status): array
    {
        $where = '';
        $params = [];

        if ($q !== null && $q !== '') {
            $like = '%' . $q . '%';
            $where .= "
                AND (
                    CAST(o.id AS CHAR) LIKE ?
                    OR u.username LIKE ?
                    OR u.firstname LIKE ?
                    OR u.lastname LIKE ?
                    OR u.email LIKE ?
                )
            ";
            $params = array_fill(0, 5, $like);
        }

        if ($status !== null && $status !== '') {
            $where .= ' AND o.status = ?';
            $params[] = $status;
        }

        return [$where, $params];
    }

    public static function searchAdminOrders(
        ?string $q = null,
        ?string $status = null,
        ?int $limit = null,
        int $offset = 0
    ): array
    {
        $db = self::getDB();
        [$where, $params] = self::buildAdminOrderFilters($q, $status);

        $sql = "
            SELECT
                o.id,
                o.user_id,
                o.total_price,
                o.status,
                o.currency,
                o.created_at,
                u.username,
                u.firstname,
                u.lastname,
                u.email,
                COALESCE(oi_summary.items_count, 0) AS items_count,
                COALESCE(payment_summary.amount_paid, 0) AS amount_paid
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN (
                SELECT order_id, COUNT(*) AS items_count
                FROM order_items
                GROUP BY order_id
            ) AS oi_summary ON oi_summary.order_id = o.id
            LEFT JOIN (
                SELECT order_id, SUM(amount_paid) AS amount_paid
                FROM payments
                WHERE status = 'captured'
                GROUP BY order_id
            ) AS payment_summary ON payment_summary.order_id = o.id
            WHERE 1
            {$where}
            ORDER BY o.created_at DESC, o.id DESC
        ";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function summarizeAdminOrders(?string $q = null, ?string $status = null): array
    {
        $db = self::getDB();
        [$where, $params] = self::buildAdminOrderFilters($q, $status);
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(o.total_price), 0) AS total_amount,
                COALESCE(SUM(COALESCE(payment_summary.amount_paid, 0)), 0) AS total_paid,
                COALESCE(SUM(GREATEST(o.total_price - COALESCE(payment_summary.amount_paid, 0), 0)), 0)
                    AS total_remaining
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN (
                SELECT order_id, SUM(amount_paid) AS amount_paid
                FROM payments
                WHERE status = 'captured'
                GROUP BY order_id
            ) AS payment_summary ON payment_summary.order_id = o.id
            WHERE 1
            {$where}
        ");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_orders' => 0,
            'total_amount' => 0,
            'total_paid' => 0,
            'total_remaining' => 0,
        ];
    }

    public static function getAdminOrderById(int $orderId): ?array
    {
        $db = self::getDB();

        $orderStmt = $db->prepare("
            SELECT
                o.*,
                u.username,
                u.firstname,
                u.lastname,
                u.email,
                u.note
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            WHERE o.id = ?
            LIMIT 1
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.variant_id,
                oi.line_type,
                oi.quantity,
                oi.unit_price,
                (oi.quantity * oi.unit_price) AS line_total,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name) AS product_name,
                p.image AS product_image,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name) AS variant_name,
                pv.image AS variant_image,
                CASE
                    WHEN NULLIF(oi.variant_name_snapshot, '') IS NULL
                    THEN MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END)
                    ELSE NULL
                END AS flavor,
                COALESCE(SUM(r.quantity_refunded), 0) AS quantity_refunded,
                COALESCE(SUM(r.amount), 0) AS refunded_amount
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN variant_attributes va ON va.variant_id = oi.variant_id
            LEFT JOIN refunds r ON r.order_item_id = oi.id
            WHERE oi.order_id = ?
            GROUP BY
                oi.id, oi.order_id, oi.product_id, oi.variant_id, oi.line_type, oi.product_name_snapshot,
                oi.variant_name_snapshot, oi.quantity, oi.unit_price,
                p.name, p.image, pv.name, pv.image
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $orderedQty = (int)$item['quantity'];
            $refundedQty = (int)$item['quantity_refunded'];
            $remainingQty = max($orderedQty - $refundedQty, 0);

            $item['display_name'] = !empty($item['flavor'])
                ? $item['flavor']
                : (!empty($item['variant_name']) ? $item['variant_name'] : $item['product_name']);

            $item['remaining_quantity'] = $remainingQty;
            $item['fully_refunded'] = $remainingQty <= 0;
        }
        unset($item);

        $paymentsStmt = $db->prepare("
            SELECT p.*
            FROM payments p
            WHERE p.order_id = ?
            ORDER BY p.id DESC
        ");
        $paymentsStmt->execute([$orderId]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $refundsStmt = $db->prepare("
            SELECT
                r.*,
                oi.product_id,
                oi.variant_id,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name) AS product_name,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name) AS variant_name,
                CASE
                    WHEN NULLIF(oi.variant_name_snapshot, '') IS NULL
                    THEN MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END)
                    ELSE NULL
                END AS flavor,
                u.username AS admin_username
            FROM refunds r
            LEFT JOIN order_items oi ON oi.id = r.order_item_id
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN variant_attributes va ON va.variant_id = oi.variant_id
            LEFT JOIN users u ON u.id = r.admin_id
            WHERE r.order_id = ?
            GROUP BY
                r.id, r.order_id, r.order_item_id, r.payment_id, r.admin_id, r.variant_id,
                r.quantity_refunded, r.amount, r.reason, r.created_at,
                oi.product_id, oi.variant_id, oi.product_name_snapshot, oi.variant_name_snapshot,
                p.name, pv.name, u.username
            ORDER BY r.id DESC
        ");
        $refundsStmt->execute([$orderId]);
        $refunds = $refundsStmt->fetchAll(PDO::FETCH_ASSOC);

        $capturedPaid = 0.0;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'captured') {
                $capturedPaid += (float)$payment['amount_paid'];
            }
        }

        $refundedTotal = 0.0;
        foreach ($refunds as $refund) {
            $refundedTotal += (float)$refund['amount'];
        }

        $refundDeadlineAt = date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +10 days'));
        $deadlineOk = strtotime($refundDeadlineAt) >= time();
        $remainingRefundableTotal = max($capturedPaid - $refundedTotal, 0);
        $remainingItemsTotal = 0.0;
        foreach ($items as $item) {
            $remainingItemsTotal += (float)$item['unit_price'] * (int)$item['remaining_quantity'];
        }

        $order['items'] = $items;
        $order['payments'] = $payments;
        $order['refunds'] = $refunds;
        $order['captured_paid_total'] = $capturedPaid;
        $order['refunded_total'] = $refundedTotal;
        $order['remaining_refundable_total'] = $remainingRefundableTotal;
        $order['net_paid_total'] = max($capturedPaid - $refundedTotal, 0);
        $order['refund_deadline_at'] = $refundDeadlineAt;
        $order['is_refundable'] = $deadlineOk && $remainingRefundableTotal > 0;
        $order['can_refund_full'] = $order['is_refundable'] && $remainingRefundableTotal + 0.009 >= $remainingItemsTotal;
        $order['can_cancel'] = (($order['status'] ?? '') === 'pending_payment') && $capturedPaid <= 0 && $refundedTotal <= 0;

        return $order;
    }

    public static function recalculateStatus(int $orderId): void
    {
        $db = self::getDB();

        $orderStmt = $db->prepare("
            SELECT total_price, status
            FROM orders
            WHERE id = ?
            LIMIT 1
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException("Commande introuvable.");
        }

        if (($order['status'] ?? '') === 'cancelled') {
            return;
        }

        $paidStmt = $db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM payments
            WHERE order_id = ?
              AND status = 'captured'
        ");
        $paidStmt->execute([$orderId]);
        $paidTotal = (float)$paidStmt->fetchColumn();

        $refundStmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM refunds
            WHERE order_id = ?
        ");
        $refundStmt->execute([$orderId]);
        $refundTotal = (float)$refundStmt->fetchColumn();

        $itemsStmt = $db->prepare("
            SELECT
                COALESCE(SUM(quantity), 0) AS ordered_qty,
                COALESCE(SUM(
                    (
                        SELECT COALESCE(SUM(r.quantity_refunded), 0)
                        FROM refunds r
                        WHERE r.order_item_id = oi.id
                    )
                ), 0) AS refunded_qty
            FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $qtyData = $itemsStmt->fetch(PDO::FETCH_ASSOC);

        $orderedQty = (int)($qtyData['ordered_qty'] ?? 0);
        $refundedQty = (int)($qtyData['refunded_qty'] ?? 0);

        $status = 'pending_payment';
        $netPaid = max($paidTotal - $refundTotal, 0);

        if ($orderedQty > 0 && $refundedQty >= $orderedQty) {
            $status = 'cancelled';
        } elseif ($netPaid >= (float)$order['total_price']) {
            $status = 'paid';
        }

        $updateStmt = $db->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$status, $orderId]);
    }

    public static function getUserStats(int $userId): array
    {
        $db = self::getDB();

        $stats = [
            'orders_total' => 0,
            'orders_pending_payment' => 0,
            'orders_paid' => 0,
            'orders_cancelled' => 0,
            'orders_total_amount' => 0.0,
            'pending_amount' => 0.0,
            'paid_amount' => 0.0,
            'orders_refunded' => 0,
            'orders_partially_refunded' => 0,
        ];

        $statusStmt = $db->prepare("
        SELECT status, COUNT(*) AS total
        FROM orders
        WHERE user_id = ?
        GROUP BY status
    ");
        $statusStmt->execute([$userId]);
        $rows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            $count = (int)($row['total'] ?? 0);

            if ($status === 'pending_payment') {
                $stats['orders_pending_payment'] = $count;
                $stats['orders_total'] += $count;
            } elseif ($status === 'paid') {
                $stats['orders_paid'] = $count;
                $stats['orders_total'] += $count;
            } elseif ($status === 'cancelled') {
                $stats['orders_cancelled'] = $count;
            } elseif ($status === 'refunded') {
                $stats['orders_refunded'] = $count;
                $stats['orders_total'] += $count;
            } elseif ($status === 'partially_refunded') {
                $stats['orders_partially_refunded'] = $count;
                $stats['orders_total'] += $count;
            } else {
                $stats['orders_total'] += $count;
            }
        }

        $amountStmt = $db->prepare("
        SELECT
            COALESCE((
                SELECT SUM(p.amount_paid)
                FROM payments p
                INNER JOIN orders o1 ON o1.id = p.order_id
                WHERE o1.user_id = ?
                  AND p.status = 'captured'
            ), 0) AS captured_total,
            COALESCE((
                SELECT SUM(r.amount)
                FROM refunds r
                INNER JOIN orders o2 ON o2.id = r.order_id
                WHERE o2.user_id = ?
            ), 0) AS refunded_total,
            COALESCE((
                SELECT SUM(t.remaining_due)
                FROM (
                    SELECT
                        o3.id,
                        GREATEST(
                            o3.total_price - COALESCE(SUM(CASE WHEN p2.status = 'captured' THEN p2.amount_paid ELSE 0 END), 0),
                            0
                        ) AS remaining_due
                    FROM orders o3
                    LEFT JOIN payments p2 ON p2.order_id = o3.id
                    WHERE o3.user_id = ?
                      AND o3.status IN ('pending_payment', 'paid', 'partially_refunded')
                    GROUP BY o3.id, o3.total_price
                ) AS t
            ), 0) AS pending_total
    ");
        $amountStmt->execute([$userId, $userId, $userId]);
        $amounts = $amountStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $capturedTotal = (float)($amounts['captured_total'] ?? 0);
        $refundedTotal = (float)($amounts['refunded_total'] ?? 0);
        $pendingTotal = (float)($amounts['pending_total'] ?? 0);
        $netSpent = max($capturedTotal - $refundedTotal, 0);

        $stats['orders_total_amount'] = $netSpent;
        $stats['paid_amount'] = $netSpent;
        $stats['pending_amount'] = $pendingTotal;

        return $stats;
    }

    public static function countForUser(int $userId, ?string $q = null): int
    {
        $db = self::getDB();

        $sql = "
            SELECT COUNT(*)
            FROM orders o
            WHERE o.user_id = ?
        ";
        $params = [$userId];

        if ($q !== null && $q !== '') {
            $sql .= " AND (CAST(o.id AS CHAR) LIKE ? OR o.status LIKE ? OR DATE_FORMAT(o.created_at, '%d/%m/%Y') LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function searchForUser(int $userId, ?string $q = null, int $limit = 10, int $offset = 0): array
    {
        $db = self::getDB();

        $sql = "
            SELECT
                o.id,
                o.user_id,
                o.status,
                o.currency,
                o.total_price,
                o.created_at,
                COALESCE(oi_summary.item_lines, 0) AS item_lines,
                COALESCE(payment_summary.paid_total, 0) AS paid_total,
                COALESCE((
                    SELECT SUM(r.amount)
                    FROM refunds r
                    WHERE r.order_id = o.id
                ), 0) AS refunded_total
            FROM orders o
            LEFT JOIN (
                SELECT order_id, COUNT(*) AS item_lines
                FROM order_items
                GROUP BY order_id
            ) AS oi_summary ON oi_summary.order_id = o.id
            LEFT JOIN (
                SELECT order_id, SUM(amount_paid) AS paid_total
                FROM payments
                WHERE status = 'captured'
                GROUP BY order_id
            ) AS payment_summary ON payment_summary.order_id = o.id
            WHERE o.user_id = ?
        ";
        $params = [$userId];

        if ($q !== null && $q !== '') {
            $sql .= " AND (CAST(o.id AS CHAR) LIKE ? OR o.status LIKE ? OR DATE_FORMAT(o.created_at, '%d/%m/%Y') LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= "
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reportItemsByOrder = [];
        $orderIds = array_values(array_filter(array_map(
            static fn(array $order): int => (int)($order['id'] ?? 0),
            $orders
        )));

        if ($orderIds !== []) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsStmt = $db->prepare("
                SELECT
                    oi.id,
                    oi.order_id,
                    oi.quantity,
                    COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name, 'Produit') AS product_name,
                    COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name, 'Standard') AS variant_name,
                    COALESCE(SUM(r.quantity_refunded), 0) AS refunded_quantity
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.product_id
                LEFT JOIN product_variants pv ON pv.id = oi.variant_id
                LEFT JOIN refunds r ON r.order_item_id = oi.id
                WHERE oi.order_id IN ($placeholders)
                GROUP BY
                    oi.id, oi.order_id, oi.quantity,
                    oi.product_name_snapshot, oi.variant_name_snapshot, p.name, pv.name
                ORDER BY oi.order_id ASC, oi.id ASC
            ");
            $itemsStmt->execute($orderIds);

            foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $reportItem) {
                $reportItem['remaining_quantity'] = max(
                    0,
                    (int)$reportItem['quantity'] - (int)$reportItem['refunded_quantity']
                );
                $reportItemsByOrder[(int)$reportItem['order_id']][] = $reportItem;
            }
        }

        foreach ($orders as &$order) {
            $order['net_paid_total'] = max((float)$order['paid_total'] - (float)$order['refunded_total'], 0);
            $order['report_items'] = $reportItemsByOrder[(int)$order['id']] ?? [];
        }
        unset($order);

        return $orders;
    }

    public static function getDetailedByUserId(int $orderId, int $userId): ?array
    {
        $db = self::getDB();

        $orderStmt = $db->prepare("
            SELECT
                o.*,
                u.username,
                u.firstname,
                u.lastname,
                u.email
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            WHERE o.id = ? AND o.user_id = ?
            LIMIT 1
        ");
        $orderStmt->execute([$orderId, $userId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.variant_id,
                oi.line_type,
                oi.quantity,
                oi.unit_price,
                oi.currency,
                (oi.quantity * oi.unit_price) AS line_total,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name) AS product_name,
                p.image AS product_image,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name) AS variant_name,
                pv.image AS variant_image,
                CASE
                    WHEN NULLIF(oi.variant_name_snapshot, '') IS NULL
                    THEN MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END)
                    ELSE NULL
                END AS flavor,
                COALESCE(SUM(r.quantity_refunded), 0) AS quantity_refunded,
                COALESCE(SUM(r.amount), 0) AS refunded_amount
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN variant_attributes va ON va.variant_id = oi.variant_id
            LEFT JOIN refunds r ON r.order_item_id = oi.id
            WHERE oi.order_id = ?
            GROUP BY
                oi.id, oi.order_id, oi.product_id, oi.variant_id, oi.line_type, oi.product_name_snapshot,
                oi.variant_name_snapshot, oi.quantity, oi.unit_price, oi.currency,
                p.name, p.image, pv.name, pv.image
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $orderedQty = (int)$item['quantity'];
            $refundedQty = (int)$item['quantity_refunded'];
            $item['remaining_quantity'] = max($orderedQty - $refundedQty, 0);
            $item['display_name'] = !empty($item['flavor'])
                ? $item['flavor']
                : (!empty($item['variant_name']) ? $item['variant_name'] : $item['product_name']);
        }
        unset($item);

        $paymentsStmt = $db->prepare("
            SELECT
                p.id,
                p.order_id,
                p.amount_paid,
                p.payment_date,
                p.method,
                p.provider,
                p.provider_ref,
                p.status,
                p.currency
            FROM payments p
            WHERE p.order_id = ?
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $paymentsStmt->execute([$orderId]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $refundsStmt = $db->prepare("
            SELECT
                r.*
            FROM refunds r
            WHERE r.order_id = ?
            ORDER BY r.created_at DESC, r.id DESC
        ");
        $refundsStmt->execute([$orderId]);
        $refunds = $refundsStmt->fetchAll(PDO::FETCH_ASSOC);

        $paidTotal = 0.0;
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'captured') {
                $paidTotal += (float)$payment['amount_paid'];
            }
        }

        $refundedTotal = 0.0;
        foreach ($refunds as $refund) {
            $refundedTotal += (float)$refund['amount'];
        }

        $order['items'] = $items;
        $order['payments'] = $payments;
        $order['refunds'] = $refunds;
        $order['paid_total'] = $paidTotal;
        $order['refunded_total'] = $refundedTotal;
        $order['net_paid_total'] = max($paidTotal - $refundedTotal, 0);

        return $order;
    }


    public static function getAdminUserCommerceSnapshot(int $userId): array
    {
        $db = self::getDB();

        $stats = self::getUserStats($userId);

        $lastOrderStmt = $db->prepare("\n            SELECT\n                o.id,\n                o.total_price,\n                o.status,\n                o.created_at\n            FROM orders o\n            WHERE o.user_id = ?\n            ORDER BY o.created_at DESC, o.id DESC\n            LIMIT 1\n        ");
        $lastOrderStmt->execute([$userId]);
        $lastOrder = $lastOrderStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $favoriteProductStmt = $db->prepare("\n            SELECT\n                p.id AS product_id,\n                p.name AS product_name,\n                SUM(oi.quantity) AS total_quantity,\n                MAX(o.created_at) AS last_order_at\n            FROM order_items oi\n            INNER JOIN orders o ON o.id = oi.order_id\n            INNER JOIN products p ON p.id = oi.product_id\n            WHERE o.user_id = ?\n              AND o.status <> 'cancelled'\n            GROUP BY p.id, p.name\n            ORDER BY total_quantity DESC, last_order_at DESC, p.name ASC\n            LIMIT 1\n        ");
        $favoriteProductStmt->execute([$userId]);
        $favoriteProduct = $favoriteProductStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'orders_total' => (int)($stats['orders_total'] ?? 0),
            'orders_pending_payment' => (int)($stats['orders_pending_payment'] ?? 0),
            'orders_paid' => (int)($stats['orders_paid'] ?? 0),
            'orders_cancelled' => (int)($stats['orders_cancelled'] ?? 0),
            'orders_refunded' => (int)($stats['orders_refunded'] ?? 0),
            'orders_partially_refunded' => (int)($stats['orders_partially_refunded'] ?? 0),
            'pending_amount' => (float)($stats['pending_amount'] ?? 0),
            'paid_amount' => (float)($stats['paid_amount'] ?? 0),
            'last_order' => $lastOrder,
            'favorite_product' => $favoriteProduct,
        ];
    }

}
