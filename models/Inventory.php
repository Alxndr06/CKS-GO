<?php
require_once __DIR__ . '/../core/Model.php';

class Inventory extends Model
{
    private const ALLOWED_REASONS = [
        'sale',
        'refund',
        'manual',
        'adjustment',
        'loss',
        'theft',
        'order_checkout',
        'restock',
        'count',
        'correction',
        'return',
    ];

    public static function adjustStock(
        PDO $db,
        int $variantId,
        int $delta,
        string $reason,
        array $context = []
    ): array {
        $variant = self::lockVariant($db, $variantId, !empty($context['allow_archived']));
        $stockBefore = (int)$variant['stock_quantity'];
        $stockAfter = $stockBefore + $delta;

        if ($stockAfter < 0) {
            throw new RuntimeException('Stock insuffisant pour cette opération.');
        }

        $updateStmt = $db->prepare("
            UPDATE product_variants
            SET stock_quantity = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$stockAfter, $variantId]);

        self::recordMovement(
            $db,
            $variantId,
            $delta,
            $reason,
            $stockBefore,
            $stockAfter,
            $context
        );

        return [
            'variant' => $variant,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'delta' => $delta,
        ];
    }

    public static function setStock(
        PDO $db,
        int $variantId,
        int $newStock,
        string $reason,
        array $context = []
    ): array {
        if ($newStock < 0) {
            throw new RuntimeException('Le stock ne peut pas être négatif.');
        }

        $variant = self::lockVariant($db, $variantId, !empty($context['allow_archived']));
        $stockBefore = (int)$variant['stock_quantity'];
        $delta = $newStock - $stockBefore;

        $updateStmt = $db->prepare("
            UPDATE product_variants
            SET stock_quantity = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$newStock, $variantId]);

        self::recordMovement(
            $db,
            $variantId,
            $delta,
            $reason,
            $stockBefore,
            $newStock,
            $context
        );

        return [
            'variant' => $variant,
            'stock_before' => $stockBefore,
            'stock_after' => $newStock,
            'delta' => $delta,
        ];
    }

    public static function recordMovement(
        PDO $db,
        int $variantId,
        int $delta,
        string $reason,
        ?int $stockBefore,
        ?int $stockAfter,
        array $context = []
    ): void {
        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            throw new RuntimeException('Motif de mouvement de stock invalide.');
        }

        $adminId = (int)($context['admin_id'] ?? 0);
        $orderId = (int)($context['order_id'] ?? 0);
        $meta = self::cleanText((string)($context['meta'] ?? ''), 255);
        $note = self::cleanText((string)($context['note'] ?? ''), 255);

        $stmt = $db->prepare("
            INSERT INTO inventory_movements (
                variant_id,
                admin_id,
                order_id,
                qty,
                stock_before,
                stock_after,
                reason,
                meta,
                note
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $variantId,
            $adminId > 0 ? $adminId : null,
            $orderId > 0 ? $orderId : null,
            $delta,
            $stockBefore,
            $stockAfter,
            $reason,
            $meta !== '' ? $meta : null,
            $note !== '' ? $note : null,
        ]);
    }

    private static function lockVariant(PDO $db, int $variantId, bool $allowArchived = false): array
    {
        if ($variantId <= 0) {
            throw new RuntimeException('Variante introuvable.');
        }

        $stmt = $db->prepare("
            SELECT
                pv.id,
                pv.product_id,
                pv.name,
                pv.sku,
                pv.stock_quantity,
                pv.is_active,
                pv.archived_at
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            WHERE pv.id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$variantId]);
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$variant || (!$allowArchived && !empty($variant['archived_at']))) {
            throw new RuntimeException('Variante introuvable ou archivée.');
        }

        if (!$allowArchived) {
            $productStmt = $db->prepare("SELECT archived_at FROM products WHERE id = ? LIMIT 1");
            $productStmt->execute([(int)$variant['product_id']]);
            if (!empty($productStmt->fetchColumn())) {
                throw new RuntimeException('Variante introuvable ou archivée.');
            }
        }

        return $variant;
    }

    private static function cleanText(string $value, int $maxLength): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));
        return substr($value, 0, $maxLength);
    }
}
