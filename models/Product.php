<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Inventory.php';

class Product extends Model
{
    public static function normalizeVisibility(?string $visibility): string
    {
        $visibility = trim((string)$visibility);

        return in_array($visibility, ['public', 'authenticated', 'admin_only'], true)
            ? $visibility
            : 'public';
    }

    private static function getAudienceSql(string $audience, string $alias = 'p'): string
    {
        return match ($audience) {
            'staff' => '1 = 1',
            'authenticated' => $alias . ".visibility IN ('public', 'authenticated')",
            default => $alias . ".visibility = 'public'",
        };
    }

    public static function getLatestPublicProducts(int $limit = 4, string $audience = 'guest'): array
    {
        $limit = max(1, min($limit, 8));
        $db = self::getDB();
        $audienceSql = self::getAudienceSql($audience);

        $stmt = $db->query("
            SELECT
                p.id,
                p.name,
                p.description,
                p.image,
                p.created_at,
                c.name AS category_name,
                MIN(CASE WHEN v.is_active = 1 THEN v.price END) AS min_price,
                COALESCE(SUM(CASE WHEN v.is_active = 1 THEN v.stock_quantity ELSE 0 END), 0) AS total_stock
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id AND v.archived_at IS NULL
            WHERE p.is_active = 1
              AND p.archived_at IS NULL
              AND {$audienceSql}
            GROUP BY p.id, p.name, p.description, p.image, p.created_at, c.name
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function search(?string $categorySlug, ?string $q, string $audience = 'guest'): array
    {
        $db = self::getDB();

        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.image,
                p.visibility,
                MIN(CASE WHEN v.is_active = 1 THEN v.price END) AS min_price
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id AND v.archived_at IS NULL
            WHERE p.is_active = 1
              AND p.archived_at IS NULL
        ";

        $params = [];

        $sql .= ' AND ' . self::getAudienceSql($audience);

        if ($categorySlug) {
            $sql .= " AND c.slug = ?";
            $params[] = $categorySlug;
        }

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR v.name LIKE ? OR v.sku LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= "
            GROUP BY p.id, p.name, p.description, p.image, p.visibility
            ORDER BY p.name ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function variants(int $productId): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.product_id,
                v.sku,
                v.name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image,
                v.is_active,
                v.sort_order,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.product_id = ?
              AND v.archived_at IS NULL
            GROUP BY
                v.id, v.product_id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.is_active, v.sort_order
            ORDER BY v.sort_order ASC, v.name ASC
        ");
        $stmt->execute([$productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function variantsByProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $db = self::getDB();
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.product_id,
                v.sku,
                v.name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image,
                v.is_active,
                v.sort_order,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.product_id IN ($placeholders)
              AND v.archived_at IS NULL
            GROUP BY
                v.id, v.product_id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.is_active, v.sort_order
            ORDER BY v.product_id ASC, v.sort_order ASC, v.name ASC
        ");
        $stmt->execute($productIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int)$row['product_id']][] = $row;
        }

        return $grouped;
    }

    public static function getVariantById(int $variantId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.product_id,
                v.sku,
                v.name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image,
                v.is_active,
                v.sort_order,
                p.name AS product_name,
                p.image AS product_image,
                p.is_active AS product_is_active,
                p.archived_at AS product_archived_at,
                p.visibility,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.id = ?
              AND v.archived_at IS NULL
              AND p.archived_at IS NULL
            GROUP BY
                v.id, v.product_id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.is_active, v.sort_order,
                p.name, p.image, p.is_active, p.archived_at, p.visibility
            LIMIT 1
        ");
        $stmt->execute([$variantId]);

        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $variant ?: null;
    }

    public static function getBillableProductsWithVariants(): array
    {
        $db = self::getDB();

        $stmt = $db->query("
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.description,
                p.image AS product_image,
                v.id AS variant_id,
                v.sku,
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.is_active,
                v.image AS variant_image,
                v.sort_order,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            INNER JOIN product_variants v ON v.product_id = p.id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE p.is_active = 1
              AND v.is_active = 1
              AND p.archived_at IS NULL
              AND v.archived_at IS NULL
            GROUP BY
                p.id, p.name, p.description, p.image,
                v.id, v.sku, v.name, v.price, v.stock_quantity, v.is_active, v.image, v.sort_order
            ORDER BY p.name ASC, v.sort_order ASC, v.name ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $products = [];

        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'id' => $productId,
                    'name' => $row['product_name'],
                    'description' => $row['description'],
                    'image' => $row['product_image'],
                    'variants' => []
                ];
            }

            $products[$productId]['variants'][] = [
                'id' => (int)$row['variant_id'],
                'sku' => $row['sku'],
                'name' => $row['variant_name'],
                'price' => (float)$row['price'],
                'stock_quantity' => (int)$row['stock_quantity'],
                'is_active' => (int)$row['is_active'],
                'image' => $row['variant_image'],
                'sort_order' => (int)$row['sort_order'],
                'flavor' => $row['flavor']
            ];
        }

        return array_values($products);
    }

