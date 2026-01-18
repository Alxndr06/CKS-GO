<?php
require_once __DIR__ . '/../core/Model.php';

class Cart extends Model
{
    // Ajoute ou incrémente une ligne de panier pour (user, variant). Le stock est vérifié sur la variante.
    public static function addItem(int $userId, int $productId, int $variantId, int $qty): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            // 1) Check stock de la variante
            $st = $db->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
            $st->execute([$variantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new RuntimeException("Variante introuvable."); }
            if ((int)$row['stock_quantity'] <= 0) { throw new RuntimeException("Variante en rupture."); }

            // 2) Existe déjà dans le panier ?
            $st = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id=? AND variant_id=?");
            $st->execute([$userId, $variantId]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $up = $db->prepare("UPDATE cart_items SET quantity=?, added_at=NOW() WHERE id=?");
                $up->execute([$newQty, $existing['id']]);
            } else {
                $ins = $db->prepare("
                    INSERT INTO cart_items (user_id, product_id, variant_id, quantity, added_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $ins->execute([$userId, $productId, $variantId, $qty]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}