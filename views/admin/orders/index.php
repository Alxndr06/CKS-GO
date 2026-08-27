<?php
require_once __DIR__ . '/../../partials/header.php';

$totalOrdersCount = (int) ($totalOrders ?? count($orders ?? []));
$invoiceMap = is_array($invoiceMap ?? null) ? $invoiceMap : [];
$selectedEligibleCount = 0;

$summaryTotalAmount = isset($totalAmount) ? (float) $totalAmount : 0.0;
$summaryTotalPaid = isset($totalPaid) ? (float) $totalPaid : 0.0;
$summaryTotalRemaining = isset($totalRemaining) ? (float) $totalRemaining : 0.0;

if (!isset($totalAmount, $totalPaid, $totalRemaining)) {
    foreach (($orders ?? []) as $orderSummary) {
        $lineTotal = (float) ($orderSummary['total_price'] ?? 0);
        $linePaid = (float) ($orderSummary['amount_paid'] ?? 0);
        $lineRemaining = max($lineTotal - $linePaid, 0);

        $summaryTotalAmount += $lineTotal;
        $summaryTotalPaid += $linePaid;
        $summaryTotalRemaining += $lineRemaining;
    }
}

$statusMap = [
        'pending_payment' => ['label' => 'Paiement en attente', 'class' => 'aord_badge_pending'],
        'paid' => ['label' => 'Payée', 'class' => 'aord_badge_paid'],
        'partially_refunded' => ['label' => 'Partiellement remboursée', 'class' => 'aord_badge_pending'],
        'refunded' => ['label' => 'Remboursée', 'class' => 'aord_badge_cancelled'],
        'cancelled' => ['label' => 'Annulée', 'class' => 'aord_badge_cancelled'],
];

$ordersPaginationTemplate = 'index.php?' . http_build_query([
                'controller' => 'admin',
                'action' => 'orders',
                'page' => '__PAGE__',
                'q' => (string) ($q ?? ''),
                'status' => (string) ($status ?? ''),
        ]);
?>

