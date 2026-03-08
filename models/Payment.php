<?php
require_once __DIR__ . '/../core/Model.php';

class Payment extends Model
{
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
                COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount,
                (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) AS remaining_due,
                COUNT(DISTINCT oi.id) AS item_lines
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.status IN ('pending_payment', 'paid')
        ";

        $params = [];

        if ($userId) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }

        $sql .= "
            GROUP BY
                o.id, o.user_id, o.status, o.currency, o.total_price, o.created_at,
                u.firstname, u.lastname, u.username, u.note
            HAVING remaining_due > 0
            ORDER BY o.created_at ASC, o.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentPayments(int $limit = 10): array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.order_id,
                p.amount_paid,
                p.payment_date,
                p.method,
                p.provider,
                p.provider_ref,
                p.status,
                p.currency,
                payer.firstname AS payer_firstname,
                payer.lastname AS payer_lastname,
                payer.username AS payer_username,
                admin.firstname AS admin_firstname,
                admin.lastname AS admin_lastname
            FROM payments p
            LEFT JOIN users payer ON payer.id = p.payment_author_id
            LEFT JOIN users admin ON admin.id = p.admin_id
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPendingTotalForUser(int $userId): float
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(t.remaining_due), 0) AS pending_total
            FROM (
                SELECT
                    o.id,
                    (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) AS remaining_due
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.user_id = ?
                  AND o.status IN ('pending_payment', 'paid')
                GROUP BY o.id, o.total_price
                HAVING remaining_due > 0
            ) AS t
        ");
        $stmt->execute([$userId]);

        return (float)$stmt->fetchColumn();
    }

    public static function captureOrderPayment(
        int $orderId,
        int $adminId,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): int {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                SELECT
                    o.id,
                    o.user_id,
                    o.status,
                    o.currency,
                    o.total_price,
                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.id = ?
                GROUP BY o.id, o.user_id, o.status, o.currency, o.total_price
                LIMIT 1
            ");
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

            $insert = $db->prepare("
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

            $updateOrder = $db->prepare("
                UPDATE orders
                SET status = 'paid'
                WHERE id = ?
            ");
            $updateOrder->execute([$orderId]);

            $updateUser = $db->prepare("
                UPDATE users
                SET note = GREATEST(note - ?, 0)
                WHERE id = ?
            ");
            $updateUser->execute([$remainingDue, $userId]);

            $log = $db->prepare("
                INSERT INTO logs (admin_id, action, details)
                VALUES (?, ?, ?)
            ");
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

    public static function captureAllPendingPaymentsForUser(
        int $userId,
        int $adminId,
        string $method,
        ?string $provider = null,
        ?string $providerRef = null
    ): int {
        $total = self::getPendingTotalForUser($userId);

        if ($total <= 0) {
            throw new RuntimeException("Aucune commande en attente pour cet utilisateur.");
        }

        $result = self::captureCustomAmountForUser(
            $userId,
            $adminId,
            $total,
            $method,
            $provider,
            $providerRef
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
        $db = self::getDB();
        $db->beginTransaction();

        try {
            if ($amount <= 0) {
                throw new RuntimeException("Le montant doit être supérieur à 0.");
            }

            $ordersStmt = $db->prepare("
                SELECT
                    o.id,
                    o.total_price,
                    o.currency,
                    COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0) AS paid_amount
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.user_id = ?
                  AND o.status IN ('pending_payment', 'paid')
                GROUP BY o.id, o.total_price, o.currency, o.created_at
                HAVING (o.total_price - COALESCE(SUM(CASE WHEN p.status = 'captured' THEN p.amount_paid ELSE 0 END), 0)) > 0
                ORDER BY o.created_at ASC, o.id ASC
            ");
            $ordersStmt->execute([$userId]);
            $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($orders)) {
                throw new RuntimeException("Aucune commande en attente pour cet utilisateur.");
            }

            $totalPending = 0.0;
            foreach ($orders as $order) {
                $totalPending += ((float)$order['total_price'] - (float)$order['paid_amount']);
            }

            if ($amount > $totalPending) {
                throw new RuntimeException("Le montant saisi dépasse la note restante de l'utilisateur.");
            }

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

            $updateOrderPaid = $db->prepare("
                UPDATE orders
                SET status = 'paid'
                WHERE id = ?
            ");

            $updateOrderPending = $db->prepare("
                UPDATE orders
                SET status = 'pending_payment'
                WHERE id = ?
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

                if (($appliedToOrder + $alreadyPaid) >= $orderTotal) {
                    $updateOrderPaid->execute([(int)$order['id']]);
                    $fullyPaidOrders++;
                } else {
                    $updateOrderPending->execute([(int)$order['id']]);
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
}