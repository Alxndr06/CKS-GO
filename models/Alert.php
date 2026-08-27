<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Payment.php';

class Alert extends Model
{
    public static function getAllowedTypes(): array
    {
        return [
            'missing_product',
            'stock_mismatch',
            'wrong_variant',
            'damaged_product',
            'manual_check_required',
        ];
    }

    public static function getAllowedPriorities(): array
    {
        return ['low', 'medium', 'high'];
    }

    public static function getAllowedStatuses(): array
    {
        return ['open', 'in_progress', 'resolved', 'dismissed'];
    }

    public static function getAllowedSourceContexts(): array
    {
        return ['shop_product', 'cart', 'order_success', 'user_order', 'admin_manual'];
    }

    private static function normalizeEnum(?string $value, array $allowed, string $default): string
    {
        $value = trim((string)$value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function sanitizeText(?string $value, int $maxLength = 0): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    private static function resolveProductId(?int $productId, ?int $variantId): ?int
    {
        $productId = (int)$productId;
        $variantId = (int)$variantId;

        if ($productId > 0) {
            return $productId;
        }

        if ($variantId <= 0) {
            return null;
        }

        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT product_id\n            FROM product_variants\n            WHERE id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$variantId]);

        $resolved = $stmt->fetchColumn();

        return $resolved !== false ? (int)$resolved : null;
    }

