<?php
require_once __DIR__ . '/../core/Model.php';

class Product extends Model
{
    public static function search(?string $categorySlug, ?string $q): array
    {
        $db = self::getDB();

        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.image,
                MIN(CASE WHEN v.is_active = 1 THEN v.price END) AS min_price
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id
            WHERE p.is_active = 1
        ";
        $params = [];

        if ($categorySlug) {
            $sql .= " AND c.slug = ?";
            $params[] = $categorySlug;
        }

        if ($q) {
            $like = "%$q%";
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= "
            GROUP BY p.id, p.name, p.description, p.image
            ORDER BY p.name
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
                v.name,
                v.price,
                v.stock_quantity,
                v.image,
                v.is_active,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.product_id = ?
            GROUP BY v.id
            ORDER BY v.name
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
                v.name,
                v.price,
                v.stock_quantity,
                v.image,
                v.is_active,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.product_id IN ($placeholders)
            GROUP BY v.id
            ORDER BY v.product_id, v.name
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
                v.name,
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
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.is_active,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            INNER JOIN product_variants v ON v.product_id = p.id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE p.is_active = 1
              AND v.is_active = 1
            GROUP BY
                p.id, p.name, p.description, p.image,
                v.id, v.name, v.price, v.stock_quantity, v.is_active
            ORDER BY p.name ASC, v.name ASC
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
                'name' => $row['variant_name'],
                'price' => (float)$row['price'],
                'stock_quantity' => (int)$row['stock_quantity'],
                'is_active' => (int)$row['is_active'],
                'flavor' => $row['flavor']
            ];
        }

