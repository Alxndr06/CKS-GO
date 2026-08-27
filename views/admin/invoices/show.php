<?php
$invoice = is_array($invoice ?? null) ? $invoice : [];
$snapshot = is_array($snapshot ?? null) ? $snapshot : [];
$customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
$totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
$invoiceNumber = trim((string)($invoice['invoice_number'] ?? ''));
$orderId = (int)($snapshot['order_id'] ?? $invoice['order_id'] ?? 0);
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

$invoiceDocument = [
    'number' => $invoiceNumber,
    'order_id' => $orderId,
    'issued_at' => (string)($invoice['issued_at'] ?? ''),
    'order_created_at' => (string)($snapshot['created_at'] ?? ''),
    'status' => (string)($snapshot['status'] ?? ''),
    'currency' => (string)($snapshot['currency'] ?? 'EUR'),
    'customer' => $customer,
    'items' => is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [],
    'totals' => [
        'total_price' => (float)($totals['total_price'] ?? 0),
        'captured_total' => (float)($totals['captured_paid_total'] ?? 0),
        'refunded_total' => (float)($totals['refunded_total'] ?? 0),
        'net_total' => (float)($totals['net_paid_total'] ?? 0),
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/public/css/styles.css', ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars($baseUrl . '/public/js/script.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body class="invoice_standalone_body">
<main class="invoice_standalone_page">
    <nav class="invoice_screen_actions" aria-label="Actions de la facture">
        <a class="invoice_action invoice_action_secondary" href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= $orderId ?>">
            Retour à la commande
        </a>
        <button class="invoice_action invoice_action_primary" type="button" data-print-trigger>
            Imprimer la facture
        </button>
    </nav>

    <?php require __DIR__ . '/../../partials/invoice_document.php'; ?>
</main>
</body>
</html>
