<?php
require_once __DIR__ . '/../core/Model.php';
class Product extends Model
{
    public static function search(?string $categorySlug, ?string $q): array
    {
        $db = self::getDB();
        $sql = "SELECT p.id, p.name, p.description, p.image
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE 1";
        $params = [];

        if ($categorySlug) { $sql .= " AND c.slug = ?"; $params[] = $categorySlug; }
        if ($q) {
            $like = "%$q%";
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = $like; $params[] = $like;
        }
        $sql .= " ORDER BY p.name";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Variants (goûts) + flavor (depuis variant_attributes)
    public static function variants(int $productId): array
    {
        $db = self::getDB();
        $stmt = $db->prepare("
            SELECT v.id, v.product_id, v.name, v.price, v.stock_quantity, v.image, v.is_active,
                   MAX(CASE WHEN a.attr_name='flavor' THEN a.attr_value END) AS flavor
            FROM product_variants v
            LEFT JOIN variant_attributes a ON a.variant_id = v.id
            WHERE v.product_id = ?
            GROUP BY v.id
            ORDER BY v.name
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
