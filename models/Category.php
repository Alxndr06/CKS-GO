<?php
require_once __DIR__ . '/../core/Model.php';

class Category extends Model
{
    public static function allActive(): array
    {
        $db = self::getDB();
        $stmt = $db->query("
            SELECT id, name, slug, sort_order
            FROM categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAll(?string $q = null): array
    {
        $db = self::getDB();

        $sql = "
            SELECT id, name, slug, sort_order, is_active
            FROM categories
            WHERE 1
        ";
        $params = [];

        if ($q) {
            $like = '%' . trim($q) . '%';
            $sql .= " AND (name LIKE ? OR slug LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY sort_order ASC, name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById(int $id): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT id, name, slug, sort_order, is_active
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        return $category ?: null;
    }

    public static function create(array $data, int $adminId): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $name = trim((string)($data['name'] ?? ''));
            $slug = trim((string)($data['slug'] ?? ''));
            $sortOrder = (int)($data['sort_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

            if ($name === '') {
                throw new RuntimeException("Le nom de la catégorie est obligatoire.");
            }

            if ($slug === '') {
                $slug = self::slugify($name);
            }

            self::assertSlugAvailable($slug);

            $stmt = $db->prepare("
                INSERT INTO categories (name, slug, sort_order, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $slug,
                $sortOrder,
                $isActive
            ]);

            $categoryId = (int)$db->lastInsertId();

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'category_created',
                'Catégorie #' . $categoryId . ' créée (' . $name . ')'
            ]);

            $db->commit();
            return $categoryId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $categoryId, array $data, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $existing = self::findById($categoryId);

            if (!$existing) {
                throw new RuntimeException("Catégorie introuvable.");
            }

            $name = trim((string)($data['name'] ?? ''));
            $slug = trim((string)($data['slug'] ?? ''));
            $sortOrder = (int)($data['sort_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 0;

            if ($name === '') {
                throw new RuntimeException("Le nom de la catégorie est obligatoire.");
            }

            if ($slug === '') {
                $slug = self::slugify($name);
            }

            self::assertSlugAvailable($slug, $categoryId);

            $stmt = $db->prepare("
                UPDATE categories
                SET name = ?, slug = ?, sort_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $slug,
                $sortOrder,
                $isActive,
                $categoryId
            ]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'category_updated',
                'Catégorie #' . $categoryId . ' mise à jour (' . $name . ')'
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

    public static function toggleStatus(int $categoryId, int $adminId): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $category = self::findById($categoryId);

            if (!$category) {
                throw new RuntimeException("Catégorie introuvable.");
            }

            $newStatus = ((int)$category['is_active'] === 1) ? 0 : 1;

            $stmt = $db->prepare("
                UPDATE categories
                SET is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $categoryId]);

            $logStmt = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
            $logStmt->execute([
                $adminId,
                'category_status_toggled',
                'Catégorie #' . $categoryId . ' -> ' . ($newStatus === 1 ? 'active' : 'inactive')
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

    private static function assertSlugAvailable(string $slug, ?int $exceptId = null): void
    {
        $db = self::getDB();

        $sql = "SELECT id FROM categories WHERE slug = ?";
        $params = [$slug];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("Ce slug de catégorie existe déjà.");
        }
    }

    private static function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));

        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'œ' => 'oe',
            'æ' => 'ae'
        ];

        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'categorie';
    }
}