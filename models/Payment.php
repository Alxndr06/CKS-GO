<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/UserBalance.php';
require_once __DIR__ . '/Inventory.php';

class Payment extends Model
{
    public static function countForUser(int $userId, ?string $q = null): int
    {
        $db = self::getDB();

        $sql = "
            SELECT COUNT(DISTINCT p.id)
            FROM payments p
            LEFT JOIN orders o ON o.id = p.order_id
            WHERE (p.payment_author_id = ? OR o.user_id = ?)
        ";

        $params = [$userId, $userId];

        if ($q !== null && $q !== '') {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    CAST(p.id AS CHAR) LIKE ?
                    OR CAST(COALESCE(p.order_id, 0) AS CHAR) LIKE ?
                    OR COALESCE(p.method, '') LIKE ?
                    OR COALESCE(p.provider, '') LIKE ?
                    OR COALESCE(p.provider_ref, '') LIKE ?
                    OR COALESCE(p.status, '') LIKE ?
                    OR COALESCE(p.currency, '') LIKE ?
                    OR DATE_FORMAT(p.payment_date, '%d/%m/%Y %H:%i') LIKE ?
                )
            ";
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public static function searchForUser(int $userId, ?string $q = null, int $limit = 10, int $offset = 0): array
    {
        $db = self::getDB();

        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $sql = "
            SELECT
                p.id,
                p.batch_id,
                p.order_id,
                p.payment_author_id,
                p.admin_id,
                p.amount_paid,
                p.payment_date,
                p.method,
                p.provider,
                p.provider_ref,
                p.status,
                p.currency,
                o.status AS order_status,
                admin.firstname AS admin_firstname,
                admin.lastname AS admin_lastname,
                admin.username AS admin_username
            FROM payments p
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN users admin ON admin.id = p.admin_id
            WHERE (p.payment_author_id = ? OR o.user_id = ?)
        ";

        $params = [$userId, $userId];

        if ($q !== null && $q !== '') {
            $like = '%' . $q . '%';
            $sql .= "
                AND (
                    CAST(p.id AS CHAR) LIKE ?
                    OR CAST(COALESCE(p.order_id, 0) AS CHAR) LIKE ?
                    OR COALESCE(p.method, '') LIKE ?
                    OR COALESCE(p.provider, '') LIKE ?
                    OR COALESCE(p.provider_ref, '') LIKE ?
                    OR COALESCE(p.status, '') LIKE ?
                    OR COALESCE(p.currency, '') LIKE ?
                    OR DATE_FORMAT(p.payment_date, '%d/%m/%Y %H:%i') LIKE ?
                )
            ";
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
        }

        $sql .= "
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPendingOrders(?int $userId = null): array
    {
        $db = self::getDB();

        $sql = "
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
                u.note,
                COALESCE(payment_summary.paid_amount, 0) AS paid_amount,
                (o.total_price - COALESCE(payment_summary.paid_amount, 0)) AS remaining_due,
                COALESCE(item_summary.item_lines, 0) AS item_lines
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN (
                SELECT order_id, COUNT(*) AS item_lines
                FROM order_items
                GROUP BY order_id
            ) AS item_summary ON item_summary.order_id = o.id
            LEFT JOIN (
                SELECT order_id, SUM(amount_paid) AS paid_amount
                FROM payments
                WHERE status = 'captured'
                GROUP BY order_id
            ) AS payment_summary ON payment_summary.order_id = o.id
            WHERE o.status IN ('pending_payment', 'paid', 'partially_refunded')
        ";

        $params = [];

        if ($userId !== null && $userId > 0) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }

        $sql .= "
            AND (o.total_price - COALESCE(payment_summary.paid_amount, 0)) > 0
            ORDER BY o.created_at ASC, o.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUsersForPaymentFilter(?string $search = null, bool $pendingUsersOnly = false): array
    {
        $db = self::getDB();

        $sql = "
            SELECT
                u.id,
                u.firstname,
                u.lastname,
                u.username,
                u.email,
                u.note,
                u.role,
                u.is_active,
                COALESCE(pending.pending_total, 0) AS pending_total,
                COALESCE(pending.pending_orders_count, 0) AS pending_orders_count
            FROM users u
            LEFT JOIN (
                SELECT
                    t.user_id,
                    SUM(t.remaining_due) AS pending_total,
                    COUNT(*) AS pending_orders_count
                FROM (
                    SELECT
                        o.user_id,
                        o.id,
                        (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) AS remaining_due
                    FROM orders o
                    LEFT JOIN payments p ON p.order_id = o.id
                    WHERE o.status IN ('pending_payment', 'paid', 'partially_refunded')
                    GROUP BY o.user_id, o.id, o.total_price
                    HAVING remaining_due > 0
                ) t
                GROUP BY t.user_id
            ) pending ON pending.user_id = u.id
            WHERE 1 = 1
        ";

        $params = [];

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $sql .= "
                AND (
                    u.username LIKE ?
                    OR u.firstname LIKE ?
                    OR u.lastname LIKE ?
                    OR u.email LIKE ?
                    OR u.unit LIKE ?
                )
            ";
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        if ($pendingUsersOnly) {
            $sql .= " AND (COALESCE(pending.pending_total, 0) > 0 OR ABS(u.note) > 0.009)";
        }

        $sql .= "
            ORDER BY
                CASE WHEN COALESCE(pending.pending_total, 0) > 0 OR ABS(u.note) > 0.009 THEN 0 ELSE 1 END,
                u.lastname ASC,
                u.firstname ASC,
                u.username ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentPayments(int $limit = 10, ?int $userId = null): array
    {
        $db = self::getDB();

        $limit = max(1, (int)$limit);

        $sql = "
            SELECT
                p.id,
                p.batch_id,
                p.order_id,
                p.payment_author_id,
                p.admin_id,
                p.amount_paid,
                p.payment_date,
                p.method,
                p.provider,
                p.provider_ref,
                p.status,
                p.currency,
                o.status AS order_status,
                payer.firstname AS payer_firstname,
                payer.lastname AS payer_lastname,
                payer.username AS payer_username,
                admin.firstname AS admin_firstname,
                admin.lastname AS admin_lastname,
                admin.username AS admin_username
            FROM payments p
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN users payer ON payer.id = p.payment_author_id
            LEFT JOIN users admin ON admin.id = p.admin_id
            WHERE 1 = 1
        ";

        $params = [];

        if ($userId !== null && $userId > 0) {
            $sql .= " AND (p.payment_author_id = ? OR o.user_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql .= "
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT {$limit}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCapturedDailyTotals(int $days = 7): array
    {
        $days = max(1, min($days, 31));
        $fromDate = (new DateTimeImmutable('today'))
            ->modify('-' . ($days - 1) . ' days')
            ->format('Y-m-d 00:00:00');

        $stmt = self::getDB()->prepare("
            SELECT
                DATE(payment_date) AS payment_day,
                COALESCE(SUM(amount_paid), 0) AS captured_total
            FROM payments
            WHERE status = 'captured'
              AND payment_date >= ?
            GROUP BY DATE(payment_date)
            ORDER BY payment_day ASC
        ");
        $stmt->execute([$fromDate]);

        return array_map(
            static fn(array $row): array => [
                'payment_day' => (string)($row['payment_day'] ?? ''),
                'captured_total' => (float)($row['captured_total'] ?? 0),
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public static function getPendingTotalForUser(int $userId): float
    {
        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT COALESCE(SUM(t.remaining_due), 0) AS pending_total\n            FROM (\n                SELECT\n                    o.id,\n                    (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) AS remaining_due\n                FROM orders o\n                LEFT JOIN payments p ON p.order_id = o.id\n                WHERE o.user_id = ?\n                  AND o.status IN ('pending_payment', 'paid', 'partially_refunded')\n                GROUP BY o.id, o.total_price\n                HAVING remaining_due > 0\n            ) AS t\n        ");
        $stmt->execute([$userId]);

        return (float)$stmt->fetchColumn();
    }

    public static function getAdminPaymentById(int $paymentId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT\n                p.*,\n                o.status AS order_status,\n                o.total_price AS order_total_price,\n                o.created_at AS order_created_at,\n                payer.firstname AS payer_firstname,\n                payer.lastname AS payer_lastname,\n                payer.username AS payer_username,\n                payer.email AS payer_email,\n                admin.firstname AS admin_firstname,\n                admin.lastname AS admin_lastname,\n                admin.username AS admin_username\n            FROM payments p\n            LEFT JOIN orders o ON o.id = p.order_id\n            LEFT JOIN users payer ON payer.id = p.payment_author_id\n            LEFT JOIN users admin ON admin.id = p.admin_id\n            WHERE p.id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$paymentId]);

        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $payment ?: null;
    }

    public static function captureOrderPayment(
        int $orderId,
        int $adminId,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): int {
        require_once __DIR__ . '/../services/PaymentService.php';

        $ownerStmt = self::getDB()->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$orderId]);
        $userId = (int)$ownerStmt->fetchColumn();

        $result = PaymentService::captureForUser(
            $userId,
            $adminId,
            'orders',
            [$orderId],
            null,
            $method,
            $provider,
            $providerRef,
            bin2hex(random_bytes(24))
        );

        return (int)($result['payment_ids'][0] ?? 0);

        /* Ancienne implémentation conservée temporairement pour faciliter la comparaison de migration. */
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("\n                SELECT\n                    o.id,\n                    o.user_id,\n                    o.status,\n                    o.currency,\n                    o.total_price,\n                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount\n                FROM orders o\n                LEFT JOIN payments p ON p.order_id = o.id\n                WHERE o.id = ?\n                GROUP BY o.id, o.user_id, o.status, o.currency, o.total_price\n                LIMIT 1\n            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException("Commande introuvable.");
            }

            $remainingDue = (float)$order['total_price'] - (float)$order['paid_amount'];

            if ($remainingDue <= 0) {
                throw new RuntimeException("Cette commande est déjà soldée.");
            }

            $userId = (int)$order['user_id'];

            $insert = $db->prepare("\n                INSERT INTO payments (\n                    order_id,\n                    payment_author_id,\n                    admin_id,\n                    amount_paid,\n                    method,\n                    provider,\n                    provider_ref,\n                    status,\n                    currency\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, 'captured', ?)\n            ");
            $insert->execute([
                $orderId,
                $userId,
                $adminId,
                $remainingDue,
                $method,
                $provider,
                $providerRef,
                $order['currency']
            ]);

            $paymentId = (int)$db->lastInsertId();

            $updateOrder = $db->prepare("\n                UPDATE orders\n                SET status = 'paid'\n                WHERE id = ?\n            ");
            $updateOrder->execute([$orderId]);

            $updateUser = $db->prepare("\n                UPDATE users\n                SET note = GREATEST(note - ?, 0)\n                WHERE id = ?\n            ");
            $updateUser->execute([$remainingDue, $userId]);

            $log = $db->prepare("\n                INSERT INTO logs (admin_id, action, details)\n                VALUES (?, ?, ?)\n            ");
            $log->execute([
                $adminId,
                'payment_captured',
                'Paiement capturé pour commande #' . $orderId . ' / paiement #' . $paymentId
            ]);

            $db->commit();
            return $paymentId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function captureSelectedPendingPaymentsForUser(
        int $userId,
        int $adminId,
        array $orderIds,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): array {
        require_once __DIR__ . '/../services/PaymentService.php';

        return PaymentService::captureForUser(
            $userId,
            $adminId,
            'orders',
            $orderIds,
            null,
            $method,
            $provider,
            $providerRef,
            bin2hex(random_bytes(24))
        );

        /* Ancienne implémentation conservée temporairement pour faciliter la comparaison de migration. */
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static function (int $orderId): bool {
                return $orderId > 0;
            })));

            if (empty($orderIds)) {
                throw new RuntimeException("Aucune commande sélectionnée.");
            }

            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $params = array_merge([$userId], $orderIds);

            $stmt = $db->prepare("\n                SELECT\n                    o.id,\n                    o.total_price,\n                    o.currency,\n                    o.created_at,\n                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount\n                FROM orders o\n                LEFT JOIN payments p ON p.order_id = o.id\n                WHERE o.user_id = ?\n                  AND o.id IN ({$placeholders})\n                  AND o.status IN ('pending_payment', 'paid', 'partially_refunded')\n                GROUP BY o.id, o.total_price, o.currency, o.created_at\n                HAVING (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) > 0\n                ORDER BY o.created_at ASC, o.id ASC\n            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($orders)) {
                throw new RuntimeException("Aucune commande sélectionnée n'est encaissable.");
            }

            $insertPayment = $db->prepare("\n                INSERT INTO payments (\n                    order_id,\n                    payment_author_id,\n                    admin_id,\n                    amount_paid,\n                    method,\n                    provider,\n                    provider_ref,\n                    status,\n                    currency\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, 'captured', ?)\n            ");

            $updateOrderPaid = $db->prepare("\n                UPDATE orders\n                SET status = 'paid'\n                WHERE id = ?\n            ");

            $updateUserNote = $db->prepare("\n                UPDATE users\n                SET note = GREATEST(note - ?, 0)\n                WHERE id = ?\n            ");

            $insertLog = $db->prepare("\n                INSERT INTO logs (admin_id, action, details)\n                VALUES (?, ?, ?)\n            ");

            $paymentsCount = 0;
            $appliedAmount = 0.0;
            $capturedOrderIds = [];

            foreach ($orders as $order) {
                $alreadyPaid = (float)$order['paid_amount'];
                $orderTotal = (float)$order['total_price'];
                $due = $orderTotal - $alreadyPaid;

                if ($due <= 0) {
                    continue;
                }

                $insertPayment->execute([
                    (int)$order['id'],
                    $userId,
                    $adminId,
                    $due,
                    $method,
                    $provider,
                    $providerRef,
                    $order['currency']
                ]);

                $paymentId = (int)$db->lastInsertId();

                self::updateOrderFinancialStatus($db, (int)$order['id']);
                $updateUserNote->execute([$due, $userId]);

                $insertLog->execute([
                    $adminId,
                    'selected_payment_captured',
                    'Paiement de ' . $due . ' EUR sur commande #' . (int)$order['id'] . ' / paiement #' . $paymentId
                ]);

                $paymentsCount++;
                $appliedAmount += $due;
                $capturedOrderIds[] = (int)$order['id'];
            }

            $db->commit();

            return [
                'payments_count' => $paymentsCount,
                'applied_amount' => $appliedAmount,
                'order_ids' => $capturedOrderIds,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function captureAllPendingPaymentsForUser(
        int $userId,
        int $adminId,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): int {
        require_once __DIR__ . '/../services/PaymentService.php';

        $result = PaymentService::captureForUser(
            $userId,
            $adminId,
            'balance',
            [],
            null,
            $method,
            $provider,
            $providerRef,
            bin2hex(random_bytes(24))
        );

        return (int)$result['payments_count'];
    }

    public static function captureCustomAmountForUser(
        int $userId,
        int $adminId,
        float $amount,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): array {
        require_once __DIR__ . '/../services/PaymentService.php';

        return PaymentService::captureForUser(
            $userId,
            $adminId,
            'free',
            [],
            UserBalance::decimalToCents(number_format($amount, 2, '.', '')),
            $method,
            $provider,
            $providerRef,
            bin2hex(random_bytes(24))
        );

        /* Ancienne implémentation conservée temporairement pour faciliter la comparaison de migration. */
        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($amount <= 0) {
                throw new RuntimeException("Le montant doit être supérieur à 0.");
            }

            $userStmt = $db->prepare("
                SELECT id, note
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException("Utilisateur introuvable.");
            }

            $currentNote = max(0.0, (float)($user['note'] ?? 0));
            if ($currentNote <= 0) {
                throw new RuntimeException("Aucune note à encaisser pour cet utilisateur.");
            }

            if ($amount > $currentNote) {
                throw new RuntimeException("Le montant saisi dépasse la note restante de l'utilisateur.");
            }

            $ordersStmt = $db->prepare("
                SELECT
                    o.id,
                    o.total_price,
                    o.currency,
                    o.created_at,
                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.user_id = ?
                  AND o.status IN ('pending_payment', 'paid', 'partially_refunded')
                GROUP BY o.id, o.total_price, o.currency, o.created_at
                HAVING (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) > 0
                ORDER BY o.created_at ASC, o.id ASC
            ");
            $ordersStmt->execute([$userId]);
            $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertPayment = $db->prepare("
                INSERT INTO payments (
                    order_id,
                    payment_author_id,
                    admin_id,
                    amount_paid,
                    method,
                    provider,
                    provider_ref,
                    status,
                    currency
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'captured', ?)
            ");

            $updateUserNote = $db->prepare("
                UPDATE users
                SET note = GREATEST(note - ?, 0)
                WHERE id = ?
            ");

            $insertLog = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");

            $remainingToApply = $amount;
            $paymentsCount = 0;
            $fullyPaidOrders = 0;
            $appliedAmount = 0.0;

            foreach ($orders as $order) {
                if ($remainingToApply <= 0) {
                    break;
                }

                $alreadyPaid = (float)$order['paid_amount'];
                $orderTotal = (float)$order['total_price'];
                $due = $orderTotal - $alreadyPaid;

                if ($due <= 0) {
                    continue;
                }

                $appliedToOrder = min($remainingToApply, $due);

                $insertPayment->execute([
                    (int)$order['id'],
                    $userId,
                    $adminId,
                    $appliedToOrder,
                    $method,
                    $provider,
                    $providerRef,
                    $order['currency']
                ]);

                $paymentId = (int)$db->lastInsertId();

                self::updateOrderFinancialStatus($db, (int)$order['id']);

                if (($appliedToOrder + $alreadyPaid) >= $orderTotal) {
                    $fullyPaidOrders++;
                }

                $updateUserNote->execute([$appliedToOrder, $userId]);

                $insertLog->execute([
                    $adminId,
                    'partial_payment_captured',
                    'Paiement de ' . $appliedToOrder . ' EUR sur commande #' . (int)$order['id'] . ' / paiement #' . $paymentId
                ]);

                $remainingToApply -= $appliedToOrder;
                $appliedAmount += $appliedToOrder;
                $paymentsCount++;
            }

            if ($remainingToApply > 0) {
                $insertPayment->execute([
                    null,
                    $userId,
                    $adminId,
                    $remainingToApply,
                    $method,
                    $provider,
                    $providerRef,
                    'EUR'
                ]);

                $balancePaymentId = (int)$db->lastInsertId();
                $updateUserNote->execute([$remainingToApply, $userId]);

                $insertLog->execute([
                    $adminId,
                    'manual_note_payment_captured',
                    'Paiement de ' . $remainingToApply . ' EUR sur note libre utilisateur #' . $userId . ' / paiement #' . $balancePaymentId
                ]);

                $appliedAmount += $remainingToApply;
                $paymentsCount++;
                $remainingToApply = 0.0;
            }

            $insertLog->execute([
                $adminId,
                'custom_user_payment_captured',
                'Encaissement libre utilisateur #' . $userId . ' / montant ' . $appliedAmount . ' EUR / ' . $paymentsCount . ' paiement(s)'
            ]);

            $db->commit();

            return [
                'payments_count' => $paymentsCount,
                'fully_paid_orders' => $fullyPaidOrders,
                'applied_amount' => $appliedAmount,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getLastCapturedForUser(int $userId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("\n            SELECT\n                p.id,\n                p.order_id,\n                p.amount_paid,\n                p.payment_date,\n                p.method,\n                p.provider,\n                p.provider_ref,\n                p.status,\n                p.currency\n            FROM payments p\n            LEFT JOIN orders o ON o.id = p.order_id\n            WHERE (p.payment_author_id = ? OR o.user_id = ?)\n              AND p.status = 'captured'\n            ORDER BY p.payment_date DESC, p.id DESC\n            LIMIT 1\n        ");
        $stmt->execute([$userId, $userId]);

        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $payment ?: null;
    }

    public static function refundOrderFull(int $orderId, int $adminId, ?string $refundStockAction = 'restock'): array
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $refundStockAction = self::normalizeRefundStockAction($refundStockAction);
            self::lockOrderAndOwner($db, $orderId);
            $order = self::fetchRefundableOrderSummary($db, $orderId);

            if (!$order) {
                throw new RuntimeException("Commande introuvable.");
            }

            if ((float)$order['remaining_refundable_total'] <= 0) {
                throw new RuntimeException("Cette commande ne peut plus être remboursée.");
            }

            $itemsStmt = $db->prepare("\n                SELECT\n                    oi.id,\n                    oi.order_id,\n                    oi.variant_id,\n                    oi.quantity,\n                    oi.unit_price,\n                    COALESCE(SUM(r.quantity_refunded), 0) AS refunded_quantity\n                FROM order_items oi\n                LEFT JOIN refunds r ON r.order_item_id = oi.id\n                WHERE oi.order_id = ?\n                GROUP BY oi.id, oi.order_id, oi.variant_id, oi.quantity, oi.unit_price\n                ORDER BY oi.id ASC\n            ");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new RuntimeException("Impossible de rembourser une commande vide.");
            }

            $remainingItemsAmountCents = 0;
            foreach ($items as $item) {
                $remainingQuantity = (int)$item['quantity'] - (int)$item['refunded_quantity'];
                if ($remainingQuantity > 0) {
                    $remainingItemsAmountCents += UserBalance::decimalToCents($item['unit_price']) * $remainingQuantity;
                }
            }

            $remainingRefundableCents = UserBalance::decimalToCents($order['remaining_refundable_total']);
            if ($remainingItemsAmountCents > $remainingRefundableCents) {
                throw new RuntimeException(
                    "Le paiement ne couvre pas tous les produits restants. Utilise le remboursement partiel pour choisir les lignes concernées."
                );
            }

            $paymentId = self::findLastCapturedPaymentIdForOrder($db, $orderId);
            $insertRefund = $db->prepare("\n                INSERT INTO refunds (\n                    order_id,\n                    order_item_id,\n                    payment_id,\n                    admin_id,\n                    variant_id,\n                    quantity_refunded,\n                    amount,\n                    reason\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n            ");

            $refundedAmount = 0.0;
            $refundedQuantity = 0;
            $refundIds = [];

            foreach ($items as $item) {
                $remainingQuantity = (int)$item['quantity'] - (int)$item['refunded_quantity'];
                if ($remainingQuantity <= 0) {
                    continue;
                }

                $amount = round($remainingQuantity * (float)$item['unit_price'], 2);

                $reason = $refundStockAction === 'consumed'
                    ? 'Remboursement total — produit consommé / détruit'
                    : 'Remboursement total — produit reversé au stock';

                $insertRefund->execute([
                    $orderId,
                    (int)$item['id'],
                    $paymentId,
                    $adminId,
                    $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
                    $remainingQuantity,
                    $amount,
                    $reason
                ]);
                $refundIds[] = (int)$db->lastInsertId();

                if ($item['variant_id'] !== null && $refundStockAction === 'restock') {
                    self::restoreVariantStock(
                        $db,
                        (int)$item['variant_id'],
                        $remainingQuantity,
                        [
                            'admin_id' => $adminId,
                            'order_id' => $orderId,
                            'meta' => 'order_item_id=' . (int)$item['id'] . ';type=full_refund;stock_action=restock',
                            'note' => 'Restock après remboursement intégral',
                            'allow_archived' => true,
                        ]
                    );
                }

                $refundedAmount += $amount;
                $refundedQuantity += $remainingQuantity;
            }

            if ($refundedAmount <= 0) {
                throw new RuntimeException("Aucun montant remboursable restant sur cette commande.");
            }

            self::updateOrderFinancialStatus($db, $orderId);
            self::restoreCreditForRefund(
                $db,
                $orderId,
                (int)$order['user_id'],
                UserBalance::decimalToCents(number_format($refundedAmount, 2, '.', '')),
                $adminId,
                $refundIds
            );

            $log = $db->prepare("\n                INSERT INTO logs (admin_id, action, details)\n                VALUES (?, ?, ?)\n            ");
            $log->execute([
                $adminId,
                'order_refunded_full',
                'Commande #' . $orderId . ' remboursée intégralement / montant=' . $refundedAmount . ' / stock_action=' . $refundStockAction
            ]);

            $db->commit();

            return [
                'order_id' => $orderId,
                'refunded_amount' => round($refundedAmount, 2),
                'refunded_quantity' => $refundedQuantity,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function refundOrderItemPartial(
        int $orderItemId,
        int $quantity,
        int $adminId,
        ?string $refundStockAction = 'restock',
        bool $manageTransaction = true
    ): array
    {
        $db = self::getDB();

        if ($manageTransaction) {
            $db->beginTransaction();
        } elseif (!$db->inTransaction()) {
            throw new LogicException("Une transaction active est requise pour ce remboursement.");
        }

        try {
            $refundStockAction = self::normalizeRefundStockAction($refundStockAction);

            if ($quantity <= 0) {
                throw new RuntimeException("La quantité à rembourser doit être supérieure à 0.");
            }

            $orderOwnerStmt = $db->prepare("
                SELECT o.id, o.user_id
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE oi.id = ?
                LIMIT 1
            ");
            $orderOwnerStmt->execute([$orderItemId]);
            $orderOwner = $orderOwnerStmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderOwner) {
                throw new RuntimeException("Ligne de commande introuvable.");
            }

            self::lockOrderAndOwner($db, (int)$orderOwner['id']);

            $itemLockStmt = $db->prepare('SELECT id FROM order_items WHERE id = ? LIMIT 1 FOR UPDATE');
            $itemLockStmt->execute([$orderItemId]);

            $stmt = $db->prepare("\n                SELECT\n                    oi.id,\n                    oi.order_id,\n                    oi.variant_id,\n                    oi.quantity,\n                    oi.unit_price,\n                    o.status AS order_status,\n                    o.total_price,\n                    COALESCE(SUM(r.quantity_refunded), 0) AS refunded_quantity\n                FROM order_items oi\n                INNER JOIN orders o ON o.id = oi.order_id\n                LEFT JOIN refunds r ON r.order_item_id = oi.id\n                WHERE oi.id = ?\n                GROUP BY oi.id, oi.order_id, oi.variant_id, oi.quantity, oi.unit_price, o.status, o.total_price\n                LIMIT 1\n            ");
            $stmt->execute([$orderItemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException("Ligne de commande introuvable.");
            }

            $order = self::fetchRefundableOrderSummary($db, (int)$item['order_id']);
            if (!$order || (float)$order['remaining_refundable_total'] <= 0) {
                throw new RuntimeException("Cette commande ne peut plus être remboursée.");
            }

            $remainingQuantity = (int)$item['quantity'] - (int)$item['refunded_quantity'];
            if ($remainingQuantity <= 0) {
                throw new RuntimeException("Cette ligne a déjà été totalement remboursée.");
            }

            if ($quantity > $remainingQuantity) {
                throw new RuntimeException("La quantité demandée dépasse la quantité remboursable restante.");
            }

            $amount = round($quantity * (float)$item['unit_price'], 2);
            if ($amount > (float)$order['remaining_refundable_total']) {
                throw new RuntimeException("Le montant à rembourser dépasse le reste remboursable de la commande.");
            }

            $paymentId = self::findLastCapturedPaymentIdForOrder($db, (int)$item['order_id']);

            $insertRefund = $db->prepare("\n                INSERT INTO refunds (\n                    order_id,\n                    order_item_id,\n                    payment_id,\n                    admin_id,\n                    variant_id,\n                    quantity_refunded,\n                    amount,\n                    reason\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $reason = $refundStockAction === 'consumed'
                ? 'Remboursement partiel — produit consommé / détruit'
                : 'Remboursement partiel — produit reversé au stock';

            $insertRefund->execute([
                (int)$item['order_id'],
                (int)$item['id'],
                $paymentId,
                $adminId,
                $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
                $quantity,
                $amount,
                $reason
            ]);
            $refundId = (int)$db->lastInsertId();

            if ($item['variant_id'] !== null && $refundStockAction === 'restock') {
                self::restoreVariantStock(
                    $db,
                    (int)$item['variant_id'],
                    $quantity,
                    [
                        'admin_id' => $adminId,
                        'order_id' => (int)$item['order_id'],
                        'meta' => 'order_item_id=' . (int)$item['id'] . ';type=partial_refund;stock_action=restock',
                        'note' => 'Restock après remboursement partiel',
                        'allow_archived' => true,
                    ]
                );
            }

            self::updateOrderFinancialStatus($db, (int)$item['order_id']);
            self::restoreCreditForRefund(
                $db,
                (int)$item['order_id'],
                (int)$orderOwner['user_id'],
                UserBalance::decimalToCents(number_format($amount, 2, '.', '')),
                $adminId,
                [$refundId]
            );

            $log = $db->prepare("\n                INSERT INTO logs (admin_id, action, details)\n                VALUES (?, ?, ?)\n            ");
            $log->execute([
                $adminId,
                'order_item_refunded_partial',
                'Commande #' . (int)$item['order_id'] . ' / ligne #' . (int)$item['id'] . ' / quantité=' . $quantity . ' / montant=' . $amount . ' / stock_action=' . $refundStockAction
            ]);

            if ($manageTransaction) {
                $db->commit();
            }

            return [
                'refund_id' => $refundId,
                'order_id' => (int)$item['order_id'],
                'order_item_id' => (int)$item['id'],
                'refunded_quantity' => $quantity,
                'refunded_amount' => $amount,
            ];
        } catch (Throwable $e) {
            if ($manageTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function fetchRefundableOrderSummary(PDO $db, int $orderId): ?array
    {
        $stmt = $db->prepare("\n            SELECT\n                o.id,\n                o.user_id,\n                o.status,\n                o.total_price,\n                COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS captured_paid_total,\n                COALESCE((SELECT SUM(r.amount) FROM refunds r WHERE r.order_id = o.id), 0) AS refunded_total\n            FROM orders o\n            LEFT JOIN payments p ON p.order_id = o.id\n            WHERE o.id = ?\n            GROUP BY o.id, o.user_id, o.status, o.total_price\n            LIMIT 1\n        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $captured = (float)$order['captured_paid_total'];
        $refunded = (float)$order['refunded_total'];
        $order['remaining_refundable_total'] = max($captured - $refunded, 0);

        return $order;
    }

    private static function findLastCapturedPaymentIdForOrder(PDO $db, int $orderId): ?int
    {
        $stmt = $db->prepare("\n            SELECT id\n            FROM payments\n            WHERE order_id = ?\n              AND status = 'captured'\n            ORDER BY payment_date DESC, id DESC\n            LIMIT 1\n        ");
        $stmt->execute([$orderId]);
        $paymentId = $stmt->fetchColumn();

        return $paymentId !== false ? (int)$paymentId : null;
    }


    public static function refreshOrderFinancialStatus(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $db = self::getDB();
        self::updateOrderFinancialStatus($db, $orderId);
    }

    public static function refreshAllOrderFinancialStatuses(): int
    {
        $db = self::getDB();

        $stmt = $db->query("
            SELECT id
            FROM orders
            WHERE status IN ('pending_payment', 'paid', 'partially_refunded', 'refunded')
            ORDER BY id ASC
        ");

        $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $updated = 0;

        foreach ($orderIds as $orderId) {
            self::updateOrderFinancialStatus($db, (int)$orderId);
            $updated++;
        }

        return $updated;
    }

    private static function normalizeRefundStockAction(?string $refundStockAction): string
    {
        return $refundStockAction === 'consumed' ? 'consumed' : 'restock';
    }

    private static function lockOrderAndOwner(PDO $db, int $orderId): int
    {
        $ownerStmt = $db->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$orderId]);
        $userId = (int)$ownerStmt->fetchColumn();

        if ($userId <= 0) {
            throw new RuntimeException("Commande introuvable.");
        }

        UserBalance::lockBalance($db, $userId);
        $orderStmt = $db->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
        $orderStmt->execute([$orderId]);

        return $userId;
    }

    private static function restoreCreditForRefund(
        PDO $db,
        int $orderId,
        int $userId,
        int $refundCents,
        int $adminId,
        array $refundIds
    ): void {
        if ($refundCents <= 0 || $userId <= 0) {
            return;
        }

        $creditStmt = $db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM payments
            WHERE order_id = ?
              AND status = 'captured'
              AND method = 'credit'
        ");
        $creditStmt->execute([$orderId]);
        $creditCapturedCents = UserBalance::decimalToCents($creditStmt->fetchColumn());

        $restoredStmt = $db->prepare("
            SELECT COALESCE(-SUM(amount_delta), 0)
            FROM user_balance_movements
            WHERE order_id = ?
              AND movement_type = 'credit_refund'
        ");
        $restoredStmt->execute([$orderId]);
        $alreadyRestoredCents = UserBalance::decimalToCents($restoredStmt->fetchColumn());
        $creditToRestoreCents = min($refundCents, max(0, $creditCapturedCents - $alreadyRestoredCents));

        if ($creditToRestoreCents <= 0) {
            return;
        }

        $refundIds = array_values(array_filter(array_map('intval', $refundIds)));
        $movementSuffix = !empty($refundIds) ? implode('-', $refundIds) : bin2hex(random_bytes(8));

        UserBalance::applyMovement(
            $db,
            $userId,
            -$creditToRestoreCents,
            'credit_refund',
            'credit-refund-' . $movementSuffix,
            $adminId,
            $orderId,
            null,
            'Restauration d’avoir après remboursement de la commande #' . $orderId
        );
    }

    private static function restoreVariantStock(PDO $db, int $variantId, int $quantity, array $context = []): void
    {
        if ($quantity <= 0) {
            return;
        }

        Inventory::adjustStock($db, $variantId, $quantity, 'refund', $context);
    }

    private static function updateOrderFinancialStatus(PDO $db, int $orderId): void
    {
        $summary = self::fetchRefundableOrderSummary($db, $orderId);
        if (!$summary) {
            throw new RuntimeException("Commande introuvable.");
        }

        $currentStatus = (string)($summary['status'] ?? '');
        $captured = (float)($summary['captured_paid_total'] ?? 0);
        $refunded = (float)($summary['refunded_total'] ?? 0);
        $total = (float)($summary['total_price'] ?? 0);

        /*
         * Important :
         * une commande annulée admin avant paiement ne doit jamais être
         * recalculée en "pending_payment" par le moteur financier.
         */
        if ($currentStatus === 'cancelled' && $captured <= 0 && $refunded <= 0) {
            return;
        }

        if ($refunded > 0 && $captured > 0 && $refunded >= $captured) {
            $status = 'refunded';
        } elseif ($refunded > 0) {
            $status = 'partially_refunded';
        } elseif ($captured >= $total && $total > 0) {
            $status = 'paid';
        } else {
            $status = 'pending_payment';
        }

        $stmt = $db->prepare("
        UPDATE orders
        SET status = ?
        WHERE id = ?
    ");
        $stmt->execute([$status, $orderId]);
    }
}