    public static function getAdminCatalog(?string $q = null, string $archiveState = 'active'): array
    {
        $db = self::getDB();
        $archiveState = in_array($archiveState, ['active', 'archived', 'all'], true)
            ? $archiveState
            : 'active';

        $sql = "
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.description,
                p.image AS product_image,
                p.is_active AS product_is_active,
                p.archived_at AS product_archived_at,
                p.visibility,
                c.name AS category_name,
                v.id AS variant_id,
                v.sku,
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image AS variant_image,
                v.sort_order,
                v.is_active AS variant_is_active,
                v.archived_at AS variant_archived_at,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id AND v.archived_at IS NULL
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE 1
        ";

        $params = [];

        if ($archiveState === 'active') {
            $sql .= " AND p.archived_at IS NULL";
        } elseif ($archiveState === 'archived') {
            $sql .= " AND p.archived_at IS NOT NULL";
        }

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    p.name LIKE ?
                    OR p.description LIKE ?
                    OR c.name LIKE ?
                    OR v.name LIKE ?
                    OR v.sku LIKE ?
                    OR a.attr_value LIKE ?
                )
            ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= "
            GROUP BY
                p.id, p.name, p.description, p.image, p.is_active, p.archived_at, p.visibility,
                c.name,
                v.id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.sort_order, v.is_active, v.archived_at
            ORDER BY p.name ASC, v.sort_order ASC, v.name ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $products = [];

        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'id' => $productId,
                    'name' => $row['product_name'],
                    'description' => $row['description'],
                    'image' => $row['product_image'],
                    'is_active' => (int)$row['product_is_active'],
                    'archived_at' => $row['product_archived_at'],
                    'visibility' => $row['visibility'] ?? 'public',
                    'category_name' => $row['category_name'] ?? null,
                    'variants' => [],
                    'total_stock' => 0,
                    'sellable_stock' => 0,
                    'variant_count' => 0,
                    'min_price' => null,
                    'max_price' => null
                ];
            }

            if (!empty($row['variant_id'])) {
                $price = (float)$row['price'];
                $stock = (int)$row['stock_quantity'];

                $products[$productId]['variants'][] = [
                    'id' => (int)$row['variant_id'],
                    'sku' => $row['sku'],
                    'name' => $row['variant_name'],
                    'flavor' => $row['flavor'],
                    'display_name' => !empty($row['flavor']) ? $row['flavor'] : $row['variant_name'],
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => (int)$row['low_stock_threshold'],
                    'image' => $row['variant_image'],
                    'sort_order' => (int)$row['sort_order'],
                    'is_active' => (int)$row['variant_is_active'],
                    'archived_at' => $row['variant_archived_at']
                ];

                $products[$productId]['total_stock'] += $stock;
                if (
                    (int)$row['product_is_active'] === 1
                    && (int)$row['variant_is_active'] === 1
                    && empty($row['product_archived_at'])
                ) {
                    $products[$productId]['sellable_stock'] += $stock;
                }
                $products[$productId]['variant_count']++;

                if ($products[$productId]['min_price'] === null || $price < $products[$productId]['min_price']) {
                    $products[$productId]['min_price'] = $price;
                }

                if ($products[$productId]['max_price'] === null || $price > $products[$productId]['max_price']) {
                    $products[$productId]['max_price'] = $price;
                }
            }
        }

        return array_values($products);
    }

    public static function getAdminProductById(int $productId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.description,
                p.image AS product_image,
                p.is_active AS product_is_active,
                p.archived_at AS product_archived_at,
                p.visibility,
                c.name AS category_name,
                v.id AS variant_id,
                v.sku,
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image AS variant_image,
                v.sort_order,
                v.is_active AS variant_is_active,
                v.archived_at AS variant_archived_at,
                last_movement.last_stock_movement_at,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            LEFT JOIN (
                SELECT variant_id, MAX(created_at) AS last_stock_movement_at
                FROM inventory_movements
                GROUP BY variant_id
            ) last_movement ON last_movement.variant_id = v.id
            WHERE p.id = ?
            GROUP BY
                p.id, p.name, p.description, p.image, p.is_active, p.archived_at, p.visibility,
                c.name,
                v.id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.sort_order, v.is_active, v.archived_at, last_movement.last_stock_movement_at
            ORDER BY v.archived_at IS NOT NULL ASC, v.sort_order ASC, v.name ASC
        ");
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        }

        $first = $rows[0];

        $product = [
            'id' => (int)$first['product_id'],
            'name' => $first['product_name'],
            'description' => $first['description'],
            'image' => $first['product_image'],
            'is_active' => (int)$first['product_is_active'],
            'archived_at' => $first['product_archived_at'],
            'visibility' => $first['visibility'] ?? 'public',
            'category_name' => $first['category_name'] ?? null,
            'variants' => [],
            'total_stock' => 0,
            'sellable_stock' => 0,
            'variant_count' => 0
        ];

        foreach ($rows as $row) {
            if (empty($row['variant_id'])) {
                continue;
            }

            $stock = (int)$row['stock_quantity'];

            $product['variants'][] = [
                'id' => (int)$row['variant_id'],
                'sku' => $row['sku'],
                'name' => $row['variant_name'],
                'flavor' => $row['flavor'],
                'display_name' => !empty($row['flavor']) ? $row['flavor'] : $row['variant_name'],
                'price' => (float)$row['price'],
                'stock_quantity' => $stock,
                'low_stock_threshold' => (int)$row['low_stock_threshold'],
                'image' => $row['variant_image'],
                'sort_order' => (int)$row['sort_order'],
                'is_active' => (int)$row['variant_is_active'],
                'archived_at' => $row['variant_archived_at'],
                'last_stock_movement_at' => $row['last_stock_movement_at']
            ];

            $product['total_stock'] += $stock;
            if (
                (int)$row['product_is_active'] === 1
                && (int)$row['variant_is_active'] === 1
                && empty($row['product_archived_at'])
                && empty($row['variant_archived_at'])
            ) {
                $product['sellable_stock'] += $stock;
            }
            $product['variant_count']++;
        }

        return $product;
    }

    public static function getAdminVariantById(int $variantId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                v.id,
                v.product_id,
                v.sku,
                v.name,
                v.price,
                v.stock_quantity,
                v.low_stock_threshold,
                v.image,
                v.is_active,
                v.archived_at,
                v.sort_order,
                p.name AS product_name,
                p.image AS product_image,
                p.description AS product_description,
                p.is_active AS product_is_active,
                p.archived_at AS product_archived_at,
                p.visibility,
                c.name AS category_name,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.id = ?
            GROUP BY
                v.id, v.product_id, v.sku, v.name, v.price, v.stock_quantity, v.low_stock_threshold,
                v.image, v.is_active, v.archived_at, v.sort_order,
                p.name, p.image, p.description, p.is_active, p.archived_at, p.visibility,
                c.name
            LIMIT 1
        ");
        $stmt->execute([$variantId]);

        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $variant ?: null;
    }

    public static function updateVariantStock(int $variantId, int $newStock, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $movement = Inventory::setStock(
                $db,
                $variantId,
                $newStock,
                'adjustment',
                [
                    'admin_id' => $adminId,
                    'meta' => 'source=legacy_stock_form',
                    'note' => 'Mise à jour depuis la fiche produit',
                ]
            );

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'variant_stock_updated',
                'Ajustement stock variante #' . $variantId
                    . ' / de ' . $movement['stock_before']
                    . ' à ' . $movement['stock_after']
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function adjustVariantStock(
        int $variantId,
        string $mode,
        int $quantity,
        string $reason,
        ?string $note,
        int $adminId
    ): array {
        $mode = in_array($mode, ['increase', 'decrease', 'set'], true) ? $mode : '';
        $allowedReasons = ['restock', 'count', 'correction', 'return', 'loss', 'theft', 'manual'];

        if ($mode === '') {
            throw new RuntimeException('Type d’ajustement invalide.');
        }

        if (!in_array($reason, $allowedReasons, true)) {
            throw new RuntimeException('Motif d’ajustement invalide.');
        }

        if ($quantity < 0 || ($mode !== 'set' && $quantity === 0)) {
            throw new RuntimeException('La quantité doit être supérieure à 0.');
        }

        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($mode === 'set') {
                $movement = Inventory::setStock(
                    $db,
                    $variantId,
                    $quantity,
                    $reason,
                    [
                        'admin_id' => $adminId,
                        'meta' => 'source=inventory_cockpit;mode=set',
                        'note' => $note,
                    ]
                );
            } else {
                $delta = $mode === 'increase' ? $quantity : -$quantity;
                $movement = Inventory::adjustStock(
                    $db,
                    $variantId,
                    $delta,
                    $reason,
                    [
                        'admin_id' => $adminId,
                        'meta' => 'source=inventory_cockpit;mode=' . $mode,
                        'note' => $note,
                    ]
                );
            }

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, 'variant_stock_adjusted', ?)
            ");
            $logStmt->execute([
                $adminId,
                sprintf(
                    'Variante #%d / stock %d -> %d / motif %s',
                    $variantId,
                    $movement['stock_before'],
                    $movement['stock_after'],
                    $reason
                ),
            ]);

            $db->commit();
            return $movement;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updateProduct(int $productId, array $data, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE products
                SET
                    category_id = ?,
                    name = ?,
                    description = ?,
                    image = ?,
                    is_active = ?,
                    visibility = ?
                WHERE id = ?
                  AND archived_at IS NULL
            ");
            $stmt->execute([
                $data['category_id'] ?: null,
                trim((string)$data['name']),
                trim((string)$data['description']),
                trim((string)$data['image']),
                (int)$data['is_active'],
                self::normalizeVisibility($data['visibility'] ?? 'public'),
                $productId
            ]);

            $checkStmt = $db->prepare("SELECT id FROM products WHERE id = ? AND archived_at IS NULL");
            $checkStmt->execute([$productId]);

            if (!$checkStmt->fetchColumn()) {
                throw new RuntimeException("Produit introuvable.");
            }

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'product_updated',
                'Produit #' . $productId . ' modifié'
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function updateVariant(int $variantId, array $data, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $variant = self::getAdminVariantById($variantId);

            if (!$variant) {
                throw new RuntimeException("Variante introuvable.");
            }

            if (!empty($variant['archived_at']) || !empty($variant['product_archived_at'])) {
                throw new RuntimeException("Une variante archivée ne peut pas être modifiée.");
            }

            $productId = (int)$variant['product_id'];
            $newSortOrder = max(0, (int)($data['sort_order'] ?? 0));
            $sku = self::resolveVariantSku(
                $db,
                $productId,
                (string)($data['name'] ?? ''),
                (string)($data['flavor'] ?? ''),
                (string)($data['sku'] ?? ''),
                $variantId
            );
            $lowStockThreshold = max(0, (int)($data['low_stock_threshold'] ?? 5));

            $stmt = $db->prepare("
                UPDATE product_variants
                SET
                    sku = ?,
                    name = ?,
                    price = ?,
                    low_stock_threshold = ?,
                    is_active = ?,
                    sort_order = ?,
                    image = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $sku,
                trim((string)$data['name']),
                (float)$data['price'],
                $lowStockThreshold,
                (int)$data['is_active'],
                $newSortOrder,
                trim((string)($data['image'] ?? '')),
                $variantId
            ]);

            self::syncVariantFlavor($variantId, trim((string)($data['flavor'] ?? '')));

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'variant_updated',
                'Variante #' . $variantId . ' modifiée'
            ]);

            self::normalizeVariantSortOrders($productId, $variantId, $newSortOrder);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createProduct(array $productData, array $variantData, int $adminId): int
    {
        return self::createProductWithVariant($productData, $variantData, $adminId);
    }

    public static function createProductWithVariant(array $productData, array $variantData, int $adminId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO products (name, description, category_id, image, is_active, visibility)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim((string)$productData['name']),
                trim((string)$productData['description']),
                $productData['category_id'] ?: null,
                trim((string)$productData['image']),
                (int)$productData['is_active'],
                self::normalizeVisibility($productData['visibility'] ?? 'public')
            ]);

            $productId = (int)$db->lastInsertId();

            $sortOrder = max(0, (int)($variantData['sort_order'] ?? 0));
            $variantImage = trim((string)($variantData['image'] ?? $productData['image'] ?? ''));
            $sku = self::resolveVariantSku(
                $db,
                $productId,
                (string)($variantData['name'] ?? ''),
                (string)($variantData['flavor'] ?? ''),
                (string)($variantData['sku'] ?? '')
            );
            $lowStockThreshold = max(0, (int)($variantData['low_stock_threshold'] ?? 5));

            $stmt = $db->prepare("
                INSERT INTO product_variants (
                    product_id, sku, name, price, stock_quantity,
                    low_stock_threshold, is_active, image, sort_order
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                $sku,
                trim((string)$variantData['name']),
                (float)$variantData['price'],
                (int)$variantData['stock_quantity'],
                $lowStockThreshold,
                (int)$variantData['is_active'],
                $variantImage,
                $sortOrder
            ]);

            $variantId = (int)$db->lastInsertId();

            if (!empty($variantData['flavor'])) {
                $stmt = $db->prepare("
                    INSERT INTO variant_attributes (variant_id, attr_name, attr_value)
                    VALUES (?, 'flavor', ?)
                ");
                $stmt->execute([
                    $variantId,
                    trim((string)$variantData['flavor'])
                ]);
            }

            if ((int)$variantData['stock_quantity'] > 0) {
                Inventory::recordMovement(
                    $db,
                    $variantId,
                    (int)$variantData['stock_quantity'],
                    'manual',
                    0,
                    (int)$variantData['stock_quantity'],
                    [
                        'admin_id' => $adminId,
                        'meta' => 'source=product_creation',
                        'note' => 'Stock initial',
                    ]
                );
            }

            $stmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $adminId,
                'product_created',
                'Produit #' . $productId . ' créé avec variante #' . $variantId
            ]);

            self::normalizeVariantSortOrders($productId);

            $db->commit();
            return $productId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function createVariant(int $productId, array $variantData, int $adminId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                SELECT id, image
                FROM products
                WHERE id = ?
                  AND archived_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException("Produit introuvable.");
            }

            $sortOrder = max(0, (int)($variantData['sort_order'] ?? 0));
            $variantImage = trim((string)($variantData['image'] ?? $product['image'] ?? ''));
            $sku = self::resolveVariantSku(
                $db,
                $productId,
                (string)($variantData['name'] ?? ''),
                (string)($variantData['flavor'] ?? ''),
                (string)($variantData['sku'] ?? '')
            );
            $lowStockThreshold = max(0, (int)($variantData['low_stock_threshold'] ?? 5));

            $stmt = $db->prepare("
                INSERT INTO product_variants (
                    product_id, sku, name, price, stock_quantity,
                    low_stock_threshold, is_active, image, sort_order
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                $sku,
                trim((string)$variantData['name']),
                (float)$variantData['price'],
                (int)$variantData['stock_quantity'],
                $lowStockThreshold,
                (int)$variantData['is_active'],
                $variantImage,
                $sortOrder
            ]);

            $variantId = (int)$db->lastInsertId();

            if (!empty($variantData['flavor'])) {
                $stmt = $db->prepare("
                    INSERT INTO variant_attributes (variant_id, attr_name, attr_value)
                    VALUES (?, 'flavor', ?)
                ");
                $stmt->execute([
                    $variantId,
                    trim((string)$variantData['flavor'])
                ]);
            }

            if ((int)$variantData['stock_quantity'] > 0) {
                Inventory::recordMovement(
                    $db,
                    $variantId,
                    (int)$variantData['stock_quantity'],
                    'manual',
                    0,
                    (int)$variantData['stock_quantity'],
                    [
                        'admin_id' => $adminId,
                        'meta' => 'source=variant_creation',
                        'note' => 'Stock initial',
                    ]
                );
            }

            $stmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $adminId,
                'variant_created',
                'Variante #' . $variantId . ' ajoutée au produit #' . $productId
            ]);

            self::normalizeVariantSortOrders($productId, $variantId, $sortOrder);

            $db->commit();
            return $variantId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function setProductActiveState(int $productId, bool $isActive, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE products
                SET is_active = ?
                WHERE id = ?
                  AND archived_at IS NULL
            ");
            $stmt->execute([(int)$isActive, $productId]);

            $checkStmt = $db->prepare("SELECT id FROM products WHERE id = ? AND archived_at IS NULL");
            $checkStmt->execute([$productId]);

            if (!$checkStmt->fetchColumn()) {
                throw new RuntimeException("Produit introuvable.");
            }

            $variantStmt = $db->prepare("
                UPDATE product_variants
                SET is_active = ?
                WHERE product_id = ?
                  AND archived_at IS NULL
            ");
            $variantStmt->execute([(int)$isActive, $productId]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                $isActive ? 'product_enabled' : 'product_disabled',
                'Produit #' . $productId . ' / état = ' . ($isActive ? 'actif' : 'inactif')
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteProduct(int $productId, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $checkStmt = $db->prepare("SELECT id, archived_at FROM products WHERE id = ? FOR UPDATE");
            $checkStmt->execute([$productId]);
            $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException("Produit introuvable.");
            }

            if (!empty($product['archived_at'])) {
                throw new RuntimeException("Ce produit est déjà archivé.");
            }

            $cleanupCartStmt = $db->prepare("
                DELETE ci
                FROM cart_items ci
                INNER JOIN product_variants pv ON pv.id = ci.variant_id
                WHERE pv.product_id = ?
            ");
            $cleanupCartStmt->execute([$productId]);

            $archiveStmt = $db->prepare("
                UPDATE products
                SET archived_at = NOW()
                WHERE id = ?
            ");
            $archiveStmt->execute([$productId]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'product_archived',
                'Produit #' . $productId . ' archivé'
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }


    public static function deleteVariant(int $variantId, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $variant = self::getAdminVariantById($variantId);

            if (!$variant) {
                throw new RuntimeException("Variante introuvable.");
            }

            $productId = (int)$variant['product_id'];

            if (!empty($variant['archived_at'])) {
                throw new RuntimeException("Cette variante est déjà archivée.");
            }

            $cleanupCartStmt = $db->prepare("
                DELETE FROM cart_items
                WHERE variant_id = ?
            ");
            $cleanupCartStmt->execute([$variantId]);

            $archiveVariantStmt = $db->prepare("
                UPDATE product_variants
                SET archived_at = NOW()
                WHERE id = ?
            ");
            $archiveVariantStmt->execute([$variantId]);

            if ($archiveVariantStmt->rowCount() <= 0) {
                throw new RuntimeException("Impossible d’archiver cette variante.");
            }

            self::normalizeVariantSortOrders($productId);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'variant_archived',
                'Variante #' . $variantId . ' archivée'
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getInventoryDashboard(?string $q = null, string $state = 'all'): array
    {
        $db = self::getDB();
        $state = in_array($state, ['all', 'available', 'low', 'out', 'inactive'], true) ? $state : 'all';

        $sql = "
            SELECT
                pv.id AS variant_id,
                pv.product_id,
                pv.sku,
                pv.name AS variant_name,
                pv.price,
                pv.stock_quantity,
                pv.low_stock_threshold,
                pv.is_active AS variant_is_active,
                pv.image AS variant_image,
                p.name AS product_name,
                p.image AS product_image,
                p.is_active AS product_is_active,
                c.name AS category_name,
                MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END) AS flavor,
                last_movement.id AS last_movement_id,
                last_movement.qty AS last_movement_qty,
                last_movement.reason AS last_movement_reason,
                last_movement.created_at AS last_movement_at,
                last_movement.stock_before AS last_stock_before,
                last_movement.stock_after AS last_stock_after,
                CONCAT_WS(' ', admin.firstname, admin.lastname) AS last_movement_author
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN variant_attributes va ON va.variant_id = pv.id
            LEFT JOIN inventory_movements last_movement ON last_movement.id = (
                SELECT im2.id
                FROM inventory_movements im2
                WHERE im2.variant_id = pv.id
                ORDER BY im2.created_at DESC, im2.id DESC
                LIMIT 1
            )
            LEFT JOIN users admin ON admin.id = last_movement.admin_id
            WHERE p.archived_at IS NULL
              AND pv.archived_at IS NULL
        ";

        $params = [];
        $q = trim((string)$q);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    p.name LIKE ?
                    OR pv.name LIKE ?
                    OR pv.sku LIKE ?
                    OR va.attr_value LIKE ?
                )
            ";
            $params = [$like, $like, $like, $like];
        }

        if ($state === 'available') {
            $sql .= " AND p.is_active = 1 AND pv.is_active = 1 AND pv.stock_quantity > pv.low_stock_threshold";
        } elseif ($state === 'low') {
            $sql .= " AND p.is_active = 1 AND pv.is_active = 1 AND pv.stock_quantity > 0 AND pv.stock_quantity <= pv.low_stock_threshold";
        } elseif ($state === 'out') {
            $sql .= " AND p.is_active = 1 AND pv.is_active = 1 AND pv.stock_quantity <= 0";
        } elseif ($state === 'inactive') {
            $sql .= " AND (p.is_active = 0 OR pv.is_active = 0)";
        }

        $sql .= "
            GROUP BY
                pv.id, pv.product_id, pv.sku, pv.name, pv.price, pv.stock_quantity,
                pv.low_stock_threshold, pv.is_active, pv.image,
                p.name, p.image, p.is_active, c.name,
                last_movement.id, last_movement.qty, last_movement.reason,
                last_movement.created_at, last_movement.stock_before, last_movement.stock_after,
                admin.firstname, admin.lastname
            ORDER BY
                CASE
                    WHEN pv.stock_quantity <= 0 THEN 0
                    WHEN pv.stock_quantity <= pv.low_stock_threshold THEN 1
                    WHEN p.is_active = 0 OR pv.is_active = 0 THEN 2
                    ELSE 3
                END,
                p.name ASC,
                pv.sort_order ASC,
                pv.name ASC
            LIMIT 250
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statsStmt = $db->query("
            SELECT
                COUNT(*) AS total_variants,
                COALESCE(SUM(pv.stock_quantity), 0) AS physical_stock,
                COALESCE(SUM(CASE WHEN p.is_active = 1 AND pv.is_active = 1 THEN pv.stock_quantity ELSE 0 END), 0) AS sellable_stock,
                SUM(CASE WHEN p.is_active = 1 AND pv.is_active = 1 AND pv.stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_count,
                SUM(CASE WHEN p.is_active = 1 AND pv.is_active = 1 AND pv.stock_quantity > 0 AND pv.stock_quantity <= pv.low_stock_threshold THEN 1 ELSE 0 END) AS low_count,
                SUM(CASE WHEN p.is_active = 0 OR pv.is_active = 0 THEN 1 ELSE 0 END) AS inactive_count
            FROM product_variants pv
            INNER JOIN products p ON p.id = pv.product_id
            WHERE p.archived_at IS NULL
              AND pv.archived_at IS NULL
        ");

        return [
            'items' => $items,
            'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [],
            'movements' => self::getRecentInventoryMovements(20),
        ];
    }

    public static function getRecentInventoryMovements(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $db = self::getDB();
        $stmt = $db->query("
            SELECT
                im.id,
                im.variant_id,
                im.admin_id,
                im.order_id,
                im.qty,
                im.stock_before,
                im.stock_after,
                im.reason,
                im.meta,
                im.note,
                im.created_at,
                pv.sku,
                pv.name AS variant_name,
                p.name AS product_name,
                MAX(CASE WHEN va.attr_name = 'flavor' THEN va.attr_value END) AS flavor,
                CONCAT_WS(' ', admin.firstname, admin.lastname) AS admin_name
            FROM inventory_movements im
            INNER JOIN product_variants pv ON pv.id = im.variant_id
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN variant_attributes va ON va.variant_id = pv.id
            LEFT JOIN users admin ON admin.id = im.admin_id
            GROUP BY
                im.id, im.variant_id, im.admin_id, im.order_id, im.qty,
                im.stock_before, im.stock_after, im.reason, im.meta, im.note, im.created_at,
                pv.sku, pv.name, p.name, admin.firstname, admin.lastname
            ORDER BY im.created_at DESC, im.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function declareInventoryIssue(int $variantId, int $quantity, string $reason, ?string $note, int $adminId): bool
    {
        $reason = in_array($reason, ['loss', 'theft'], true) ? $reason : '';

        if ($reason === '') {
            throw new RuntimeException("Le motif de sortie est invalide.");
        }

        if ($quantity <= 0) {
            throw new RuntimeException("La quantité doit être supérieure à 0.");
        }

        $db = self::getDB();
        $db->beginTransaction();

        try {
            $movement = Inventory::adjustStock(
                $db,
                $variantId,
                -$quantity,
                $reason,
                [
                    'admin_id' => $adminId,
                    'meta' => 'source=inventory_issue',
                    'note' => $note,
                ]
            );

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                $reason === 'theft' ? 'inventory_theft_declared' : 'inventory_loss_declared',
                sprintf(
                    'Sortie stock %s / variante #%d / quantité %d / stock %d -> %d',
                    $reason === 'theft' ? 'vol' : 'perte',
                    $variantId,
                    $quantity,
                    $movement['stock_before'],
                    $movement['stock_after']
                )
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getInventoryIssueStats(int $days = 30): array
    {
        $days = max(1, $days);
        $db = self::getDB();

        $summaryStmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN im.reason = 'loss' THEN ABS(im.qty) ELSE 0 END), 0) AS total_loss_qty,
                COALESCE(SUM(CASE WHEN im.reason = 'theft' THEN ABS(im.qty) ELSE 0 END), 0) AS total_theft_qty,
                COALESCE(SUM(ABS(im.qty) * pv.price), 0) AS estimated_amount,
                COUNT(*) AS total_events
            FROM inventory_movements im
            INNER JOIN product_variants pv ON pv.id = im.variant_id
            WHERE im.reason IN ('loss', 'theft')
              AND im.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $summaryStmt->execute([$days]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $topStmt = $db->prepare("
            SELECT
                pv.id AS variant_id,
                pv.name AS variant_name,
                p.name AS product_name,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor,
                SUM(ABS(im.qty)) AS total_qty
            FROM inventory_movements im
            INNER JOIN product_variants pv ON pv.id = im.variant_id
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN variant_attributes a ON a.variant_id = pv.id
            WHERE im.reason IN ('loss', 'theft')
              AND im.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY pv.id, pv.name, p.name
            ORDER BY total_qty DESC, p.name ASC, pv.name ASC
            LIMIT 5
        ");
        $topStmt->execute([$days]);
        $topVariants = $topStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_loss_qty' => (int)($summary['total_loss_qty'] ?? 0),
            'total_theft_qty' => (int)($summary['total_theft_qty'] ?? 0),
            'estimated_amount' => (float)($summary['estimated_amount'] ?? 0),
            'total_events' => (int)($summary['total_events'] ?? 0),
            'top_variants' => $topVariants
        ];
    }

    public static function getRecentInventoryIssues(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $db = self::getDB();

        $stmt = $db->query("
            SELECT
                im.id,
                im.variant_id,
                im.qty,
                im.reason,
                im.meta,
                im.created_at,
                pv.name AS variant_name,
                pv.price,
                p.name AS product_name,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM inventory_movements im
            INNER JOIN product_variants pv ON pv.id = im.variant_id
            INNER JOIN products p ON p.id = pv.product_id
            LEFT JOIN variant_attributes a ON a.variant_id = pv.id
            WHERE im.reason IN ('loss', 'theft')
            GROUP BY
                im.id, im.variant_id, im.qty, im.reason, im.meta, im.created_at,
                pv.name, pv.price, p.name
            ORDER BY im.created_at DESC, im.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function syncVariantFlavor(int $variantId, string $flavor): void
    {
        $db = self::getDB();

        $existingStmt = $db->prepare("
            SELECT id
            FROM variant_attributes
            WHERE variant_id = ?
              AND attr_name = 'flavor'
            LIMIT 1
        ");
        $existingStmt->execute([$variantId]);
        $existingId = $existingStmt->fetchColumn();

        if ($flavor === '') {
            if ($existingId) {
                $deleteStmt = $db->prepare("DELETE FROM variant_attributes WHERE id = ?");
                $deleteStmt->execute([$existingId]);
            }
            return;
        }

        if ($existingId) {
            $updateStmt = $db->prepare("
                UPDATE variant_attributes
                SET attr_value = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$flavor, $existingId]);
            return;
        }

        $insertStmt = $db->prepare("
            INSERT INTO variant_attributes (variant_id, attr_name, attr_value)
            VALUES (?, 'flavor', ?)
        ");
        $insertStmt->execute([$variantId, $flavor]);
    }


    private static function deleteUploadedImageIfUnused(?string $imagePath): void
    {
        $imagePath = trim((string)$imagePath);

        if ($imagePath === '' || !str_starts_with($imagePath, 'products/')) {
            return;
        }

        $db = self::getDB();

        $productStmt = $db->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE image = ?
        ");
        $productStmt->execute([$imagePath]);
        $productUsageCount = (int)$productStmt->fetchColumn();

        $variantStmt = $db->prepare("
            SELECT COUNT(*)
            FROM product_variants
            WHERE image = ?
        ");
        $variantStmt->execute([$imagePath]);
        $variantUsageCount = (int)$variantStmt->fetchColumn();

        if (($productUsageCount + $variantUsageCount) > 0) {
            return;
        }

        $publicImgDir = realpath(__DIR__ . '/../public/img');
        if ($publicImgDir === false) {
            return;
        }

        $absolutePath = realpath($publicImgDir . '/' . ltrim($imagePath, '/'));
        if ($absolutePath === false) {
            return;
        }

        $normalizedPublicDir = str_replace('\\', '/', $publicImgDir);
        $normalizedAbsolutePath = str_replace('\\', '/', $absolutePath);

        if (!str_starts_with($normalizedAbsolutePath, $normalizedPublicDir . '/')) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private static function resolveVariantSku(
        PDO $db,
        int $productId,
        string $name,
        string $flavor,
        string $requestedSku = '',
        ?int $excludeVariantId = null
    ): string {
        $requestedSku = self::normalizeSku($requestedSku);

        if ($requestedSku !== '') {
            $stmt = $db->prepare("
                SELECT id
                FROM product_variants
                WHERE sku = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmt->execute([$requestedSku, $excludeVariantId ?: 0]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Ce SKU est déjà utilisé par une autre variante.');
            }

            return $requestedSku;
        }

        $label = trim($name . ' ' . $flavor);
        $slug = self::normalizeSku($label);
        $base = 'CKS-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT);

        if ($slug !== '') {
            $base .= '-' . substr($slug, 0, 36);
        }

        $candidate = $base;
        $suffix = 1;
        $stmt = $db->prepare("
            SELECT id
            FROM product_variants
            WHERE sku = ?
              AND id <> ?
            LIMIT 1
        ");

        while (true) {
            $stmt->execute([$candidate, $excludeVariantId ?: 0]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }

            $suffix++;
            $candidate = substr($base, 0, 58) . '-' . $suffix;
        }
    }

    public static function restoreProduct(int $productId, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE products
                SET archived_at = NULL
                WHERE id = ?
                  AND archived_at IS NOT NULL
            ");
            $stmt->execute([$productId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Produit introuvable ou déjà restauré.');
            }

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, 'product_restored', ?)
            ");
            $logStmt->execute([$adminId, 'Produit #' . $productId . ' restauré']);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function restoreVariant(int $variantId, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE product_variants pv
                INNER JOIN products p ON p.id = pv.product_id
                SET pv.archived_at = NULL
                WHERE pv.id = ?
                  AND pv.archived_at IS NOT NULL
                  AND p.archived_at IS NULL
            ");
            $stmt->execute([$variantId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Variante introuvable ou produit encore archivé.');
            }

            $variantStmt = $db->prepare("SELECT product_id FROM product_variants WHERE id = ?");
            $variantStmt->execute([$variantId]);
            $productId = (int)$variantStmt->fetchColumn();
            self::normalizeVariantSortOrders($productId);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, 'variant_restored', ?)
            ");
            $logStmt->execute([$adminId, 'Variante #' . $variantId . ' restaurée']);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function normalizeSku(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?? '';
        return substr(trim($value, '-'), 0, 64);
    }

    private static function normalizeVariantSortOrders(int $productId, ?int $focusVariantId = null, ?int $requestedSortOrder = null): void
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id, sort_order, created_at
            FROM product_variants
            WHERE product_id = ?
              AND archived_at IS NULL
            ORDER BY
                CASE WHEN id = ? THEN 0 ELSE 1 END,
                sort_order ASC,
                created_at ASC,
                id ASC
        ");
        $stmt->execute([$productId, $focusVariantId ?: 0]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($variants)) {
            return;
        }

        $orderedIds = array_map(static fn(array $row): int => (int)$row['id'], $variants);

        if ($focusVariantId !== null && in_array($focusVariantId, $orderedIds, true)) {
            $orderedIds = array_values(array_filter(
                $orderedIds,
                static fn(int $id): bool => $id !== $focusVariantId
            ));

            $targetIndex = max(0, min((int)$requestedSortOrder, count($orderedIds)));
            array_splice($orderedIds, $targetIndex, 0, [$focusVariantId]);
        }

        $updateStmt = $db->prepare("
            UPDATE product_variants
            SET sort_order = ?
            WHERE id = ?
        ");

        foreach ($orderedIds as $index => $variantId) {
            $updateStmt->execute([$index, $variantId]);
        }
    }

    public static function getAdminCatalogPaginated(
        ?string $q = null,
        int $page = 1,
        int $perPage = 12,
        string $archiveState = 'active'
    ): array
    {
        $allProducts = self::getAdminCatalog($q, $archiveState);

        $total = count($allProducts);
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $offset = ($page - 1) * $perPage;
        $products = array_slice($allProducts, $offset, $perPage);

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ];
    }
}
