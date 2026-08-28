<?php
$pageStylesheets = array_merge($pageStylesheets ?? [], ['admin-orders.css']);
$pageScripts = array_merge($pageScripts ?? [], ['admin-orders.js']);
require_once __DIR__ . '/../../partials/header.php';

$totalOrdersCount = (int)($totalOrders ?? count($orders ?? []));
$invoiceMap = is_array($invoiceMap ?? null) ? $invoiceMap : [];
$summaryTotalAmount = (float)($totalAmount ?? 0);
$summaryTotalPaid = (float)($totalPaid ?? 0);
$summaryTotalRemaining = (float)($totalRemaining ?? 0);
$selectedEligibleCount = 0;

$statusMap = [
    'pending_payment' => ['label' => 'Paiement en attente', 'class' => 'is_pending'],
    'paid' => ['label' => 'Payée', 'class' => 'is_paid'],
    'partially_refunded' => ['label' => 'Partiellement remboursée', 'class' => 'is_refunded'],
    'refunded' => ['label' => 'Remboursée', 'class' => 'is_refunded'],
    'cancelled' => ['label' => 'Annulée', 'class' => 'is_cancelled'],
];

foreach (($orders ?? []) as $orderSummary) {
    $summaryOrderId = (int)($orderSummary['id'] ?? 0);

    if (($orderSummary['status'] ?? '') === 'paid' && !isset($invoiceMap[$summaryOrderId])) {
        $selectedEligibleCount++;
    }
}

$ordersPaginationTemplate = 'index.php?' . http_build_query([
    'controller' => 'admin',
    'action' => 'orders',
    'page' => '__PAGE__',
    'q' => (string)($q ?? ''),
    'status' => (string)($status ?? ''),
]);
?>

