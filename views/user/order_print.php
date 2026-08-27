<?php
$invoice = is_array($invoice ?? null) ? $invoice : [];
$invoiceSnapshot = is_array($invoice['snapshot'] ?? null) ? $invoice['snapshot'] : [];
$invoiceSource = $invoiceSnapshot !== [] ? $invoiceSnapshot : $order;
$invoiceOrderId = (int)($invoiceSource['order_id'] ?? $order['id'] ?? 0);
$invoiceIssuedAt = (string)($invoice['issued_at'] ?? $invoiceSource['created_at'] ?? $order['created_at'] ?? '');
$invoiceYear = ($invoiceIssuedAt !== '' && strtotime($invoiceIssuedAt) !== false)
    ? date('Y', strtotime($invoiceIssuedAt))
    : date('Y');
$invoiceNumber = trim((string)($invoice['invoice_number'] ?? ''));

if ($invoiceNumber === '') {
    $invoiceNumber = sprintf('FAC-CMD-%s-%05d', $invoiceYear, $invoiceOrderId);
}

$invoiceCustomer = is_array($invoiceSource['customer'] ?? null)
    ? $invoiceSource['customer']
    : [
        'username' => (string)($order['username'] ?? ''),
        'firstname' => (string)($order['firstname'] ?? ''),
        'lastname' => (string)($order['lastname'] ?? ''),
        'email' => (string)($order['email'] ?? ''),
    ];
$invoiceTotalsSource = is_array($invoiceSource['totals'] ?? null) ? $invoiceSource['totals'] : $order;

$invoiceDocument = [
    'number' => $invoiceNumber,
    'order_id' => $invoiceOrderId,
    'issued_at' => $invoiceIssuedAt,
    'order_created_at' => (string)($invoiceSource['created_at'] ?? $order['created_at'] ?? ''),
    'status' => (string)($invoiceSource['status'] ?? $order['status'] ?? ''),
    'currency' => (string)($invoiceSource['currency'] ?? $order['currency'] ?? 'EUR'),
    'customer' => $invoiceCustomer,
    'items' => is_array($invoiceSource['items'] ?? null) ? $invoiceSource['items'] : (array)($order['items'] ?? []),
    'totals' => [
        'total_price' => (float)($invoiceTotalsSource['total_price'] ?? $order['total_price'] ?? 0),
        'captured_total' => (float)($invoiceTotalsSource['captured_paid_total'] ?? $invoiceTotalsSource['paid_total'] ?? $order['paid_total'] ?? 0),
        'refunded_total' => (float)($invoiceTotalsSource['refunded_total'] ?? $order['refunded_total'] ?? 0),
        'net_total' => (float)($invoiceTotalsSource['net_paid_total'] ?? $order['net_paid_total'] ?? 0),
    ],
];

$pageTitle = 'Facture ' . $invoiceNumber . ' - CKS GO';
require_once __DIR__ . '/../partials/header.php';
?>

<main class="main_part order_print_page">
    <nav class="invoice_screen_actions" aria-label="Actions de la facture">
        <a class="invoice_action invoice_action_secondary" href="index.php?controller=user&amp;action=showOrder&amp;id=<?= $invoiceOrderId ?>">
            Retour à la commande
        </a>
        <button class="invoice_action invoice_action_primary" type="button" data-print-trigger>
            Imprimer la facture
        </button>
    </nav>

    <?php require __DIR__ . '/../partials/invoice_document.php'; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
