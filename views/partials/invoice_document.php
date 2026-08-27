<?php
$invoiceDocument = is_array($invoiceDocument ?? null) ? $invoiceDocument : [];
$invoiceCustomer = is_array($invoiceDocument['customer'] ?? null) ? $invoiceDocument['customer'] : [];
$invoiceTotals = is_array($invoiceDocument['totals'] ?? null) ? $invoiceDocument['totals'] : [];
$invoiceItems = is_array($invoiceDocument['items'] ?? null) ? $invoiceDocument['items'] : [];

$invoiceNumber = trim((string)($invoiceDocument['number'] ?? ''));
$invoiceOrderId = (int)($invoiceDocument['order_id'] ?? 0);
$invoiceCurrency = strtoupper(trim((string)($invoiceDocument['currency'] ?? 'EUR')));
$invoiceCurrency = $invoiceCurrency !== '' ? $invoiceCurrency : 'EUR';
$invoiceIssuedAt = trim((string)($invoiceDocument['issued_at'] ?? ''));
$invoiceOrderCreatedAt = trim((string)($invoiceDocument['order_created_at'] ?? ''));
$invoiceRawStatus = strtolower(trim((string)($invoiceDocument['status'] ?? '')));

$invoiceStatusLabels = [
    'paid' => 'Payée',
    'partially_refunded' => 'Partiellement remboursée',
    'refunded' => 'Remboursée',
    'pending_payment' => 'En attente de paiement',
    'pending' => 'En attente',
    'cancelled' => 'Annulée',
];
$invoiceStatusLabel = $invoiceStatusLabels[$invoiceRawStatus]
    ?? (trim((string)($invoiceDocument['status_label'] ?? '')) ?: 'Émise');
$invoiceStatusClass = match ($invoiceRawStatus) {
    'paid' => 'is_paid',
    'refunded', 'partially_refunded' => 'is_refunded',
    'cancelled' => 'is_cancelled',
    default => 'is_pending',
};

$invoiceCustomerName = trim((string)($invoiceCustomer['display_name'] ?? ''));
if ($invoiceCustomerName === '') {
    $invoiceCustomerName = trim((string)($invoiceCustomer['firstname'] ?? '') . ' ' . (string)($invoiceCustomer['lastname'] ?? ''));
}
if ($invoiceCustomerName === '') {
    $invoiceCustomerName = trim((string)($invoiceCustomer['username'] ?? '')) ?: 'Client CKS GO';
}

$formatInvoiceDate = static function (string $value, bool $withTime = false): string {
    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date($withTime ? 'd/m/Y à H:i' : 'd/m/Y', $timestamp);
};

$formatInvoiceAmount = static function (float $amount, string $currency): string {
    $currencyLabel = $currency === 'EUR' ? '€' : $currency;
    return number_format($amount, 2, ',', ' ') . ' ' . $currencyLabel;
};

$invoiceTotalPrice = (float)($invoiceTotals['total_price'] ?? 0);
$invoiceCapturedTotal = (float)($invoiceTotals['captured_total'] ?? $invoiceTotals['captured_paid_total'] ?? 0);
$invoiceRefundedTotal = (float)($invoiceTotals['refunded_total'] ?? 0);
$invoiceNetTotal = (float)($invoiceTotals['net_total'] ?? $invoiceTotals['net_paid_total'] ?? max($invoiceCapturedTotal - $invoiceRefundedTotal, 0));
?>

