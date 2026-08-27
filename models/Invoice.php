<?php
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/Order.php';

class Invoice extends Model
{
    private static function decodeRow(array $invoice): array
    {
        $snapshot = [];

        if (!empty($invoice['snapshot_json'])) {
            $decoded = json_decode((string)$invoice['snapshot_json'], true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        $invoice['snapshot'] = $snapshot;

        return $invoice;
    }

    private static function buildSnapshot(array $order): array
    {
        $items = [];

        foreach ((array)($order['items'] ?? []) as $item) {
            $displayName = trim((string)($item['display_name'] ?? ''));

            if ($displayName === '') {
                $displayName = trim((string)(($item['flavor'] ?? '') ?: ($item['variant_name'] ?? '')));
            }

            $quantity = (int)($item['quantity'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $lineTotal = isset($item['line_total'])
                ? (float)$item['line_total']
                : ($unitPrice * $quantity);

            $items[] = [
                'id' => (int)($item['id'] ?? 0),
                'line_type' => (string)($item['line_type'] ?? 'product'),
                'product_name' => (string)($item['product_name'] ?? 'Produit'),
                'display_name' => $displayName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'order_id' => (int)($order['id'] ?? 0),
            'user_id' => (int)($order['user_id'] ?? 0),
            'status' => (string)($order['status'] ?? ''),
            'currency' => (string)($order['currency'] ?? 'EUR'),
            'created_at' => (string)($order['created_at'] ?? ''),
            'customer' => [
                'username' => (string)($order['username'] ?? ''),
                'firstname' => (string)($order['firstname'] ?? ''),
                'lastname' => (string)($order['lastname'] ?? ''),
                'email' => (string)($order['email'] ?? ''),
            ],
            'totals' => [
                'total_price' => (float)($order['total_price'] ?? 0),
                'captured_paid_total' => (float)($order['captured_paid_total'] ?? 0),
                'refunded_total' => (float)($order['refunded_total'] ?? 0),
                'net_paid_total' => (float)($order['net_paid_total'] ?? 0),
            ],
            'items' => $items,
        ];
    }

    private static function getNextInvoiceNumber(PDO $db): string
    {
        $year = date('Y');
        $prefix = 'FAC-' . $year . '-';

        $stmt = $db->prepare("
            SELECT invoice_number
            FROM invoices
            WHERE invoice_number LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $lastInvoiceNumber = (string)$stmt->fetchColumn();

        $nextSequence = 1;

        if ($lastInvoiceNumber !== '' && preg_match('/^FAC-' . preg_quote($year, '/') . '-(\d+)$/', $lastInvoiceNumber, $matches)) {
            $nextSequence = ((int)$matches[1]) + 1;
        }

        return sprintf('FAC-%s-%05d', $year, $nextSequence);
    }

    public static function findById(int $invoiceId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT *
            FROM invoices
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        return self::decodeRow($invoice);
    }

    public static function findByOrderId(int $orderId): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT *
            FROM invoices
            WHERE order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        return self::decodeRow($invoice);
    }

    public static function getMapByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static function ($id) {
            return $id > 0;
        })));

        if (empty($orderIds)) {
            return [];
        }

        $db = self::getDB();
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmt = $db->prepare("
            SELECT *
            FROM invoices
            WHERE order_id IN ($placeholders)
        ");
        $stmt->execute($orderIds);

        $invoiceMap = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $decoded = self::decodeRow($row);
            $invoiceMap[(int)($decoded['order_id'] ?? 0)] = $decoded;
        }

        return $invoiceMap;
    }

    public static function createForOrder(int $orderId, int $adminId): int
    {
        if ($orderId <= 0) {
            throw new RuntimeException('Commande invalide.');
        }

        $order = Order::getAdminOrderById($orderId);

        if (!$order) {
            throw new RuntimeException('Commande introuvable.');
        }

        if ((string)($order['status'] ?? '') !== 'paid') {
            throw new RuntimeException('Seules les commandes payées peuvent être facturées.');
        }

        $db = self::getDB();
        $db->beginTransaction();

        try {
            $existingStmt = $db->prepare("
                SELECT id
                FROM invoices
                WHERE order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $existingStmt->execute([$orderId]);
            $existingInvoiceId = (int)$existingStmt->fetchColumn();

            if ($existingInvoiceId > 0) {
                $db->commit();
                return $existingInvoiceId;
            }

            $invoiceNumber = self::getNextInvoiceNumber($db);
            $snapshotJson = json_encode(
                self::buildSnapshot($order),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($snapshotJson === false) {
                throw new RuntimeException('Impossible de figer les données de facture.');
            }

            $insertStmt = $db->prepare("
                INSERT INTO invoices (order_id, invoice_number, issued_at, snapshot_json, created_by)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $insertStmt->execute([
                $orderId,
                $invoiceNumber,
                $snapshotJson,
                $adminId > 0 ? $adminId : null,
            ]);

            $invoiceId = (int)$db->lastInsertId();
            $db->commit();

            return $invoiceId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public static function createManyForOrders(array $orderIds, int $adminId): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static function ($id) {
            return $id > 0;
        })));

        $result = [
            'generated' => [],
            'existing' => [],
            'skipped' => [],
            'errors' => [],
        ];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::getAdminOrderById($orderId);

                if (!$order) {
                    $result['errors'][] = [
                        'order_id' => $orderId,
                        'reason' => 'Commande introuvable.'
                    ];
                    continue;
                }

                if ((string)($order['status'] ?? '') !== 'paid') {
                    $result['skipped'][] = [
                        'order_id' => $orderId,
                        'reason' => 'Commande non payée.'
                    ];
                    continue;
                }

                $existingInvoice = self::findByOrderId($orderId);
                if ($existingInvoice) {
                    $result['existing'][] = $existingInvoice;
                    continue;
                }

                $invoiceId = self::createForOrder($orderId, $adminId);
                $invoice = self::findById($invoiceId);

                if ($invoice) {
                    $result['generated'][] = $invoice;
                }
            } catch (Throwable $e) {
                $result['errors'][] = [
                    'order_id' => $orderId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        return $result;
    }
}