    private static function resolveOrderItemContext(
        int $orderItemId,
        int $orderId,
        int $reportedByUserId
    ): ?array {
        if ($orderItemId <= 0) {
            return null;
        }

        $db = self::getDB();
        $stmt = $db->prepare("
            SELECT
                oi.id AS order_item_id,
                oi.order_id,
                oi.product_id,
                oi.variant_id,
                oi.quantity,
                oi.unit_price,
                o.user_id
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.id = ?
              AND (? = 0 OR oi.order_id = ?)
              AND (? = 0 OR o.user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([
            $orderItemId,
            $orderId,
            $orderId,
            $reportedByUserId,
            $reportedByUserId,
        ]);

        $context = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$context) {
            throw new RuntimeException("La ligne produit ne correspond pas à la commande du signalant.");
        }

        return $context;
    }

    private static function normalizeOrderItemIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $candidate) {
            $id = (int)$candidate;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function resolveOrderItemContexts(
        array $orderItemIds,
        int $orderId,
        int $reportedByUserId
    ): array {
        $orderItemIds = self::normalizeOrderItemIds($orderItemIds);
        if ($orderItemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
        $params = $orderItemIds;
        $params[] = $orderId;
        $params[] = $orderId;
        $params[] = $reportedByUserId;
        $params[] = $reportedByUserId;

        $stmt = self::getDB()->prepare("
            SELECT
                oi.id AS order_item_id,
                oi.order_id,
                oi.product_id,
                oi.variant_id,
                oi.quantity,
                oi.unit_price,
                o.user_id
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.id IN ($placeholders)
              AND (? = 0 OR oi.order_id = ?)
              AND (? = 0 OR o.user_id = ?)
            ORDER BY oi.id ASC
        ");
        $stmt->execute($params);
        $contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($contexts) !== count($orderItemIds)) {
            throw new RuntimeException("Un ou plusieurs produits ne correspondent pas à la commande du signalant.");
        }

        $resolvedOrderIds = array_unique(array_map(
            static fn(array $context): int => (int)$context['order_id'],
            $contexts
        ));
        if (count($resolvedOrderIds) !== 1) {
            throw new RuntimeException("Tous les produits signalés doivent appartenir à la même commande.");
        }

        return $contexts;
    }

    private static function getAllOrderItemContexts(int $orderId, int $reportedByUserId): array
    {
        if ($orderId <= 0 || $reportedByUserId <= 0) {
            return [];
        }

        $stmt = self::getDB()->prepare("
            SELECT
                oi.id AS order_item_id,
                oi.order_id,
                oi.product_id,
                oi.variant_id,
                oi.quantity,
                oi.unit_price,
                o.user_id
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.order_id = ?
              AND o.user_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId, $reportedByUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function syncAlertItems(PDO $db, int $alertId, array $contexts): void
    {
        if ($alertId <= 0 || $contexts === []) {
            return;
        }

        $insert = $db->prepare('INSERT IGNORE INTO alert_items (alert_id, order_item_id) VALUES (?, ?)');
        foreach ($contexts as $context) {
            $orderItemId = (int)($context['order_item_id'] ?? 0);
            if ($orderItemId > 0) {
                $insert->execute([$alertId, $orderItemId]);
            }
        }

        self::refreshAlertItemProjection($db, $alertId);
    }

    private static function refreshAlertItemProjection(PDO $db, int $alertId): void
    {
        $countStmt = $db->prepare('SELECT COUNT(*), MIN(order_item_id) FROM alert_items WHERE alert_id = ?');
        $countStmt->execute([$alertId]);
        $selection = $countStmt->fetch(PDO::FETCH_NUM) ?: [0, null];
        $count = (int)$selection[0];

        if ($count <= 0) {
            return;
        }

        if ($count > 1) {
            $update = $db->prepare('UPDATE alerts SET order_item_id = NULL, product_id = NULL, variant_id = NULL WHERE id = ?');
            $update->execute([$alertId]);
            return;
        }

        $itemStmt = $db->prepare('SELECT id, product_id, variant_id FROM order_items WHERE id = ? LIMIT 1');
        $itemStmt->execute([(int)$selection[1]]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            return;
        }

        $update = $db->prepare('UPDATE alerts SET order_item_id = ?, product_id = ?, variant_id = ? WHERE id = ?');
        $update->execute([
            (int)$item['id'],
            (int)$item['product_id'],
            $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
            $alertId,
        ]);
    }

    private static function assertOrderBelongsToReporter(int $orderId, int $reportedByUserId): void
    {
        if ($orderId <= 0 || $reportedByUserId <= 0) {
            return;
        }

        $stmt = self::getDB()->prepare('SELECT user_id FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $ownerId = (int)$stmt->fetchColumn();

        if ($ownerId <= 0 || $ownerId !== $reportedByUserId) {
            throw new RuntimeException("La commande ne correspond pas au signalant.");
        }
    }

    private static function buildDefaultTitle(string $type): string
    {
        return match ($type) {
            'missing_product' => 'Produit signalé absent',
            'stock_mismatch' => 'Incohérence de stock signalée',
            'wrong_variant' => 'Mauvaise variante signalée',
            'damaged_product' => 'Produit abîmé signalé',
            default => 'Signalement boutique',
        };
    }

    private static function addEvent(
        int $alertId,
        string $eventType,
        ?int $userId = null,
        ?int $adminId = null,
        ?string $message = null,
        ?array $meta = null
    ): void {
        $db = self::getDB();

        $stmt = $db->prepare("\n            INSERT INTO alert_events (\n                alert_id,\n                event_type,\n                user_id,\n                admin_id,\n                message,\n                meta_json\n            )\n            VALUES (?, ?, ?, ?, ?, ?)\n        ");

        $metaJson = null;
        if (!empty($meta)) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stmt->execute([
            $alertId,
            self::sanitizeText($eventType, 50),
            $userId > 0 ? $userId : null,
            $adminId > 0 ? $adminId : null,
            $message !== null ? trim($message) : null,
            $metaJson,
        ]);
    }

    private static function findActiveDuplicate(
        string $type,
        ?int $productId,
        ?int $variantId,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?int $reportedByUserId = null
    ): ?array {
        $db = self::getDB();
        $variantId = (int)$variantId;
        $productId = (int)$productId;
        $orderId = (int)$orderId;
        $orderItemId = (int)$orderItemId;
        $reportedByUserId = (int)$reportedByUserId;

        if ($orderId > 0 && $reportedByUserId > 0) {
            $stmt = $db->prepare("
                SELECT *
                FROM alerts
                WHERE type = ?
                  AND order_id = ?
                  AND reported_by_user_id = ?
                  AND status IN ('open', 'in_progress')
                ORDER BY
                    CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END,
                    id DESC
                LIMIT 1
            ");
            $stmt->execute([$type, $orderId, $reportedByUserId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        if ($orderItemId > 0) {
            $stmt = $db->prepare("
                SELECT *
                FROM alerts
                WHERE type = ?
                  AND order_item_id = ?
                  AND reported_by_user_id = ?
                  AND status IN ('open', 'in_progress')
                ORDER BY
                    CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END,
                    id DESC
                LIMIT 1
            ");
            $stmt->execute([$type, $orderItemId, $reportedByUserId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        if ($variantId > 0) {
            $stmt = $db->prepare("\n                SELECT *\n                FROM alerts\n                WHERE type = ?\n                  AND variant_id = ?\n                  AND status IN ('open', 'in_progress')\n                ORDER BY\n                    CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END,\n                    id DESC\n                LIMIT 1\n            ");
            $stmt->execute([$type, $variantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        if ($productId > 0) {
            $stmt = $db->prepare("\n                SELECT *\n                FROM alerts\n                WHERE type = ?\n                  AND product_id = ?\n                  AND variant_id IS NULL\n                  AND status IN ('open', 'in_progress')\n                ORDER BY\n                    CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END,\n                    id DESC\n                LIMIT 1\n            ");
            $stmt->execute([$type, $productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        if ($orderId > 0) {
            $stmt = $db->prepare("\n                SELECT *\n                FROM alerts\n                WHERE type = ?\n                  AND order_id = ?\n                  AND product_id IS NULL\n                  AND variant_id IS NULL\n                  AND status IN ('open', 'in_progress')\n                ORDER BY\n                    CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END,\n                    id DESC\n                LIMIT 1\n            " );
            $stmt->execute([$type, $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }

        return null;
    }

    public static function countActive(): int
    {
        $db = self::getDB();

        $stmt = $db->query("\n            SELECT COUNT(*)\n            FROM alerts\n            WHERE status IN ('open', 'in_progress')\n        ");

        return (int)$stmt->fetchColumn();
    }

    public static function getDashboardStats(): array
    {
        $db = self::getDB();

        $stmt = $db->query("\n            SELECT\n                COUNT(*) AS total_alerts,\n                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_alerts,\n                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_alerts,\n                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_alerts,\n                SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) AS dismissed_alerts,\n                SUM(CASE WHEN priority = 'high' AND status IN ('open', 'in_progress') THEN 1 ELSE 0 END) AS high_priority_active_alerts\n            FROM alerts\n        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_alerts' => (int)($row['total_alerts'] ?? 0),
            'open_alerts' => (int)($row['open_alerts'] ?? 0),
            'in_progress_alerts' => (int)($row['in_progress_alerts'] ?? 0),
            'resolved_alerts' => (int)($row['resolved_alerts'] ?? 0),
            'dismissed_alerts' => (int)($row['dismissed_alerts'] ?? 0),
            'high_priority_active_alerts' => (int)($row['high_priority_active_alerts'] ?? 0),
            'unassigned_active_alerts' => (int)$db->query("
                SELECT COUNT(*) FROM alerts
                WHERE assigned_admin_id IS NULL AND status IN ('open', 'in_progress')
            ")->fetchColumn(),
            'stale_active_alerts' => (int)$db->query("
                SELECT COUNT(*) FROM alerts
                WHERE status IN ('open', 'in_progress')
                  AND last_reported_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
            ")->fetchColumn(),
        ];
    }

    public static function createUserReport(array $data): int
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $type = self::normalizeEnum(
                $data['type'] ?? null,
                self::getAllowedTypes(),
                'manual_check_required'
            );

            $priority = self::normalizeEnum(
                $data['priority'] ?? null,
                self::getAllowedPriorities(),
                'medium'
            );

            $sourceContext = self::normalizeEnum(
                $data['source_context'] ?? null,
                self::getAllowedSourceContexts(),
                'shop_product'
            );

            $reportedByUserId = isset($data['reported_by_user_id']) ? (int)$data['reported_by_user_id'] : 0;
            $productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
            $variantId = isset($data['variant_id']) ? (int)$data['variant_id'] : 0;
            $orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
            $orderItemId = isset($data['order_item_id']) ? (int)$data['order_item_id'] : 0;
            $orderItemIds = self::normalizeOrderItemIds($data['order_item_ids'] ?? []);
            if ($orderItemId > 0) {
                $orderItemIds[] = $orderItemId;
                $orderItemIds = self::normalizeOrderItemIds($orderItemIds);
            }
            $allProducts = filter_var($data['all_products'] ?? false, FILTER_VALIDATE_BOOL);

            self::assertOrderBelongsToReporter($orderId, $reportedByUserId);

            $orderItemContexts = [];
            if ($allProducts && $orderId > 0) {
                $orderItemContexts = self::getAllOrderItemContexts($orderId, $reportedByUserId);
            } elseif ($orderItemIds !== []) {
                $orderItemContexts = self::resolveOrderItemContexts(
                    $orderItemIds,
                    $orderId,
                    $reportedByUserId
                );
            }

            if ($orderItemContexts !== []) {
                $orderId = (int)$orderItemContexts[0]['order_id'];
                if (count($orderItemContexts) === 1) {
                    $orderItemId = (int)$orderItemContexts[0]['order_item_id'];
                    $productId = (int)$orderItemContexts[0]['product_id'];
                    $variantId = (int)($orderItemContexts[0]['variant_id'] ?? 0);
                } else {
                    $orderItemId = 0;
                    $productId = 0;
                    $variantId = 0;
                }
            } elseif ($orderId > 0 && in_array($sourceContext, ['order_success', 'user_order'], true)) {
                throw new RuntimeException("Sélectionne au moins un produit concerné par le signalement.");
            }

            $resolvedProductId = self::resolveProductId($productId, $variantId);

            if ($resolvedProductId === null && $variantId <= 0 && $orderId <= 0) {
                throw new RuntimeException("Produit, variante ou commande manquant pour le signalement.");
            }

            $defaultTitle = ($orderId > 0 && $resolvedProductId === null && $variantId <= 0)
                ? 'Signalement sur commande'
                : self::buildDefaultTitle($type);

            $title = self::sanitizeText(
                $data['title'] ?? $defaultTitle,
                160
            );

            if ($title === '') {
                $title = self::buildDefaultTitle($type);
            }

            $message = trim((string)($data['message'] ?? ''));
            if ($message === '') {
                $message = null;
            }

            $duplicate = self::findActiveDuplicate(
                $type,
                $resolvedProductId,
                $variantId > 0 ? $variantId : null,
                $orderId > 0 ? $orderId : null,
                $orderItemId > 0 ? $orderItemId : null,
                $reportedByUserId > 0 ? $reportedByUserId : null
            );

            if ($duplicate) {
                self::syncAlertItems($db, (int)$duplicate['id'], $orderItemContexts);

                $update = $db->prepare("\n                    UPDATE alerts\n                    SET occurrence_count = occurrence_count + 1,\n                        last_reported_at = NOW()\n                    WHERE id = ?\n                ");
                $update->execute([(int)$duplicate['id']]);

                self::addEvent(
                    (int)$duplicate['id'],
                    'duplicate_report',
                    $reportedByUserId > 0 ? $reportedByUserId : null,
                    null,
                    $message,
                    [
                        'source_context' => $sourceContext,
                        'product_id' => $resolvedProductId,
                        'variant_id' => $variantId > 0 ? $variantId : null,
                        'order_id' => $orderId > 0 ? $orderId : null,
                        'order_item_id' => $orderItemId > 0 ? $orderItemId : null,
                        'order_item_ids' => array_map(
                            static fn(array $context): int => (int)$context['order_item_id'],
                            $orderItemContexts
                        ),
                    ]
                );

                $db->commit();
                return (int)$duplicate['id'];
            }

            $insert = $db->prepare("\n                INSERT INTO alerts (\n                    type,\n                    priority,\n                    status,\n                    source_context,\n                    product_id,\n                    variant_id,\n                    order_id,\n                    order_item_id,\n                    reported_by_user_id,\n                    assigned_admin_id,\n                    title,\n                    message,\n                    occurrence_count,\n                    resolution_code,\n                    resolution_note,\n                    last_reported_at,\n                    resolved_at\n                )\n                VALUES (?, ?, 'open', ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, NULL, NULL, NOW(), NULL)\n            ");

            $insert->execute([
                $type,
                $priority,
                $sourceContext,
                $resolvedProductId,
                $variantId > 0 ? $variantId : null,
                $orderId > 0 ? $orderId : null,
                $orderItemId > 0 ? $orderItemId : null,
                $reportedByUserId > 0 ? $reportedByUserId : null,
                $title,
                $message,
            ]);

            $alertId = (int)$db->lastInsertId();
            self::syncAlertItems($db, $alertId, $orderItemContexts);

            self::addEvent(
                $alertId,
                'report_created',
                $reportedByUserId > 0 ? $reportedByUserId : null,
                null,
                $message,
                [
                    'type' => $type,
                    'priority' => $priority,
                    'source_context' => $sourceContext,
                    'product_id' => $resolvedProductId,
                    'variant_id' => $variantId > 0 ? $variantId : null,
                    'order_id' => $orderId > 0 ? $orderId : null,
                    'order_item_id' => $orderItemId > 0 ? $orderItemId : null,
                    'order_item_ids' => array_map(
                        static fn(array $context): int => (int)$context['order_item_id'],
                        $orderItemContexts
                    ),
                ]
            );

            $db->commit();
            return $alertId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public static function countAll(
        ?string $status = null,
        ?string $priority = null,
        ?string $type = null,
        ?string $q = null
    ): int {
        $db = self::getDB();

        $sql = "\n            SELECT COUNT(*)\n            FROM alerts a\n            LEFT JOIN products p ON p.id = a.product_id\n            LEFT JOIN product_variants pv ON pv.id = a.variant_id\n            LEFT JOIN users reporter ON reporter.id = a.reported_by_user_id\n            LEFT JOIN users admin_user ON admin_user.id = a.assigned_admin_id\n            WHERE 1\n        ";

        $params = [];

        if ($status !== null && $status !== '') {
            $status = self::normalizeEnum($status, self::getAllowedStatuses(), '');
            if ($status !== '') {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
        }

        if ($priority !== null && $priority !== '') {
            $priority = self::normalizeEnum($priority, self::getAllowedPriorities(), '');
            if ($priority !== '') {
                $sql .= " AND a.priority = ?";
                $params[] = $priority;
            }
        }

        if ($type !== null && $type !== '') {
            $type = self::normalizeEnum($type, self::getAllowedTypes(), '');
            if ($type !== '') {
                $sql .= " AND a.type = ?";
                $params[] = $type;
            }
        }

        if ($q !== null && trim($q) !== '') {
            $like = '%' . trim($q) . '%';
            $sql .= "\n                AND (\n                    a.title LIKE ?\n                    OR a.message LIKE ?\n                    OR p.name LIKE ?\n                    OR pv.name LIKE ?\n                    OR reporter.username LIKE ?\n                    OR reporter.firstname LIKE ?\n                    OR reporter.lastname LIKE ?\n                    OR admin_user.username LIKE ?\n                    OR admin_user.firstname LIKE ?\n                    OR admin_user.lastname LIKE ?\n                )\n            ";
            array_push(
                $params,
                $like, $like, $like, $like, $like, $like, $like, $like, $like, $like
            );
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function getAll(
        ?string $status = null,
        ?string $priority = null,
        ?string $type = null,
        ?string $q = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $db = self::getDB();

        $sql = "\n            SELECT\n                a.*,\n                p.name AS product_name,\n                pv.name AS variant_name,\n                reporter.username AS reporter_username,\n                reporter.firstname AS reporter_firstname,\n                reporter.lastname AS reporter_lastname,\n                admin_user.username AS assigned_admin_username,\n                admin_user.firstname AS assigned_admin_firstname,\n                admin_user.lastname AS assigned_admin_lastname\n            FROM alerts a\n            LEFT JOIN products p ON p.id = a.product_id\n            LEFT JOIN product_variants pv ON pv.id = a.variant_id\n            LEFT JOIN users reporter ON reporter.id = a.reported_by_user_id\n            LEFT JOIN users admin_user ON admin_user.id = a.assigned_admin_id\n            WHERE 1\n        ";

        $params = [];

        if ($status !== null && $status !== '') {
            $status = self::normalizeEnum($status, self::getAllowedStatuses(), '');
            if ($status !== '') {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
        }

        if ($priority !== null && $priority !== '') {
            $priority = self::normalizeEnum($priority, self::getAllowedPriorities(), '');
            if ($priority !== '') {
                $sql .= " AND a.priority = ?";
                $params[] = $priority;
            }
        }

        if ($type !== null && $type !== '') {
            $type = self::normalizeEnum($type, self::getAllowedTypes(), '');
            if ($type !== '') {
                $sql .= " AND a.type = ?";
                $params[] = $type;
            }
        }

        if ($q !== null && trim($q) !== '') {
            $like = '%' . trim($q) . '%';
            $sql .= "\n                AND (\n                    a.title LIKE ?\n                    OR a.message LIKE ?\n                    OR p.name LIKE ?\n                    OR pv.name LIKE ?\n                    OR reporter.username LIKE ?\n                    OR reporter.firstname LIKE ?\n                    OR reporter.lastname LIKE ?\n                    OR admin_user.username LIKE ?\n                    OR admin_user.firstname LIKE ?\n                    OR admin_user.lastname LIKE ?\n                )\n            ";
            array_push(
                $params,
                $like, $like, $like, $like, $like, $like, $like, $like, $like, $like
            );
        }

        $sql .= "\n            ORDER BY\n                CASE a.status\n                    WHEN 'open' THEN 0\n                    WHEN 'in_progress' THEN 1\n                    WHEN 'resolved' THEN 2\n                    WHEN 'dismissed' THEN 3\n                    ELSE 4\n                END,\n                CASE a.priority\n                    WHEN 'high' THEN 0\n                    WHEN 'medium' THEN 1\n                    WHEN 'low' THEN 2\n                    ELSE 3\n                END,\n                a.last_reported_at DESC,\n                a.id DESC\n            LIMIT " . (int)$limit . "\n            OFFSET " . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countWorkQueue(
        ?string $status,
        ?string $priority,
        ?string $type,
        ?string $q,
        ?string $owner,
        ?int $adminId,
        ?string $age
    ): int {
        return count(self::getFilteredWorkQueue($status, $priority, $type, $q, $owner, $adminId, $age));
    }

    public static function getWorkQueue(
        ?string $status,
        ?string $priority,
        ?string $type,
        ?string $q,
        int $limit,
        int $offset,
        ?string $owner,
        ?int $adminId,
        ?string $age
    ): array {
        $alerts = self::getFilteredWorkQueue($status, $priority, $type, $q, $owner, $adminId, $age);
        return array_slice($alerts, max(0, $offset), max(1, $limit));
    }

    private static function getFilteredWorkQueue(
        ?string $status,
        ?string $priority,
        ?string $type,
        ?string $q,
        ?string $owner,
        ?int $adminId,
        ?string $age
    ): array {
        $alerts = self::getAll($status, $priority, $type, $q, 10000, 0);
        $staleThreshold = time() - 172800;

        return array_values(array_filter($alerts, static function (array $alert) use ($owner, $adminId, $age, $staleThreshold): bool {
            $assignedId = (int)($alert['assigned_admin_id'] ?? 0);
            if ($owner === 'mine' && ($adminId === null || $assignedId !== $adminId)) {
                return false;
            }
            if ($owner === 'unassigned' && $assignedId > 0) {
                return false;
            }

            if ($age !== null && $age !== '') {
                $reportedAt = strtotime((string)($alert['last_reported_at'] ?? '')) ?: 0;
                if ($age === 'stale' && $reportedAt >= $staleThreshold) {
                    return false;
                }
                if ($age === 'recent' && $reportedAt < $staleThreshold) {
                    return false;
                }
            }

            return true;
        }));
    }

    public static function findById(int $alertId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT\n                a.*,\n                p.name AS product_name,\n                pv.name AS variant_name,\n                reporter.username AS reporter_username,\n                reporter.firstname AS reporter_firstname,\n                reporter.lastname AS reporter_lastname,\n                admin_user.username AS assigned_admin_username,\n                admin_user.firstname AS assigned_admin_firstname,\n                admin_user.lastname AS assigned_admin_lastname\n            FROM alerts a\n            LEFT JOIN products p ON p.id = a.product_id\n            LEFT JOIN product_variants pv ON pv.id = a.variant_id\n            LEFT JOIN users reporter ON reporter.id = a.reported_by_user_id\n            LEFT JOIN users admin_user ON admin_user.id = a.assigned_admin_id\n            WHERE a.id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$alertId]);

        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        return $alert ?: null;
    }

    public static function getEventsByAlertId(int $alertId): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT\n                ae.*,\n                u.username AS user_username,\n                u.firstname AS user_firstname,\n                u.lastname AS user_lastname,\n                admin_user.username AS admin_username,\n                admin_user.firstname AS admin_firstname,\n                admin_user.lastname AS admin_lastname\n            FROM alert_events ae\n            LEFT JOIN users u ON u.id = ae.user_id\n            LEFT JOIN users admin_user ON admin_user.id = ae.admin_id\n            WHERE ae.alert_id = ?\n            ORDER BY ae.created_at ASC, ae.id ASC\n        ");
        $stmt->execute([$alertId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function assignToAdmin(int $alertId, int $adminId): bool
    {
        if ($alertId <= 0 || $adminId <= 0) {
            throw new RuntimeException("Alerte ou administrateur invalide.");
        }

        $alert = self::findById($alertId);

        if (!$alert) {
            throw new RuntimeException("Alerte introuvable.");
        }

        $db = self::getDB();

        $stmt = $db->prepare("\n            UPDATE alerts\n            SET assigned_admin_id = ?,\n                status = CASE\n                    WHEN status = 'open' THEN 'in_progress'\n                    ELSE status\n                END\n            WHERE id = ?\n        ");
        $ok = $stmt->execute([$adminId, $alertId]);

        self::addEvent(
            $alertId,
            'assigned_to_admin',
            null,
            $adminId,
            'Alerte prise en charge.',
            ['previous_status' => $alert['status']]
        );

        return $ok;
    }

    public static function updatePriority(int $alertId, string $priority, ?int $adminId = null): bool
    {
        $priority = self::normalizeEnum($priority, self::getAllowedPriorities(), '');

        if ($alertId <= 0 || $priority === '') {
            throw new RuntimeException("Priorité invalide.");
        }

        $alert = self::findById($alertId);

        if (!$alert) {
            throw new RuntimeException("Alerte introuvable.");
        }

        $db = self::getDB();

        $stmt = $db->prepare("\n            UPDATE alerts\n            SET priority = ?\n            WHERE id = ?\n        ");
        $ok = $stmt->execute([$priority, $alertId]);

        self::addEvent(
            $alertId,
            'priority_updated',
            null,
            $adminId && $adminId > 0 ? $adminId : null,
            'Priorité mise à jour.',
            [
                'from' => $alert['priority'],
                'to' => $priority,
            ]
        );

        return $ok;
    }

    public static function updateStatus(
        int $alertId,
        string $status,
        ?int $adminId = null,
        ?string $resolutionNote = null,
        ?string $resolutionCode = null
    ): bool {
        $status = self::normalizeEnum($status, self::getAllowedStatuses(), '');

        if ($alertId <= 0 || $status === '') {
            throw new RuntimeException("Statut invalide.");
        }

        $alert = self::findById($alertId);

        if (!$alert) {
            throw new RuntimeException("Alerte introuvable.");
        }

        $db = self::getDB();

        $resolutionNote = trim((string)$resolutionNote);
        $resolutionCode = self::sanitizeText($resolutionCode, 50);
        $isClosingStatus = in_array($status, ['resolved', 'dismissed'], true);

        $stmt = $db->prepare("\n            UPDATE alerts\n            SET\n                status = ?,\n                resolution_note = CASE\n                    WHEN ? = 1 THEN ?\n                    ELSE resolution_note\n                END,\n                resolution_code = CASE\n                    WHEN ? = 1 THEN ?\n                    ELSE resolution_code\n                END,\n                resolved_at = CASE\n                    WHEN ? = 1 THEN NOW()\n                    ELSE NULL\n                END\n            WHERE id = ?\n        ");

        $ok = $stmt->execute([
            $status,
            $isClosingStatus ? 1 : 0,
            $resolutionNote !== '' ? $resolutionNote : null,
            $isClosingStatus ? 1 : 0,
            $resolutionCode !== '' ? $resolutionCode : null,
            $isClosingStatus ? 1 : 0,
            $alertId,
        ]);

        self::addEvent(
            $alertId,
            'status_updated',
            null,
            $adminId && $adminId > 0 ? $adminId : null,
            $resolutionNote !== '' ? $resolutionNote : null,
            [
                'from' => $alert['status'],
                'to' => $status,
                'resolution_code' => $resolutionCode !== '' ? $resolutionCode : null,
            ]
        );

        return $ok;
    }

    public static function reopen(int $alertId, int $adminId, ?string $message = null): bool
    {
        if ($alertId <= 0 || $adminId <= 0) {
            throw new RuntimeException("Alerte ou administrateur invalide.");
        }

        $alert = self::findById($alertId);

        if (!$alert) {
            throw new RuntimeException("Alerte introuvable.");
        }

        $db = self::getDB();

        $stmt = $db->prepare("\n            UPDATE alerts\n            SET\n                status = 'open',\n                resolved_at = NULL,\n                resolution_code = NULL,\n                resolution_note = NULL\n            WHERE id = ?\n        ");
        $ok = $stmt->execute([$alertId]);

        self::addEvent(
            $alertId,
            'reopened',
            null,
            $adminId,
            trim((string)$message) !== '' ? trim((string)$message) : 'Alerte rouverte.',
            ['from' => $alert['status']]
        );

        return $ok;
    }

    public static function getRefundContext(int $alertId): array
    {
        $result = [
            'supported' => false,
            'can_refund' => false,
            'message' => "Ce signalement n'est pas lié à un achat remboursable.",
            'remaining_refundable_total' => 0.0,
            'items' => [],
            'refundable_items' => [],
            'refunds' => [],
            'refund' => null,
        ];

        $alert = self::findById($alertId);
        if (!$alert) {
            return $result;
        }

        $orderId = (int)($alert['order_id'] ?? 0);
        $reporterId = (int)($alert['reported_by_user_id'] ?? 0);
        if ($orderId <= 0 || $reporterId <= 0) {
            return $result;
        }

        $db = self::getDB();
        $refundStmt = $db->prepare("
            SELECT
                ar.*,
                r.created_at AS refund_created_at,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name, 'Produit') AS product_name,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name, 'Standard') AS variant_name,
                admin_user.username AS admin_username,
                admin_user.firstname AS admin_firstname,
                admin_user.lastname AS admin_lastname
            FROM alert_refunds ar
            INNER JOIN refunds r ON r.id = ar.refund_id
            INNER JOIN order_items oi ON oi.id = ar.order_item_id
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN users admin_user ON admin_user.id = ar.admin_id
            WHERE ar.alert_id = ?
            ORDER BY ar.created_at ASC, ar.id ASC
        ");
        $refundStmt->execute([$alertId]);
        $existingRefunds = $refundStmt->fetchAll(PDO::FETCH_ASSOC);
        $refundsByItem = [];
        foreach ($existingRefunds as $existingRefund) {
            $refundsByItem[(int)$existingRefund['order_item_id']] = $existingRefund;
        }

        $orderItemId = (int)($alert['order_item_id'] ?? 0);
        $productId = (int)($alert['product_id'] ?? 0);
        $variantId = (int)($alert['variant_id'] ?? 0);

        $selectedStmt = $db->prepare('SELECT order_item_id FROM alert_items WHERE alert_id = ? ORDER BY order_item_id ASC');
        $selectedStmt->execute([$alertId]);
        $selectedIds = array_map('intval', $selectedStmt->fetchAll(PDO::FETCH_COLUMN));

        $selectionSql = '';
        $selectionParams = [];
        if ($selectedIds !== []) {
            $selectionSql = ' AND oi.id IN (' . implode(',', array_fill(0, count($selectedIds), '?')) . ')';
            $selectionParams = $selectedIds;
        } else {
            $selectionSql = "
              AND (? = 0 OR oi.id = ?)
              AND (? = 0 OR oi.product_id = ?)
              AND (? = 0 OR oi.variant_id = ?)
            ";
            $selectionParams = [
                $orderItemId,
                $orderItemId,
                $productId,
                $productId,
                $variantId,
                $variantId,
            ];
        }

        $itemsStmt = $db->prepare("
            SELECT
                oi.id AS order_item_id,
                oi.product_id,
                oi.variant_id,
                oi.quantity,
                oi.unit_price,
                COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name, 'Produit') AS product_name,
                COALESCE(NULLIF(oi.variant_name_snapshot, ''), pv.name, 'Standard') AS variant_name,
                COALESCE(SUM(r.quantity_refunded), 0) AS refunded_quantity
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN refunds r ON r.order_item_id = oi.id
            WHERE oi.order_id = ?
              AND o.user_id = ?
              $selectionSql
            GROUP BY
                oi.id, oi.product_id, oi.variant_id, oi.quantity, oi.unit_price,
                oi.product_name_snapshot, oi.variant_name_snapshot, p.name, pv.name
            ORDER BY oi.id ASC
        ");
        $itemsStmt->execute(array_merge([$orderId, $reporterId], $selectionParams));
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $amountStmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)
                - COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.order_id = o.id), 0)
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.id = ? AND o.user_id = ?
            GROUP BY o.id
        ");
        $amountStmt->execute([$orderId, $reporterId]);
        $remainingRefundable = max(0.0, (float)$amountStmt->fetchColumn());

        foreach ($items as &$item) {
            $remainingQuantity = max(0, (int)$item['quantity'] - (int)$item['refunded_quantity']);
            $unitPrice = (float)$item['unit_price'];
            $coveredQuantity = $unitPrice > 0
                ? (int)floor(($remainingRefundable + 0.00001) / $unitPrice)
                : 0;
            $item['remaining_quantity'] = $remainingQuantity;
            $item['max_refundable_quantity'] = max(0, min($remainingQuantity, $coveredQuantity));
            $item['alert_refund'] = $refundsByItem[(int)$item['order_item_id']] ?? null;
            $item['already_refunded_for_alert'] = $item['alert_refund'] !== null;
        }
        unset($item);

        $refundableItems = array_values(array_filter(
            $items,
            static fn(array $item): bool => empty($item['already_refunded_for_alert'])
                && (int)($item['max_refundable_quantity'] ?? 0) > 0
        ));

        $result['supported'] = true;
        $result['remaining_refundable_total'] = round($remainingRefundable, 2);
        $result['items'] = $items;
        $result['refundable_items'] = $refundableItems;
        $result['refunds'] = $existingRefunds;
        $result['refund'] = $existingRefunds[0] ?? null;

        if (($alert['status'] ?? '') === 'dismissed') {
            $result['message'] = "Un signalement écarté doit être rouvert avant remboursement.";
            return $result;
        }

        if ($items === []) {
            $result['message'] = "Aucune ligne de commande ne correspond aux produits signalés.";
            return $result;
        }

        if ($refundableItems === [] && $existingRefunds !== []) {
            $result['message'] = 'Tous les produits traités dans ce signalement ont déjà été remboursés.';
            return $result;
        }

        if ($remainingRefundable <= 0 || $refundableItems === []) {
            $result['message'] = "Aucun montant payé et remboursable ne reste sur les produits sélectionnés.";
            return $result;
        }

        $result['can_refund'] = true;
        $result['message'] = '';

        return $result;
    }

    public static function refundReportedItems(
        int $alertId,
        array $quantitiesByOrderItem,
        int $adminId,
        string $stockAction = 'consumed'
    ): array {
        $quantities = [];
        foreach ($quantitiesByOrderItem as $orderItemId => $quantity) {
            $orderItemId = (int)$orderItemId;
            $quantity = (int)$quantity;
            if ($orderItemId > 0 && $quantity > 0) {
                $quantities[$orderItemId] = $quantity;
            }
        }

        if ($alertId <= 0 || $quantities === [] || $adminId <= 0) {
            throw new RuntimeException("Sélectionne au moins un produit et une quantité valide.");
        }

        $stockAction = $stockAction === 'restock' ? 'restock' : 'consumed';
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $alertStmt = $db->prepare('SELECT * FROM alerts WHERE id = ? LIMIT 1 FOR UPDATE');
            $alertStmt->execute([$alertId]);
            $alert = $alertStmt->fetch(PDO::FETCH_ASSOC);

            if (!$alert) {
                throw new RuntimeException("Signalement introuvable.");
            }
            if (($alert['status'] ?? '') === 'dismissed') {
                throw new RuntimeException("Rouvre le signalement avant de procéder au remboursement.");
            }

            $itemsStmt = $db->prepare("
                SELECT
                    oi.id,
                    oi.order_id,
                    oi.product_id,
                    oi.variant_id,
                    oi.quantity,
                    oi.unit_price,
                    COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name, 'Produit') AS product_name,
                    o.user_id
                FROM alert_items ai
                INNER JOIN order_items oi ON oi.id = ai.order_item_id
                INNER JOIN orders o ON o.id = oi.order_id
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE ai.alert_id = ?
                ORDER BY oi.id ASC
                FOR UPDATE
            ");
            $itemsStmt->execute([$alertId]);
            $selectedItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($selectedItems === []) {
                $legacyStmt = $db->prepare("
                    SELECT
                        oi.id,
                        oi.order_id,
                        oi.product_id,
                        oi.variant_id,
                        oi.quantity,
                        oi.unit_price,
                        COALESCE(NULLIF(oi.product_name_snapshot, ''), p.name, 'Produit') AS product_name,
                        o.user_id
                    FROM order_items oi
                    INNER JOIN orders o ON o.id = oi.order_id
                    LEFT JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = ?
                      AND o.user_id = ?
                      AND (? = 0 OR oi.id = ?)
                      AND (? = 0 OR oi.product_id = ?)
                      AND (? = 0 OR oi.variant_id = ?)
                    ORDER BY oi.id ASC
                    FOR UPDATE
                ");
                $legacyStmt->execute([
                    (int)($alert['order_id'] ?? 0),
                    (int)($alert['reported_by_user_id'] ?? 0),
                    (int)($alert['order_item_id'] ?? 0),
                    (int)($alert['order_item_id'] ?? 0),
                    (int)($alert['product_id'] ?? 0),
                    (int)($alert['product_id'] ?? 0),
                    (int)($alert['variant_id'] ?? 0),
                    (int)($alert['variant_id'] ?? 0),
                ]);
                $selectedItems = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $itemsById = [];
            foreach ($selectedItems as $item) {
                $itemsById[(int)$item['id']] = $item;
            }

            foreach (array_keys($quantities) as $orderItemId) {
                if (!isset($itemsById[$orderItemId])) {
                    throw new RuntimeException("Un produit choisi ne fait pas partie de ce signalement.");
                }
            }

            $existingStmt = $db->prepare('SELECT order_item_id FROM alert_refunds WHERE alert_id = ? FOR UPDATE');
            $existingStmt->execute([$alertId]);
            $alreadyRefundedIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
            foreach (array_keys($quantities) as $orderItemId) {
                if (in_array($orderItemId, $alreadyRefundedIds, true)) {
                    throw new RuntimeException("Ce produit a déjà été remboursé pour ce signalement.");
                }
            }

            $bridgeStmt = $db->prepare("
                INSERT INTO alert_refunds (
                    alert_id, refund_id, order_item_id, admin_id,
                    quantity_refunded, amount, stock_action
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $refunds = [];
            $totalAmount = 0.0;
            $totalQuantity = 0;
            foreach ($quantities as $orderItemId => $quantity) {
                $refund = Payment::refundOrderItemPartial(
                    $orderItemId,
                    $quantity,
                    $adminId,
                    $stockAction,
                    false
                );

                $bridgeStmt->execute([
                    $alertId,
                    (int)$refund['refund_id'],
                    $orderItemId,
                    $adminId,
                    (int)$refund['refunded_quantity'],
                    (float)$refund['refunded_amount'],
                    $stockAction,
                ]);

                $refund['product_name'] = (string)$itemsById[$orderItemId]['product_name'];
                $refunds[] = $refund;
                $totalAmount += (float)$refund['refunded_amount'];
                $totalQuantity += (int)$refund['refunded_quantity'];
            }

            $processedStmt = $db->prepare('SELECT COUNT(DISTINCT order_item_id) FROM alert_refunds WHERE alert_id = ?');
            $processedStmt->execute([$alertId]);
            $allSelectedItemsProcessed = (int)$processedStmt->fetchColumn() >= count($selectedItems);

            $resolutionNote = sprintf(
                '%s : %d produit(s), %d ligne(s), %.2f €.',
                $allSelectedItemsProcessed ? 'Signalement remboursé' : 'Remboursement partiel du signalement',
                $totalQuantity,
                count($refunds),
                $totalAmount
            );
            $updateStmt = $db->prepare("
                UPDATE alerts
                SET assigned_admin_id = COALESCE(assigned_admin_id, ?),
                    status = ?,
                    resolution_code = ?,
                    resolution_note = ?,
                    resolved_at = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $adminId,
                $allSelectedItemsProcessed ? 'resolved' : 'in_progress',
                $allSelectedItemsProcessed ? 'refunded' : null,
                $resolutionNote,
                $allSelectedItemsProcessed ? date('Y-m-d H:i:s') : null,
                $alertId,
            ]);

            self::addEvent(
                $alertId,
                'report_refunded',
                null,
                $adminId,
                $resolutionNote,
                [
                    'refund_ids' => array_map(static fn(array $refund): int => (int)$refund['refund_id'], $refunds),
                    'order_item_ids' => array_keys($quantities),
                    'quantity' => $totalQuantity,
                    'amount' => number_format($totalAmount, 2, '.', ''),
                    'stock_action' => $stockAction,
                    'alert_fully_processed' => $allSelectedItemsProcessed,
                ]
            );

            $db->commit();

            return [
                'alert_id' => $alertId,
                'order_id' => (int)($selectedItems[0]['order_id'] ?? 0),
                'reporter_user_id' => (int)($selectedItems[0]['user_id'] ?? 0),
                'refunded_amount' => round($totalAmount, 2),
                'refunded_quantity' => $totalQuantity,
                'refunded_line_count' => count($refunds),
                'stock_action' => $stockAction,
                'alert_fully_processed' => $allSelectedItemsProcessed,
                'refunds' => $refunds,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function refundReportedItem(
        int $alertId,
        int $orderItemId,
        int $quantity,
        int $adminId,
        string $stockAction = 'consumed'
    ): array {
        $result = self::refundReportedItems(
            $alertId,
            [$orderItemId => $quantity],
            $adminId,
            $stockAction
        );
        $refund = $result['refunds'][0] ?? [];

        return $refund + [
            'alert_id' => $alertId,
            'reporter_user_id' => (int)$result['reporter_user_id'],
            'stock_action' => $stockAction,
            'alert_fully_processed' => (bool)$result['alert_fully_processed'],
        ];
    }

    public static function addAdminNote(int $alertId, int $adminId, string $message, array $meta = []): bool
    {
        $message = trim($message);

        if ($alertId <= 0 || $adminId <= 0) {
            throw new RuntimeException("Alerte ou administrateur invalide.");
        }

        if ($message === '') {
            throw new RuntimeException("Le message ne peut pas être vide.");
        }

        $alert = self::findById($alertId);

        if (!$alert) {
            throw new RuntimeException("Alerte introuvable.");
        }

        self::addEvent(
            $alertId,
            'admin_note',
            null,
            $adminId,
            $message,
            $meta
        );

        return true;
    }
}