        return array_values($products);
    }

    public static function getAdminCatalog(?string $q = null): array
    {
        $db = self::getDB();

        $sql = "
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.description,
                p.image AS product_image,
                p.is_active AS product_is_active,
                c.name AS category_name,
                v.id AS variant_id,
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.is_active AS variant_is_active,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE 1
        ";

        $params = [];

        if ($q) {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    p.name LIKE ?
                    OR p.description LIKE ?
                    OR c.name LIKE ?
                    OR v.name LIKE ?
                    OR a.attr_value LIKE ?
                )
            ";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= "
            GROUP BY
                p.id, p.name, p.description, p.image, p.is_active,
                c.name,
                v.id, v.name, v.price, v.stock_quantity, v.is_active
            ORDER BY p.name ASC, v.name ASC
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
                    'category_name' => $row['category_name'] ?? null,
                    'variants' => [],
                    'total_stock' => 0,
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
                    'name' => $row['variant_name'],
                    'flavor' => $row['flavor'],
                    'display_name' => !empty($row['flavor']) ? $row['flavor'] : $row['variant_name'],
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'is_active' => (int)$row['variant_is_active']
                ];

                $products[$productId]['total_stock'] += $stock;
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
                c.name AS category_name,
                v.id AS variant_id,
                v.name AS variant_name,
                v.price,
                v.stock_quantity,
                v.is_active AS variant_is_active,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_variants v ON v.product_id = p.id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE p.id = ?
            GROUP BY
                p.id, p.name, p.description, p.image, p.is_active,
                c.name,
                v.id, v.name, v.price, v.stock_quantity, v.is_active
            ORDER BY v.name ASC
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
            'category_name' => $first['category_name'] ?? null,
            'variants' => [],
            'total_stock' => 0,
            'variant_count' => 0
        ];

        foreach ($rows as $row) {
            if (empty($row['variant_id'])) {
                continue;
            }

            $stock = (int)$row['stock_quantity'];

            $product['variants'][] = [
                'id' => (int)$row['variant_id'],
                'name' => $row['variant_name'],
                'flavor' => $row['flavor'],
                'display_name' => !empty($row['flavor']) ? $row['flavor'] : $row['variant_name'],
                'price' => (float)$row['price'],
                'stock_quantity' => $stock,
                'is_active' => (int)$row['variant_is_active']
            ];

            $product['total_stock'] += $stock;
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
                v.name,
                v.price,
                v.stock_quantity,
                v.is_active,
                p.name AS product_name,
                p.image AS product_image,
                p.description AS product_description,
                p.is_active AS product_is_active,
                c.name AS category_name,
                MAX(CASE WHEN a.attr_name = 'flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.id = ?
            GROUP BY
                v.id, v.product_id, v.name, v.price, v.stock_quantity, v.is_active,
                p.name, p.image, p.description, p.is_active,
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
            $variant = self::getVariantById($variantId);

            if (!$variant) {
                throw new RuntimeException("Variante introuvable.");
            }

            if ($newStock < 0) {
                throw new RuntimeException("Le stock ne peut pas être négatif.");
            }

            $currentStock = (int)$variant['stock_quantity'];
            $delta = $newStock - $currentStock;

            $updateStmt = $db->prepare("
                UPDATE product_variants
                SET stock_quantity = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newStock, $variantId]);

            $movementStmt = $db->prepare("
                INSERT INTO inventory_movements (variant_id, qty, reason, meta)
                VALUES (?, ?, 'adjustment', ?)
            ");
            $movementStmt->execute([
                $variantId,
                $delta,
                "admin_id={$adminId};from={$currentStock};to={$newStock}"
            ]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'variant_stock_updated',
                'Ajustement stock variante #' . $variantId . ' / de ' . $currentStock . ' à ' . $newStock
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

    public static function updateProduct(int $productId, array $data, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE products
                SET name = ?, description = ?, image = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($data['name']),
                trim($data['description']),
                trim($data['image']),
                (int)$data['is_active'],
                $productId
            ]);

            if ($stmt->rowCount() === 0) {
                $checkStmt = $db->prepare("SELECT id FROM products WHERE id = ?");
                $checkStmt->execute([$productId]);

                if (!$checkStmt->fetchColumn()) {
                    throw new RuntimeException("Produit introuvable.");
                }
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
            $stmt = $db->prepare("
                UPDATE product_variants
                SET name = ?, price = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($data['name']),
                (float)$data['price'],
                (int)$data['is_active'],
                $variantId
            ]);

            if ($stmt->rowCount() === 0) {
                $checkStmt = $db->prepare("SELECT id FROM product_variants WHERE id = ?");
                $checkStmt->execute([$variantId]);

                if (!$checkStmt->fetchColumn()) {
                    throw new RuntimeException("Variante introuvable.");
                }
            }

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'variant_updated',
                'Variante #' . $variantId . ' modifiée'
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

    public static function createProductWithVariant(array $productData, array $variantData, int $adminId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO products (name, description, category_id, image, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($productData['name']),
                trim($productData['description']),
                $productData['category_id'] ?: null,
                trim($productData['image']),
                (int)$productData['is_active']
            ]);

            $productId = (int)$db->lastInsertId();

            $stmt = $db->prepare("
                INSERT INTO product_variants (product_id, name, price, stock_quantity, is_active, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                trim($variantData['name']),
                (float)$variantData['price'],
                (int)$variantData['stock_quantity'],
                (int)$variantData['is_active'],
                trim($productData['image'])
            ]);

            $variantId = (int)$db->lastInsertId();

            if (!empty($variantData['flavor'])) {
                $stmt = $db->prepare("
                    INSERT INTO variant_attributes (variant_id, attr_name, attr_value)
                    VALUES (?, 'flavor', ?)
                ");
                $stmt->execute([
                    $variantId,
                    trim($variantData['flavor'])
                ]);
            }

            if ((int)$variantData['stock_quantity'] > 0) {
                $stmt = $db->prepare("
                    INSERT INTO inventory_movements (variant_id, qty, reason, meta)
                    VALUES (?, ?, 'manual', ?)
                ");
                $stmt->execute([
                    $variantId,
                    (int)$variantData['stock_quantity'],
                    'Initial stock / admin_id=' . $adminId
                ]);
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
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new RuntimeException("Produit introuvable.");
            }

            $stmt = $db->prepare("
                INSERT INTO product_variants (product_id, name, price, stock_quantity, is_active, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                trim($variantData['name']),
                (float)$variantData['price'],
                (int)$variantData['stock_quantity'],
                (int)$variantData['is_active'],
                trim((string)$product['image'])
            ]);

            $variantId = (int)$db->lastInsertId();

            if (!empty($variantData['flavor'])) {
                $stmt = $db->prepare("
                    INSERT INTO variant_attributes (variant_id, attr_name, attr_value)
                    VALUES (?, 'flavor', ?)
                ");
                $stmt->execute([
                    $variantId,
                    trim($variantData['flavor'])
                ]);
            }

            if ((int)$variantData['stock_quantity'] > 0) {
                $stmt = $db->prepare("
                    INSERT INTO inventory_movements (variant_id, qty, reason, meta)
                    VALUES (?, ?, 'manual', ?)
                ");
                $stmt->execute([
                    $variantId,
                    (int)$variantData['stock_quantity'],
                    'Initial stock variant / admin_id=' . $adminId
                ]);
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

            $db->commit();
            return $variantId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}