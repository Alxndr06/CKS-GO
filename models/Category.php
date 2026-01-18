<?php
require_once __DIR__ . '/../core/Model.php';

class Category extends Model
{
    public static function allActive(): array
    {
        $db = self::getDB();
        $stmt = $db->query("SELECT id, name, slug FROM categories WHERE is_active=1 ORDER BY sort_order, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}