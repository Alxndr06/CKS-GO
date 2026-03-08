<?php
require_once __DIR__ . '/../core/Model.php';

class Cart extends Model
{
    private static function getOrCreateCartId(int $userId): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id
            FROM carts
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        $cartId = $stmt->fetchColumn();
        if ($cartId) {
            return (int)$cartId;
        }

        $stmt = $db->prepare("
            INSERT INTO carts (user_id, session_id, is_locked)
            VALUES (?, NULL, 0)
        ");
        $stmt->execute([$userId]);

        return (int)$db->lastInsertId();
    }

    private static function getVariantForCart(int $variantId): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.product_id,
                v.price,
                v.stock_quantity,
                v.is_active,
                p.name AS product_name,
                p.is_active AS product_is_active
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            WHERE v.id = ?
            LIMIT 1
        ");
        $stmt->execute([$variantId]);

        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$variant) {
            throw new RuntimeException("Variante introuvable.");
        }

        if ((int)$variant['product_is_active'] !== 1) {
            throw new RuntimeException("Le produit n'est pas disponible.");
        }

        if ((int)$variant['is_active'] !== 1) {
            throw new RuntimeException("Cette variante n'est pas disponible.");
        }

        return $variant;
    }

    public static function addItem(int $userId, int $productId, int $variantId, int $qty): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $cartId = self::getOrCreateCartId($userId);
            $variant = self::getVariantForCart($variantId);

            if ((int)$variant['product_id'] !== $productId) {
                throw new RuntimeException("La variante ne correspond pas au produit sélectionné.");
            }

            $stockQty = (int)$variant['stock_quantity'];
            if ($stockQty <= 0) {
                throw new RuntimeException("Variante en rupture.");
            }

            $stmt = $db->prepare("
                SELECT id, quantity
                FROM cart_items
                WHERE cart_id = ? AND variant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$cartId, $variantId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $currentQty = $existing ? (int)$existing['quantity'] : 0;
            $newQty = $currentQty + $qty;

            if ($newQty > $stockQty) {
                throw new RuntimeException("Stock insuffisant pour la quantité demandée.");
            }

            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newQty, $existing['id']]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO cart_items (cart_id, variant_id, quantity)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$cartId, $variantId, $qty]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getDetailedCart(int $userId): array
    {
        $db = self::getDB();
        $cartId = self::getOrCreateCartId($userId);

        $stmt = $db->prepare("
            SELECT
                ci.id AS cart_item_id,
                ci.quantity,
                ci.variant_id,
                p.id AS product_id,
                p.name AS product_name,
                p.image AS product_image,
                p.description AS product_description,
                v.name AS variant_name,
                v.price AS unit_price,
                v.stock_quantity,
                v.is_active AS variant_is_active,
                MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END) AS flavor
            FROM cart_items ci
            INNER JOIN product_variants v ON v.id = ci.variant_id
            INNER JOIN products p ON p.id = v.product_id
            LEFT JOIN variant_attributes va ON va.variant_id = v.id
            WHERE ci.cart_id = ?
            GROUP BY
                ci.id, ci.quantity, ci.variant_id,
                p.id, p.name, p.image, p.description,
                v.name, v.price, v.stock_quantity, v.is_active
            ORDER BY ci.id DESC
        ");
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0.0;

        foreach ($items as &$item) {
            $item['quantity'] = (int)$item['quantity'];
            $item['stock_quantity'] = (int)$item['stock_quantity'];
            $item['unit_price'] = (float)$item['unit_price'];
            $item['line_total'] = $item['unit_price'] * $item['quantity'];
            $item['display_variant'] = !empty($item['flavor'])
                ? $item['flavor']
                : (!empty($item['variant_name']) ? $item['variant_name'] : 'Variante');
            $item['is_available'] = ((int)$item['variant_is_active'] === 1 && $item['stock_quantity'] > 0);
            $subtotal += $item['line_total'];
        }

        return [
            'cart_id' => $cartId,
            'items' => $items,
            'subtotal' => $subtotal,
            'item_count' => array_sum(array_map(static fn(array $item): int => $item['quantity'], $items)),
        ];
    }

    public static function updateItemQuantity(int $userId, int $cartItemId, int $qty): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $cartId = self::getOrCreateCartId($userId);

            $stmt = $db->prepare("
                SELECT
                    ci.id,
                    ci.variant_id,
                    ci.quantity,
                    v.stock_quantity,
                    v.is_active
                FROM cart_items ci
                INNER JOIN product_variants v ON v.id = ci.variant_id
                WHERE ci.id = ? AND ci.cart_id = ?
                LIMIT 1
            ");
            $stmt->execute([$cartItemId, $cartId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException("Ligne de panier introuvable.");
            }

            if ((int)$item['is_active'] !== 1 || (int)$item['stock_quantity'] <= 0) {
                throw new RuntimeException("Cette variante n'est plus disponible.");
            }

            if ($qty < 1) {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ?");
                $stmt->execute([$cartItemId]);
            } else {
                if ($qty > (int)$item['stock_quantity']) {
                    throw new RuntimeException("Stock insuffisant pour cette quantité.");
                }

                $stmt = $db->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$qty, $cartItemId]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function removeItem(int $userId, int $cartItemId): bool
    {
        $db = self::getDB();
        $cartId = self::getOrCreateCartId($userId);

        $stmt = $db->prepare("
            DELETE FROM cart_items
            WHERE id = ? AND cart_id = ?
        ");

        return $stmt->execute([$cartItemId, $cartId]);
    }

    public static function clear(int $userId): bool
    {
        $db = self::getDB();
        $cartId = self::getOrCreateCartId($userId);

        $stmt = $db->prepare("
            DELETE FROM cart_items
            WHERE cart_id = ?
        ");

        return $stmt->execute([$cartId]);
    }

    public static function getItemCount(int $userId): int
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id
            FROM carts
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        $cartId = $stmt->fetchColumn();
        if (!$cartId) {
            return 0;
        }

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM cart_items
            WHERE cart_id = ?
        ");
        $stmt->execute([(int)$cartId]);

        return (int)$stmt->fetchColumn();
    }
}