<article class="invoice_document" aria-labelledby="invoice-document-title">
    <header class="invoice_document_header">
        <div class="invoice_brand_block">
            <span class="invoice_brand_mark" aria-hidden="true">CG</span>
            <div>
                <strong>CKS GO</strong>
                <span>Gestion simple du quotidien.</span>
            </div>
        </div>

        <div class="invoice_title_block">
            <span>Document de facturation</span>
            <h1 id="invoice-document-title">FACTURE</h1>
            <p>N° <?= htmlspecialchars($invoiceNumber !== '' ? $invoiceNumber : '—', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </header>

    <div class="invoice_accent_rule" aria-hidden="true"></div>

    <section class="invoice_identity_grid" aria-label="Émetteur, client et références">
        <div class="invoice_party_card">
            <span class="invoice_section_label">Émetteur</span>
            <strong>CKS GO</strong>
            <p>Service de gestion interne</p>
            <p>Document émis électroniquement</p>
        </div>

        <div class="invoice_party_card">
            <span class="invoice_section_label">Facturé à</span>
            <strong><?= htmlspecialchars($invoiceCustomerName, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($invoiceCustomer['email'])): ?>
                <p><?= htmlspecialchars((string)$invoiceCustomer['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (!empty($invoiceCustomer['username'])): ?>
                <p>Compte : @<?= htmlspecialchars((string)$invoiceCustomer['username'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="invoice_reference_card">
            <dl>
                <div>
                    <dt>Date d’émission</dt>
                    <dd><?= htmlspecialchars($formatInvoiceDate($invoiceIssuedAt), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div>
                    <dt>Commande</dt>
                    <dd>#<?= $invoiceOrderId ?></dd>
                </div>
                <div>
                    <dt>Date de commande</dt>
                    <dd><?= htmlspecialchars($formatInvoiceDate($invoiceOrderCreatedAt, true), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div>
                    <dt>Statut</dt>
                    <dd><span class="invoice_status <?= $invoiceStatusClass ?>"><?= htmlspecialchars($invoiceStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="invoice_lines_section" aria-labelledby="invoice-lines-title">
        <div class="invoice_section_heading">
            <span class="invoice_section_label">Détail</span>
            <h2 id="invoice-lines-title">Lignes facturées</h2>
        </div>

        <div class="invoice_table_wrap">
            <table class="invoice_table">
                <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Détail</th>
                    <th class="is_numeric">Qté</th>
                    <th class="is_numeric">Prix unitaire</th>
                    <th class="is_numeric">Montant</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($invoiceItems === []): ?>
                    <tr>
                        <td colspan="5" class="invoice_empty_line">Aucune ligne de facturation.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoiceItems as $item): ?>
                        <?php
                        $itemQuantity = (int)($item['quantity'] ?? 0);
                        $itemUnitPrice = (float)($item['unit_price'] ?? 0);
                        $itemLineTotal = (float)($item['line_total'] ?? ($itemUnitPrice * $itemQuantity));
                        ?>
                        <tr>
                            <td data-label="Libellé"><strong><?= htmlspecialchars((string)($item['product_name'] ?? 'Ligne facturée'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td data-label="Détail"><?= htmlspecialchars((string)($item['display_name'] ?? $item['variant_name'] ?? 'Standard'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Quantité" class="is_numeric"><?= $itemQuantity ?></td>
                            <td data-label="Prix unitaire" class="is_numeric"><?= htmlspecialchars($formatInvoiceAmount($itemUnitPrice, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Montant" class="is_numeric"><strong><?= htmlspecialchars($formatInvoiceAmount($itemLineTotal, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="invoice_summary" aria-label="Résumé de la facture">
        <div class="invoice_payment_note">
            <span class="invoice_section_label">Règlement</span>
            <strong><?= htmlspecialchars($invoiceStatusLabel, ENT_QUOTES, 'UTF-8') ?></strong>
            <p>Montants enregistrés dans CKS GO à la date d’émission de ce document.</p>
        </div>

        <dl class="invoice_amounts">
            <div>
                <dt>Total commande</dt>
                <dd><?= htmlspecialchars($formatInvoiceAmount($invoiceTotalPrice, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Montant réglé</dt>
                <dd><?= htmlspecialchars($formatInvoiceAmount($invoiceCapturedTotal, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($invoiceRefundedTotal > 0): ?>
                <div class="is_refund">
                    <dt>Remboursement</dt>
                    <dd>− <?= htmlspecialchars($formatInvoiceAmount($invoiceRefundedTotal, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php endif; ?>
            <div class="invoice_grand_total">
                <dt>Net payé</dt>
                <dd><?= htmlspecialchars($formatInvoiceAmount($invoiceNetTotal, $invoiceCurrency), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        </dl>
    </section>

    <footer class="invoice_document_footer">
        <div>
            <strong>Merci pour votre confiance.</strong>
            <p>Document édité électroniquement par CKS GO.</p>
        </div>
        <p class="invoice_footer_reference"><?= htmlspecialchars($invoiceNumber !== '' ? $invoiceNumber : 'CKS GO', ENT_QUOTES, 'UTF-8') ?></p>
    </footer>
</article>