<main class="main_part admin_dashboard_page admin_page_pro admin_orders_page_pro">
    <section class="management_module_header">
        <div class="management_module_header_copy">
            <span class="section_kicker">Commandes</span>
        <h2>Liste des commandes</h2>
        <p>
            Recherche une commande, filtre par statut et ouvre sa fiche détaillée ou sa facture.
            </p>
        </div>
    </section>

    <section class="comms_filter_bar aord_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form" data-auto-filter-form>
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="orders">

            <label class="comms_search_field">
                <span>Rechercher</span>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars((string) ($q ?? '')) ?>"
                    placeholder="Commande #, pseudo, nom, prénom, email..."
                    aria-label="Rechercher une commande"
                    autocomplete="off"
                    data-auto-filter
                >
            </label>

            <label>
                <span>Statut</span>
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

        <span class="comms_result_count">
            <?= $totalOrdersCount ?> commande<?= $totalOrdersCount > 1 ? 's' : '' ?>
        </span>
    </section>

    <section class="aord_summary_strip" aria-label="Synthèse financière des commandes affichées">
        <dl class="aord_summary_grid">
            <div class="aord_summary_card">
                <dt class="aord_summary_label">Montant total</dt>
                <dd class="aord_summary_value"><?= number_format($summaryTotalAmount, 2, ',', ' ') ?> €</dd>
            </div>

            <div class="aord_summary_card aord_summary_card_success">
                <dt class="aord_summary_label">Déjà encaissé</dt>
                <dd class="aord_summary_value"><?= number_format($summaryTotalPaid, 2, ',', ' ') ?> €</dd>
            </div>

            <div class="aord_summary_card aord_summary_card_warning">
                <dt class="aord_summary_label">Reste dû</dt>
                <dd class="aord_summary_value"><?= number_format($summaryTotalRemaining, 2, ',', ' ') ?> €</dd>
            </div>
        </dl>
    </section>

    <section class="admin_dashboard_section aord_list_section" aria-label="Commandes trouvées">
        <?php if (empty($orders)): ?>
            <div class="empty_state aord_empty_state">
                <h3>Aucune commande trouvée</h3>
                <p>Essaie une autre recherche ou un autre statut.</p>
            </div>
        <?php else: ?>
            <?php foreach (($orders ?? []) as $orderSummary): ?>
                <?php
                $summaryOrderId = (int) ($orderSummary['id'] ?? 0);
                $summaryStatusKey = (string) ($orderSummary['status'] ?? 'pending_payment');
                $summaryInvoice = $invoiceMap[$summaryOrderId] ?? null;
                if ($summaryStatusKey === 'paid' && $summaryInvoice === null) {
                    $selectedEligibleCount++;
                }
                ?>
            <?php endforeach; ?>

            <form method="POST" action="index.php?controller=admin&amp;action=generateSelectedInvoices" class="aord_batch_form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars((string) ($q ?? '')) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars((string) ($status ?? '')) ?>">
                <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">

                <div class="aord_batch_header">
                    <div>
                        <h3>Commandes</h3>
                        <span><?= count($orders) ?> affichée<?= count($orders) > 1 ? 's' : '' ?> sur cette page</span>
                    </div>

                    <div class="admin_toolbar_actions_pro aord_batch_actions">
                        <button type="submit" class="admin_toolbar_link_pro admin_toolbar_link_primary_pro"<?= $selectedEligibleCount > 0 ? '' : ' disabled' ?>>
                            Générer les factures sélectionnées
                        </button>
                        <a class="admin_toolbar_link_pro admin_toolbar_link_soft_pro" href="index.php?controller=admin&amp;action=orders">
                            Réinitialiser la vue
                        </a>
                    </div>
                </div>

                <div class="aord_list">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderId = (int) ($order['id'] ?? 0);
                        $fullName = trim((string) (($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')));
                        $displayName = $fullName !== '' ? $fullName : (string) ($order['username'] ?? 'Utilisateur');
                        $email = trim((string) ($order['email'] ?? ''));
                        $statusKey = (string) ($order['status'] ?? 'pending_payment');
                        $statusConfig = $statusMap[$statusKey] ?? $statusMap['pending_payment'];
                        $invoice = $invoiceMap[$orderId] ?? null;

                        $totalPrice = (float) ($order['total_price'] ?? 0);
                        $amountPaid = (float) ($order['amount_paid'] ?? 0);
                        $remainingDue = max($totalPrice - $amountPaid, 0);
                        $canGenerateInvoice = $statusKey === 'paid' && $invoice === null;
                        ?>

                        <article class="aord_card">
                            <div class="aord_card_main">
                                <div class="aord_card_head">
                                    <div class="aord_card_identity">
                                        <div class="aord_card_title_row">
                                            <h4>
                                                <a href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= $orderId ?>">
                                                    Commande #<?= $orderId ?>
                                                </a>
                                            </h4>

                                            <span class="aord_badge <?= htmlspecialchars($statusConfig['class']) ?>">
                                                <?= htmlspecialchars($statusConfig['label']) ?>
                                            </span>
                                        </div>

                                        <p class="aord_card_customer"><?= htmlspecialchars($displayName) ?></p>

                                        <div class="aord_identity_meta">
                                            <?php if ($email !== ''): ?>
                                                <span><?= htmlspecialchars($email) ?></span>
                                            <?php endif; ?>
                                            <span>Créée le <strong><?= !empty($order['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $order['created_at']))) : '-' ?></strong></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="aord_metrics_grid">
                                    <div class="aord_metric_card">
                                        <span>Total</span>
                                        <strong><?= number_format($totalPrice, 2, ',', ' ') ?> €</strong>
                                    </div>

                                    <div class="aord_metric_card aord_metric_card_success">
                                        <span>Payé</span>
                                        <strong><?= number_format($amountPaid, 2, ',', ' ') ?> €</strong>
                                    </div>

                                    <div class="aord_metric_card aord_metric_card_warning">
                                        <span>Reste dû</span>
                                        <strong><?= number_format($remainingDue, 2, ',', ' ') ?> €</strong>
                                    </div>

                                    <div class="aord_metric_card">
                                        <span>Lignes</span>
                                        <strong><?= (int) ($order['items_count'] ?? 0) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="aord_card_side">
                                <div class="aord_side_stack">
                                    <div class="aord_side_row">
                                        <span>Utilisateur</span>
                                        <strong><?= htmlspecialchars((string) ($order['username'] ?? '—')) ?></strong>
                                    </div>

                                    <div class="aord_side_row">
                                        <span>Facture</span>
                                        <strong>
                                            <?php if ($invoice !== null): ?>
                                                <?= htmlspecialchars((string) ($invoice['invoice_number'] ?? 'Générée')) ?>
                                            <?php elseif ($statusKey === 'paid'): ?>
                                                Non générée
                                            <?php else: ?>
                                                Indisponible
                                            <?php endif; ?>
                                        </strong>
                                    </div>

                                    <?php if ($canGenerateInvoice): ?>
                                        <div class="aord_side_row">
                                            <label class="aord_batch_check">
                                                <input type="checkbox" name="order_ids[]" value="<?= $orderId ?>">
                                                <span>Sélectionner pour le lot</span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <a
                                        class="aord_btn aord_btn_secondary aord_btn_full"
                                        href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= $orderId ?>"
                                >
                                    Voir le détail
                                </a>

                                <?php if ($invoice !== null): ?>
                                    <a
                                            class="aord_btn aord_btn_secondary aord_btn_full"
                                            href="index.php?controller=admin&amp;action=showInvoice&amp;id=<?= (int) ($invoice['id'] ?? 0) ?>"
                                    >
                                        Voir la facture
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>

            <?php
            $paginationCurrentPage = (int) ($page ?? 1);
            $paginationTotalPages = (int) ($totalPages ?? 1);
            $paginationLabel = 'Pagination des commandes';
            $paginationPageTemplate = $ordersPaginationTemplate;
            require __DIR__ . '/../../partials/admin_pagination.php';
            ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
