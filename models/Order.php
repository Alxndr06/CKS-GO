<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Product.php';

class Order extends Model
{
    public static function createFromCart(int $userId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $cartStmt = $db->prepare("
                SELECT id
                FROM carts
                WHERE user_id = ?
                LIMIT 1
            ");
            $cartStmt->execute([$userId]);
            $cartId = $cartStmt->fetchColumn();

            if (!$cartId) {
                throw new RuntimeException("Aucun panier trouvé.");
            }

            $itemsStmt = $db->prepare("
                SELECT
                    ci.id AS cart_item_id,
                    ci.quantity,
                    v.id AS variant_id,
                    v.product_id,
                    v.price AS unit_price,
                    v.stock_quantity,
                    v.is_active AS variant_is_active,
                    p.name AS product_name,
                    p.is_active AS product_is_active
                FROM cart_items ci
                INNER JOIN product_variants v ON v.id = ci.variant_id
                INNER JOIN products p ON p.id = v.product_id
                WHERE ci.cart_id = ?
                ORDER BY ci.id ASC
            ");
            $itemsStmt->execute([(int)$cartId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new RuntimeException("Le panier est vide.");
            }

            $total = 0.0;

            foreach ($items as $item) {
                if ((int)$item['product_is_active'] !== 1) {
                    throw new RuntimeException("Le produit '{$item['product_name']}' n'est plus disponible.");
                }

                if ((int)$item['variant_is_active'] !== 1) {
                    throw new RuntimeException("Une variante n'est plus disponible.");
                }

                if ((int)$item['stock_quantity'] < (int)$item['quantity']) {
                    throw new RuntimeException("Stock insuffisant pour '{$item['product_name']}'.");
                }

                $total += ((float)$item['unit_price'] * (int)$item['quantity']);
            }

            $orderStmt = $db->prepare("
                INSERT INTO orders (user_id, status, currency, total_price)
                VALUES (?, 'pending_payment', 'EUR', ?)
            ");
            $orderStmt->execute([$userId, $total]);
            $orderId = (int)$db->lastInsertId();

            $orderItemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, currency)
                VALUES (?, ?, ?, ?, ?, 'EUR')
            ");

            $stockUpdateStmt = $db->prepare("
                UPDATE product_variants
                SET stock_quantity = stock_quantity - ?
                WHERE id = ?
            ");

            $movementStmt = $db->prepare("
                INSERT INTO inventory_movements (variant_id, qty, reason, meta)
                VALUES (?, ?, 'sale', ?)
            ");

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $variantId = (int)$item['variant_id'];
                $productId = (int)$item['product_id'];
                $unitPrice = (float)$item['unit_price'];

                $orderItemStmt->execute([
                    $orderId,
                    $productId,
                    $variantId,
                    $qty,
                    $unitPrice
                ]);

                $stockUpdateStmt->execute([$qty, $variantId]);

                $movementStmt->execute([
                    $variantId,
                    -$qty,
                    "order_id={$orderId}"
                ]);
            }

            $noteStmt = $db->prepare("
                UPDATE users
                SET note = note + ?
                WHERE id = ?
            ");
            $noteStmt->execute([$total, $userId]);

            $clearCartStmt = $db->prepare("
                DELETE FROM cart_items
                WHERE cart_id = ?
            ");
            $clearCartStmt->execute([(int)$cartId]);

            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createAdminChargeForUser(int $userId, int $variantId, int $quantity, int $adminId): int
    {
        return self::createAdminMultiChargeForUser($userId, [
            [
                'variant_id' => $variantId,
                'quantity' => $quantity
            ]
        ], $adminId);
    }

    public static function createAdminMultiChargeForUser(int $userId, array $lines, int $adminId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($userId <= 0) {
                throw new RuntimeException("Utilisateur invalide.");
            }

            if (empty($lines)) {
                throw new RuntimeException("Aucun produit à facturer.");
            }

            $normalizedLines = [];

            foreach ($lines as $line) {
                $variantId = isset($line['variant_id']) ? (int)$line['variant_id'] : 0;
                $quantity = isset($line['quantity']) ? (int)$line['quantity'] : 0;

                if ($variantId <= 0 || $quantity <= 0) {
                    continue;
                }

                if (!isset($normalizedLines[$variantId])) {
                    $normalizedLines[$variantId] = 0;
                }

                $normalizedLines[$variantId] += $quantity;
            }

            if (empty($normalizedLines)) {
                throw new RuntimeException("Aucune ligne valide à facturer.");
            }

            $preparedLines = [];
            $total = 0.0;

            foreach ($normalizedLines as $variantId => $quantity) {
                $variant = Product::getVariantById((int)$variantId);

                if (!$variant) {
                    throw new RuntimeException("Une variante sélectionnée est introuvable.");
                }

                if ((int)$variant['product_is_active'] !== 1) {
                    throw new RuntimeException("Le produit '{$variant['product_name']}' n'est pas disponible.");
                }

                if ((int)$variant['is_active'] !== 1) {
                    throw new RuntimeException("La variante '{$variant['name']}' n'est pas disponible.");
                }

                if ((int)$variant['stock_quantity'] < $quantity) {
                    throw new RuntimeException("Stock insuffisant pour '{$variant['product_name']}' ({$variant['name']}).");
                }

                $unitPrice = (float)$variant['price'];
                $lineTotal = $unitPrice * $quantity;
                $total += $lineTotal;

                $preparedLines[] = [
                    'variant_id' => (int)$variant['id'],
                    'product_id' => (int)$variant['product_id'],
                    'product_name' => $variant['product_name'],
                    'variant_name' => $variant['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
            }

            if ($total <= 0) {
                throw new RuntimeException("Le total de la facturation est invalide.");
            }

            $orderStmt = $db->prepare("
                INSERT INTO orders (user_id, status, currency, total_price)
                VALUES (?, 'pending_payment', 'EUR', ?)
            ");
            $orderStmt->execute([$userId, $total]);
            $orderId = (int)$db->lastInsertId();

            $orderItemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, currency)
                VALUES (?, ?, ?, ?, ?, 'EUR')
            ");

            $stockUpdateStmt = $db->prepare("
                UPDATE product_variants
                SET stock_quantity = stock_quantity - ?
                WHERE id = ?
            ");

            $movementStmt = $db->prepare("
                INSERT INTO inventory_movements (variant_id, qty, reason, meta)
                VALUES (?, ?, 'sale', ?)
            ");

            foreach ($preparedLines as $line) {
                $orderItemStmt->execute([
                    $orderId,
                    $line['product_id'],
                    $line['variant_id'],
                    $line['quantity'],
                    $line['unit_price']
                ]);

                $stockUpdateStmt->execute([
                    $line['quantity'],
                    $line['variant_id']
                ]);

                $movementStmt->execute([
                    $line['variant_id'],
                    -$line['quantity'],
                    "admin_charge_order_id={$orderId}"
                ]);
            }

            $noteStmt = $db->prepare("
                UPDATE users
                SET note = note + ?
                WHERE id = ?
            ");
            $noteStmt->execute([$total, $userId]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'admin_multi_charge_created',
                'Facturation multi-produit via admin / commande #' . $orderId . ' / user #' . $userId . ' / lignes ' . count($preparedLines)
            ]);

            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getOrderSummary(int $orderId, int $userId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id, user_id, status, currency, total_price, created_at
            FROM orders
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.id,
                oi.quantity,
                oi.unit_price,
                p.name AS product_name,
                v.name AS variant_name,
                MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END) AS flavor
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants v ON v.id = oi.variant_id
            LEFT JOIN variant_attributes va ON va.variant_id = oi.variant_id
            WHERE oi.order_id = ?
            GROUP BY oi.id, oi.quantity, oi.unit_price, p.name, v.name
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['display_variant'] = !empty($item['flavor'])
                ? $item['flavor']
                : (!empty($item['variant_name']) ? $item['variant_name'] : 'Variante');
            $item['line_total'] = ((float)$item['unit_price'] * (int)$item['quantity']);
        }

        $order['items'] = $items;

        return $order;
    }

    public static function getRecentByUserId(int $userId, int $limit = 5): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                o.id,
                o.status,
                o.currency,
                o.total_price,
                o.created_at,
                COUNT(oi.id) AS item_lines
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY o.id, o.status, o.currency, o.total_price, o.created_at
            ORDER BY o.created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUserStats(int $userId): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_price), 0) AS total_orders_amount,
                COALESCE(SUM(CASE WHEN status = 'pending_payment' THEN total_price ELSE 0 END), 0) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS paid_amount
            FROM orders
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'total_orders_amount' => (float)($stats['total_orders_amount'] ?? 0),
            'pending_amount' => (float)($stats['pending_amount'] ?? 0),
            'paid_amount' => (float)($stats['paid_amount'] ?? 0),
        ];
    }

    public static function getRecentOrders(int $limit = 6): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                o.id,
                o.user_id,
                o.status,
                o.currency,
                o.total_price,
                o.created_at,
                u.firstname,
                u.lastname,
                u.username,
                COUNT(oi.id) AS item_lines
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            GROUP BY
                o.id, o.user_id, o.status, o.currency, o.total_price, o.created_at,
                u.firstname, u.lastname, u.username
            ORDER BY o.created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAdminStats(): array
    {
        $db = self::getDB();

        $stmt = $db->query("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_price), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN status = 'pending_payment' THEN total_price ELSE 0 END), 0) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS paid_amount
            FROM orders
        ");

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'total_amount' => (float)($stats['total_amount'] ?? 0),
            'pending_amount' => (float)($stats['pending_amount'] ?? 0),
            'paid_amount' => (float)($stats['paid_amount'] ?? 0),
        ];
    }
}