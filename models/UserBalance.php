<?php
require_once __DIR__ . '/../core/Model.php';

class UserBalance extends Model
{
    public static function decimalToCents($value): int
    {
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        $normalized = str_replace(',', '.', trim((string)$value));

        if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("Montant invalide.");
        }

        $whole = (int)$matches[2];
        $fraction = str_pad((string)($matches[3] ?? ''), 2, '0');
        $cents = ($whole * 100) + (int)$fraction;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign . intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function lockBalance(PDO $db, int $userId): int
    {
        $stmt = $db->prepare("SELECT note FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();

        if ($balance === false) {
            throw new RuntimeException("Utilisateur introuvable.");
        }

        return self::decimalToCents($balance);
    }

    public static function applyMovement(
        PDO $db,
        int $userId,
        int $deltaCents,
        string $movementType,
        string $movementKey,
        ?int $adminId = null,
        ?int $orderId = null,
        ?int $paymentBatchId = null,
        ?string $description = null
    ): array {
        $existingStmt = $db->prepare("
            SELECT
                user_id,
                order_id,
                payment_batch_id,
                movement_type,
                amount_delta,
                balance_after
            FROM user_balance_movements
            WHERE movement_key = ?
            LIMIT 1
        ");
        $existingStmt->execute([$movementKey]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $sameUser = (int)($existing['user_id'] ?? 0) === $userId;
            $sameOrder = self::nullableIdMatches($existing['order_id'] ?? null, $orderId);
            $sameBatch = self::nullableIdMatches($existing['payment_batch_id'] ?? null, $paymentBatchId);
            $sameType = (string)($existing['movement_type'] ?? '') === $movementType;
            $sameDelta = self::decimalToCents($existing['amount_delta']) === $deltaCents;

            if (!$sameUser || !$sameOrder || !$sameBatch || !$sameType || !$sameDelta) {
                throw new RuntimeException(
                    "La clé de mouvement financier est déjà utilisée par une autre opération."
                );
            }

            $afterCents = self::decimalToCents($existing['balance_after']);

            return [
                'before_cents' => $afterCents - self::decimalToCents($existing['amount_delta']),
                'after_cents' => $afterCents,
                'duplicate' => true,
            ];
        }

        $beforeCents = self::lockBalance($db, $userId);
        $afterCents = $beforeCents + $deltaCents;

        if (abs($afterCents) > 9999999999) {
            throw new RuntimeException("Le solde obtenu dépasse la limite autorisée.");
        }

        $update = $db->prepare("UPDATE users SET note = ? WHERE id = ?");
        $update->execute([self::centsToDecimal($afterCents), $userId]);

        $insert = $db->prepare("
            INSERT INTO user_balance_movements (
                user_id,
                admin_id,
                order_id,
                payment_batch_id,
                movement_key,
                movement_type,
                amount_delta,
                balance_after,
                description
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $userId,
            $adminId,
            $orderId,
            $paymentBatchId,
            $movementKey,
            $movementType,
            self::centsToDecimal($deltaCents),
            self::centsToDecimal($afterCents),
            $description !== null ? mb_substr($description, 0, 255) : null,
        ]);

        return [
            'before_cents' => $beforeCents,
            'after_cents' => $afterCents,
            'duplicate' => false,
        ];
    }

    private static function nullableIdMatches($storedValue, ?int $expectedValue): bool
    {
        if ($storedValue === null || $storedValue === '') {
            return $expectedValue === null;
        }

        return $expectedValue !== null && (int)$storedValue === $expectedValue;
    }

    public static function getRecentForUser(int $userId, int $limit = 12): array
    {
        $db = self::getDB();
        $limit = max(1, min(50, $limit));

        $stmt = $db->prepare("
            SELECT ubm.*, admin.username AS admin_username
            FROM user_balance_movements ubm
            LEFT JOIN users admin ON admin.id = ubm.admin_id
            WHERE ubm.user_id = ?
            ORDER BY ubm.created_at DESC, ubm.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
