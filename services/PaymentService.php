<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/UserBalance.php';

class PaymentService extends Model
{
    private const ALLOWED_METHODS = ['cash', 'card', 'bank_transfer', 'internal'];

    public static function captureForUser(
        int $userId,
        int $adminId,
        string $mode,
        array $orderIds,
        ?int $freeAmountCents,
        string $method,
        ?string $provider,
        ?string $providerRef,
        string $idempotencyKey
    ): array {
        if ($userId <= 0 || $adminId <= 0) {
            throw new RuntimeException("Requête d'encaissement invalide.");
        }

        if (!in_array($mode, ['orders', 'free', 'balance'], true)) {
            throw new RuntimeException("Mode d'encaissement invalide.");
        }

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new RuntimeException("Moyen de paiement invalide.");
        }

        if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) {
            throw new RuntimeException("Jeton d'encaissement invalide. Recharge la page puis réessaie.");
        }

        $provider = self::normalizeOptionalText($provider, 50);
        $providerRef = self::normalizeOptionalText($providerRef, 100);
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $existing = self::findBatchByKey($db, $idempotencyKey);
            if ($existing) {
                $db->commit();
                return self::formatBatchResult($db, $existing, true);
            }

            $balanceBeforeCents = UserBalance::lockBalance($db, $userId);
            $orders = self::lockEligibleOrders($db, $userId, $mode === 'orders' ? $orderIds : null);
            $allocations = [];
            $captureCents = 0;
            $fullyPaidOrders = 0;

            if ($mode === 'orders') {
                $normalizedIds = self::normalizeOrderIds($orderIds);

                if (empty($normalizedIds)) {
                    throw new RuntimeException("Sélectionne au moins une commande à encaisser.");
                }

                $ordersById = [];
                foreach ($orders as $order) {
                    $ordersById[(int)$order['id']] = $order;
                }

                foreach ($normalizedIds as $orderId) {
                    if (!isset($ordersById[$orderId])) {
                        throw new RuntimeException("La commande #{$orderId} n'est plus encaissable.");
                    }

                    $dueCents = (int)$ordersById[$orderId]['remaining_due_cents'];
                    if ($dueCents <= 0) {
                        throw new RuntimeException("La commande #{$orderId} est déjà soldée.");
                    }

                    $allocations[] = [
                        'order_id' => $orderId,
                        'amount_cents' => $dueCents,
                        'currency' => (string)$ordersById[$orderId]['currency'],
                    ];
                    $captureCents += $dueCents;
                    $fullyPaidOrders++;
                }
            } else {
                if ($mode === 'balance') {
                    if ($balanceBeforeCents <= 0) {
                        throw new RuntimeException("Aucun solde débiteur à encaisser pour cet utilisateur.");
                    }

                    $captureCents = $balanceBeforeCents;
                } else {
                    if ($freeAmountCents === null || $freeAmountCents <= 0) {
                        throw new RuntimeException("Le montant libre doit être supérieur à 0.");
                    }

                    $captureCents = $freeAmountCents;
                }

                $remainingCents = $captureCents;

                foreach ($orders as $order) {
                    if ($remainingCents <= 0) {
                        break;
                    }

                    $dueCents = (int)$order['remaining_due_cents'];
                    if ($dueCents <= 0) {
                        continue;
                    }

                    $allocatedCents = min($remainingCents, $dueCents);
                    $allocations[] = [
                        'order_id' => (int)$order['id'],
                        'amount_cents' => $allocatedCents,
                        'currency' => (string)$order['currency'],
                    ];
                    $remainingCents -= $allocatedCents;

                    if ($allocatedCents >= $dueCents) {
                        $fullyPaidOrders++;
                    }
                }
            }

            if ($captureCents <= 0 || $captureCents > 9999999999) {
                throw new RuntimeException("Montant d'encaissement invalide.");
            }

            $allocatedCents = array_sum(array_column($allocations, 'amount_cents'));
            $unallocatedCents = $captureCents - $allocatedCents;
            $balanceAfterCents = $balanceBeforeCents - $captureCents;

            $insertBatch = $db->prepare("
                INSERT INTO payment_batches (
                    user_id,
                    admin_id,
                    idempotency_key,
                    amount_paid,
                    allocated_amount,
                    unallocated_amount,
                    balance_before,
                    balance_after,
                    method,
                    provider,
                    provider_ref,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'captured')
            ");
            $insertBatch->execute([
                $userId,
                $adminId,
                $idempotencyKey,
                UserBalance::centsToDecimal($captureCents),
                UserBalance::centsToDecimal($allocatedCents),
                UserBalance::centsToDecimal($unallocatedCents),
                UserBalance::centsToDecimal($balanceBeforeCents),
                UserBalance::centsToDecimal($balanceAfterCents),
                $method,
                $provider,
                $providerRef,
            ]);
            $batchId = (int)$db->lastInsertId();

            $insertPayment = $db->prepare("
                INSERT INTO payments (
                    batch_id,
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'captured', ?)
            ");
            $paymentIds = [];
            $capturedOrderIds = [];

            foreach ($allocations as $allocation) {
                $insertPayment->execute([
                    $batchId,
                    $allocation['order_id'],
                    $userId,
                    $adminId,
                    UserBalance::centsToDecimal((int)$allocation['amount_cents']),
                    $method,
                    $provider,
                    $providerRef,
                    $allocation['currency'],
                ]);
                $paymentIds[] = (int)$db->lastInsertId();
                $capturedOrderIds[] = (int)$allocation['order_id'];
            }

            if ($unallocatedCents > 0) {
                $insertPayment->execute([
                    $batchId,
                    null,
                    $userId,
                    $adminId,
                    UserBalance::centsToDecimal($unallocatedCents),
                    $method,
                    $provider,
                    $providerRef,
                    'EUR',
                ]);
                $paymentIds[] = (int)$db->lastInsertId();
            }

            foreach (array_unique($capturedOrderIds) as $capturedOrderId) {
                Payment::refreshOrderFinancialStatus($capturedOrderId);
            }

            $balanceResult = UserBalance::applyMovement(
                $db,
                $userId,
                -$captureCents,
                'payment',
                'payment-batch-' . $batchId,
                $adminId,
                null,
                $batchId,
                'Encaissement #' . $batchId . ' par ' . $method
            );

            $insertLog = $db->prepare("INSERT INTO logs (admin_id, action, details) VALUES (?, ?, ?)");
            $insertLog->execute([
                $adminId,
                'payment_batch_captured',
                'Lot #' . $batchId . ' / utilisateur #' . $userId . ' / montant=' . UserBalance::centsToDecimal($captureCents) . ' / commandes=' . implode(',', array_unique($capturedOrderIds)),
            ]);

            $db->commit();

            return [
                'batch_id' => $batchId,
                'payment_ids' => $paymentIds,
                'order_ids' => array_values(array_unique($capturedOrderIds)),
                'payments_count' => count($paymentIds),
                'fully_paid_orders' => $fullyPaidOrders,
                'applied_amount' => $captureCents / 100,
                'allocated_amount' => $allocatedCents / 100,
                'unallocated_amount' => $unallocatedCents / 100,
                'balance_before' => $balanceResult['before_cents'] / 100,
                'balance_after' => $balanceResult['after_cents'] / 100,
                'credit_created' => max(0, -$balanceResult['after_cents']) / 100,
                'duplicate' => false,
            ];
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ((string)$e->getCode() === '23000') {
                $existing = self::findBatchByKey($db, $idempotencyKey);
                if ($existing) {
                    return self::formatBatchResult($db, $existing, true);
                }
            }

            throw $e;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function normalizeOrderIds(array $orderIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $orderIds), static function (int $orderId): bool {
            return $orderId > 0;
        })));
    }

    private static function lockEligibleOrders(PDO $db, int $userId, ?array $selectedOrderIds): array
    {
        $params = [$userId];
        $sql = "
            SELECT id, total_price, currency, created_at
            FROM orders
            WHERE user_id = ?
              AND status IN ('pending_payment', 'paid', 'partially_refunded')
        ";

        if ($selectedOrderIds !== null) {
            $selectedOrderIds = self::normalizeOrderIds($selectedOrderIds);
            if (empty($selectedOrderIds)) {
                return [];
            }

            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($selectedOrderIds), '?')) . ')';
            $params = array_merge($params, $selectedOrderIds);
        }

        $sql .= ' ORDER BY created_at ASC, id ASC FOR UPDATE';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orders)) {
            return [];
        }

        $orderIds = array_map(static fn(array $order): int => (int)$order['id'], $orders);
        $paymentSql = "
            SELECT order_id, COALESCE(SUM(amount_paid), 0) AS paid_amount
            FROM payments
            WHERE status = 'captured'
              AND order_id IN (" . implode(',', array_fill(0, count($orderIds), '?')) . ")
            GROUP BY order_id
        ";
        $paymentStmt = $db->prepare($paymentSql);
        $paymentStmt->execute($orderIds);
        $paidByOrder = [];

        foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $paymentSummary) {
            $paidByOrder[(int)$paymentSummary['order_id']] = UserBalance::decimalToCents($paymentSummary['paid_amount']);
        }

        $eligible = [];
        foreach ($orders as $order) {
            $orderId = (int)$order['id'];
            $totalCents = UserBalance::decimalToCents($order['total_price']);
            $remainingDueCents = $totalCents - ($paidByOrder[$orderId] ?? 0);

            if ($remainingDueCents <= 0) {
                continue;
            }

            $order['remaining_due_cents'] = $remainingDueCents;
            $eligible[] = $order;
        }

        return $eligible;
    }

    private static function normalizeOptionalText(?string $value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    private static function findBatchByKey(PDO $db, string $idempotencyKey): ?array
    {
        $stmt = $db->prepare("SELECT * FROM payment_batches WHERE idempotency_key = ? LIMIT 1");
        $stmt->execute([$idempotencyKey]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        return $batch ?: null;
    }

    private static function formatBatchResult(PDO $db, array $batch, bool $duplicate): array
    {
        $stmt = $db->prepare("SELECT id, order_id FROM payments WHERE batch_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$batch['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $paymentIds = array_map(static fn(array $payment): int => (int)$payment['id'], $payments);
        $orderIds = [];

        foreach ($payments as $payment) {
            if ($payment['order_id'] !== null) {
                $orderIds[] = (int)$payment['order_id'];
            }
        }

        $balanceAfterCents = UserBalance::decimalToCents($batch['balance_after']);

        return [
            'batch_id' => (int)$batch['id'],
            'payment_ids' => $paymentIds,
            'order_ids' => array_values(array_unique($orderIds)),
            'payments_count' => count($paymentIds),
            'fully_paid_orders' => count($orderIds),
            'applied_amount' => UserBalance::decimalToCents($batch['amount_paid']) / 100,
            'allocated_amount' => UserBalance::decimalToCents($batch['allocated_amount']) / 100,
            'unallocated_amount' => UserBalance::decimalToCents($batch['unallocated_amount']) / 100,
            'balance_before' => UserBalance::decimalToCents($batch['balance_before']) / 100,
            'balance_after' => $balanceAfterCents / 100,
            'credit_created' => max(0, -$balanceAfterCents) / 100,
            'duplicate' => $duplicate,
        ];
    }
}