<main class="main_part admin_dashboard_page admin_page_pro admin_orders_page">
    <section class="aorders_workspace" aria-labelledby="aorders-title">
        <header class="aorders_header">
            <div class="aorders_heading">
                <span class="section_kicker">Commandes</span>
                <div>
                    <h1 id="aorders-title">Suivi des commandes</h1>
                    <p>Retrouve une vente, contrôle son règlement et accède directement à ses actions.</p>
                </div>
            </div>

            <dl class="aorders_kpis" aria-label="Synthèse financière des résultats">
                <div>
                    <dt>Commandes</dt>
                    <dd><?= $totalOrdersCount ?></dd>
                </div>
                <div>
                    <dt>Montant</dt>
                    <dd><?= number_format($summaryTotalAmount, 2, ',', ' ') ?> €</dd>
                </div>
                <div class="is_paid">
                    <dt>Encaissé</dt>
                    <dd><?= number_format($summaryTotalPaid, 2, ',', ' ') ?> €</dd>
                </div>
                <div class="<?= $summaryTotalRemaining > 0.009 ? 'is_due' : 'is_paid' ?>">
                    <dt>Reste dû</dt>
                    <dd><?= number_format($summaryTotalRemaining, 2, ',', ' ') ?> €</dd>
                </div>
            </dl>
        </header>

        <form method="get" action="index.php" class="aorders_filters" data-auto-filter-form>
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="orders">

            <label class="aorders_search">
                <span class="visually_hidden">Rechercher une commande</span>
                <span aria-hidden="true"><?= renderUiIcon('search') ?></span>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars((string)($q ?? '')) ?>"
                    placeholder="N° de commande, utilisateur, nom ou e-mail..."
                    autocomplete="off"
                    data-auto-filter
                >
            </label>

            <label class="aorders_status_filter">
                <span class="visually_hidden">Filtrer par statut</span>
                <select name="status" data-auto-filter>
                    <option value="">Tous les statuts</option>
                    <option value="pending_payment" <?= ($status ?? '') === 'pending_payment' ? 'selected' : '' ?>>Paiement en attente</option>
                    <option value="paid" <?= ($status ?? '') === 'paid' ? 'selected' : '' ?>>Payée</option>
                    <option value="partially_refunded" <?= ($status ?? '') === 'partially_refunded' ? 'selected' : '' ?>>Partiellement remboursée</option>
                    <option value="refunded" <?= ($status ?? '') === 'refunded' ? 'selected' : '' ?>>Remboursée</option>
                    <option value="cancelled" <?= ($status ?? '') === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </label>

            <button type="submit">Filtrer</button>
            <?php if (($q ?? '') !== '' || ($status ?? '') !== ''): ?>
                <a href="index.php?controller=admin&amp;action=orders">Effacer</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="aorders_results" aria-label="Commandes trouvées">
        <?php if (empty($orders)): ?>
            <div class="aorders_empty">
                <span aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                <h2>Aucune commande trouvée</h2>
                <p>Modifie la recherche ou le statut sélectionné.</p>
                <a href="index.php?controller=admin&amp;action=orders">Afficher toutes les commandes</a>
            </div>
        <?php else: ?>
            <form
                method="post"
                action="index.php?controller=admin&amp;action=generateSelectedInvoices"
                class="aorders_batch"
                data-orders-batch
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars((string)($q ?? '')) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars((string)($status ?? '')) ?>">
                <input type="hidden" name="page" value="<?= (int)($page ?? 1) ?>">

                <div class="aorders_batchbar">
                    <div>
                        <strong><?= count($orders) ?> commande<?= count($orders) > 1 ? 's' : '' ?> sur cette page</strong>
                        <span data-orders-selection-count aria-live="polite">Aucune sélection</span>
                    </div>
                    <button type="submit" class="aorders_batch_submit" data-orders-batch-submit disabled>
                        Générer les factures
                    </button>
                </div>

                <div class="aorders_table" role="table" aria-label="Liste des commandes">
                    <div class="aorders_table_head" role="row">
                        <span role="columnheader">
                            <?php if ($selectedEligibleCount > 0): ?>
                                <label class="aorders_select_all" title="Sélectionner les commandes facturables">
                                    <input type="checkbox" data-orders-select-all>
                                    <span class="visually_hidden">Tout sélectionner</span>
                                </label>
                            <?php endif; ?>
                        </span>
                        <span role="columnheader">Commande</span>
                        <span role="columnheader">Client</span>
                        <span role="columnheader">Règlement</span>
                        <span role="columnheader">État</span>
                        <span role="columnheader">Actions</span>
                    </div>

                    <div class="aorders_rows" role="rowgroup">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderId = (int)($order['id'] ?? 0);
                            $fullName = trim((string)(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')));
                            $displayName = $fullName !== '' ? $fullName : (string)($order['username'] ?? 'Utilisateur');
                            $email = trim((string)($order['email'] ?? ''));
                            $statusKey = (string)($order['status'] ?? 'pending_payment');
                            $statusConfig = $statusMap[$statusKey] ?? $statusMap['pending_payment'];
                            $invoice = $invoiceMap[$orderId] ?? null;
                            $totalPrice = (float)($order['total_price'] ?? 0);
                            $amountPaid = (float)($order['amount_paid'] ?? 0);
                            $remainingDue = max($totalPrice - $amountPaid, 0);
                            $paymentProgress = $totalPrice > 0
                                ? min(100, max(0, (int)round(($amountPaid / $totalPrice) * 100)))
                                : 0;
                            $canGenerateInvoice = $statusKey === 'paid' && $invoice === null;
                            $createdTimestamp = strtotime((string)($order['created_at'] ?? ''));
                            ?>

                            <article class="aorders_row" role="row">
                                <div class="aorders_cell aorders_check_cell" role="cell">
                                    <?php if ($canGenerateInvoice): ?>
                                        <label title="Sélectionner la commande #<?= $orderId ?>">
                                            <input type="checkbox" name="order_ids[]" value="<?= $orderId ?>" data-orders-selectable>
                                            <span class="visually_hidden">Sélectionner la commande #<?= $orderId ?></span>
                                        </label>
                                    <?php else: ?>
                                        <span class="aorders_check_placeholder" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </div>

                                <div class="aorders_cell aorders_order_cell" role="cell" data-label="Commande">
                                    <a href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= $orderId ?>">#<?= $orderId ?></a>
                                    <time<?= $createdTimestamp ? ' datetime="' . htmlspecialchars(date(DATE_ATOM, $createdTimestamp)) . '"' : '' ?>>
                                        <?= $createdTimestamp ? htmlspecialchars(date('d/m/Y à H:i', $createdTimestamp)) : 'Date indisponible' ?>
                                    </time>
                                    <small><?= (int)($order['items_count'] ?? 0) ?> ligne<?= (int)($order['items_count'] ?? 0) > 1 ? 's' : '' ?></small>
                                </div>

                                <div class="aorders_cell aorders_customer_cell" role="cell" data-label="Client">
                                    <strong><?= htmlspecialchars($displayName) ?></strong>
                                    <span>@<?= htmlspecialchars((string)($order['username'] ?? '—')) ?></span>
                                    <?php if ($email !== ''): ?><small><?= htmlspecialchars($email) ?></small><?php endif; ?>
                                </div>

                                <div class="aorders_cell aorders_payment_cell" role="cell" data-label="Règlement">
                                    <div>
                                        <strong><?= number_format($amountPaid, 2, ',', ' ') ?> €</strong>
                                        <span>sur <?= number_format($totalPrice, 2, ',', ' ') ?> €</span>
                                    </div>
                                    <progress max="100" value="<?= $paymentProgress ?>" aria-label="<?= $paymentProgress ?> % encaissé"><?= $paymentProgress ?> %</progress>
                                    <small class="<?= $remainingDue > 0.009 ? 'has_due' : 'is_settled' ?>">
                                        <?= $remainingDue > 0.009 ? number_format($remainingDue, 2, ',', ' ') . ' € restant' : 'Soldée' ?>
                                    </small>
                                </div>

                                <div class="aorders_cell aorders_state_cell" role="cell" data-label="État">
                                    <span class="aorders_status <?= htmlspecialchars($statusConfig['class']) ?>">
                                        <?= htmlspecialchars($statusConfig['label']) ?>
                                    </span>
                                    <?php if ($invoice !== null): ?>
                                        <span class="aorders_invoice is_ready"><?= htmlspecialchars((string)($invoice['invoice_number'] ?? 'Facture générée')) ?></span>
                                    <?php elseif ($statusKey === 'paid'): ?>
                                        <span class="aorders_invoice">Facture à générer</span>
                                    <?php endif; ?>
                                </div>

                                <div class="aorders_cell aorders_actions" role="cell" data-label="Actions">
                                    <a href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= $orderId ?>">
                                        <?= renderUiIcon('eye') ?>
                                        <span>Détail</span>
                                    </a>
                                    <?php if ($invoice !== null): ?>
                                        <a href="index.php?controller=admin&amp;action=showInvoice&amp;id=<?= (int)($invoice['id'] ?? 0) ?>">
                                            <?= renderUiIcon('invoice') ?>
                                            <span>Facture</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

            <?php
            $paginationCurrentPage = (int)($page ?? 1);
            $paginationTotalPages = (int)($totalPages ?? 1);
            $paginationLabel = 'Pagination des commandes';
            $paginationPageTemplate = $ordersPaginationTemplate;
            require __DIR__ . '/../../partials/admin_pagination.php';
            ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
