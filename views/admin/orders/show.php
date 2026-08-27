<?php require_once __DIR__ . '/../../partials/header.php'; ?>
<?php
$displayName = htmlspecialchars(trim((string) (($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? ''))) ?: (string) ($order['username'] ?? 'Utilisateur'));
$email = trim((string) ($order['email'] ?? ''));
$statusKey = (string) ($order['status'] ?? 'pending_payment');
$invoice = $invoice ?? null;

$refundFlow = trim((string) ($_GET['refund_flow'] ?? ''));
$refundItemId = isset($_GET['refund_item_id']) ? (int) $_GET['refund_item_id'] : 0;
$orderHasCatalogLines = false;

foreach ((array)($order['items'] ?? []) as $orderItem) {
    if (($orderItem['line_type'] ?? 'product') === 'product' && !empty($orderItem['variant_id'])) {
        $orderHasCatalogLines = true;
        break;
    }
}

$refundReasonLabels = [
        'refund_full_order' => 'Commande remboursée',
        'refund_partial_item' => 'Ligne remboursée',
        'refund_full_order_restock' => 'Commande remboursée — produit reversé au stock',
        'refund_full_order_destroyed' => 'Commande remboursée — produit consommé / détruit',
        'refund_full_order_consumed' => 'Commande remboursée — produit consommé / détruit',
        'refund_partial_item_restock' => 'Ligne remboursée — produit reversé au stock',
        'refund_partial_item_destroyed' => 'Ligne remboursée — produit consommé / détruit',
        'refund_partial_item_consumed' => 'Ligne remboursée — produit consommé / détruit',
];

$formatRefundReason = static function (?string $reason) use ($refundReasonLabels): string {
    $reason = trim((string) $reason);

    if ($reason === '') {
        return '—';
    }

    return $refundReasonLabels[$reason] ?? $reason;
};

$statusMap = [
        'pending_payment' => ['label' => 'Paiement en attente', 'class' => 'aordshow_badge_pending'],
        'paid' => ['label' => 'Payée', 'class' => 'aordshow_badge_paid'],
        'partially_refunded' => ['label' => 'Partiellement remboursée', 'class' => 'aordshow_badge_warning'],
        'refunded' => ['label' => 'Remboursée', 'class' => 'aordshow_badge_cancelled'],
        'cancelled' => ['label' => 'Annulée / remboursée', 'class' => 'aordshow_badge_cancelled'],
];

$statusConfig = $statusMap[$statusKey] ?? $statusMap['pending_payment'];

$secondaryBadgeLabel = 'Non remboursable';
$secondaryBadgeClass = 'aordshow_badge_muted';

if (!empty($order['can_cancel'])) {
    $secondaryBadgeLabel = 'Annulable';
    $secondaryBadgeClass = 'aordshow_badge_warning';
} elseif (!empty($order['is_refundable']) && (float) ($order['remaining_refundable_total'] ?? 0) > 0) {
    $secondaryBadgeLabel = 'Remboursable';
    $secondaryBadgeClass = 'aordshow_badge_success';
} elseif (($order['status'] ?? '') === 'refunded') {
    $secondaryBadgeLabel = 'Remboursée';
    $secondaryBadgeClass = 'aordshow_badge_cancelled';
} elseif (($order['status'] ?? '') === 'partially_refunded') {
    $secondaryBadgeLabel = 'Partiellement remboursée';
    $secondaryBadgeClass = 'aordshow_badge_warning';
} elseif (($order['status'] ?? '') === 'cancelled') {
    $secondaryBadgeLabel = 'Annulée';
    $secondaryBadgeClass = 'aordshow_badge_cancelled';
}

$showFullRefundChoice = $refundFlow === 'full'
        && !empty($order['is_refundable'])
        && !empty($order['can_refund_full'])
        && (float) ($order['remaining_refundable_total'] ?? 0) > 0;
?>

<main class="main_part admin_dashboard_page aordshow_page">
    <section class="admin_dashboard_intro aordshow_intro">
        <span class="section_kicker">Commande</span>
        <h2>Commande #<?= (int) ($order['id'] ?? 0) ?></h2>
        <p>
            Consulte le détail des lignes facturées, les paiements et les actions disponibles.
        </p>
    </section>

    <section class="admin_dashboard_section aordshow_header_section">
        <article class="aordshow_header_card">
            <div class="aordshow_header_top">
                <div class="aordshow_identity">
                    <div class="aordshow_title_row">
                        <div>
                            <span class="aordshow_overline">Fiche commande</span>
                            <h3><?= $displayName ?></h3>
                        </div>

                        <div class="aordshow_badges">
                            <span class="aordshow_badge <?= htmlspecialchars($statusConfig['class']) ?>">
                                <?= htmlspecialchars($statusConfig['label']) ?>
                            </span>
                            <span class="aordshow_badge <?= htmlspecialchars($secondaryBadgeClass) ?>">
                                <?= htmlspecialchars($secondaryBadgeLabel) ?>
                            </span>
                        </div>
                    </div>

                    <div class="aordshow_identity_meta">
                        <?php if ($email !== ''): ?>
                            <span><?= htmlspecialchars($email) ?></span>
                        <?php endif; ?>
                        <span>Utilisateur : <strong><?= htmlspecialchars((string) ($order['username'] ?? '—')) ?></strong></span>
                        <span>Créée le <strong><?= !empty($order['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $order['created_at']))) : '-' ?></strong></span>
                        <span>Remboursable jusqu’au <strong><?= !empty($order['refund_deadline_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $order['refund_deadline_at']))) : '-' ?></strong></span>
                        <span>Facture : <strong><?= $invoice ? htmlspecialchars((string) ($invoice['invoice_number'] ?? 'Générée')) : 'Non générée' ?></strong></span>
                    </div>
                </div>

                <div id="order-header-actions" class="aordshow_header_actions">
                    <a class="aord_btn aord_btn_secondary" href="index.php?controller=admin&amp;action=orders">
                        Retour aux commandes
                    </a>

                    <?php if ($invoice): ?>
                        <a class="aord_btn aord_btn_secondary" href="index.php?controller=admin&amp;action=showInvoice&amp;id=<?= (int) ($invoice['id'] ?? 0) ?>">
                            Voir la facture
                        </a>
                    <?php elseif ($statusKey === 'paid'): ?>
                        <form method="POST" action="index.php?controller=admin&amp;action=generateInvoice" class="aordshow_action_form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                            <button type="submit" class="aord_btn aord_btn_secondary aord_btn_full">
                                Générer la facture
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (currentUserCan('orders.refund') && !empty($order['can_cancel'])): ?>
                        <form method="POST" action="index.php?controller=admin&amp;action=cancelOrder" class="aordshow_action_form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                            <button type="submit" class="aord_btn aord_btn_primary aord_btn_full">
                                Annuler la commande
                            </button>
                        </form>
                    <?php elseif (currentUserCan('orders.refund') && !empty($order['can_refund_full']) && (float) ($order['remaining_refundable_total'] ?? 0) > 0): ?>
                        <?php if ($showFullRefundChoice): ?>
                            <div id="refund-full-choice" class="aordshow_refund_choice_box">
                                <div class="aordshow_refund_choice_head">
                                    <span class="aorditem_refund_overline">Remboursement total</span>
                                    <h4><?= $orderHasCatalogLines ? 'Sort des produits remboursés' : 'Confirmation du remboursement' ?></h4>
                                    <p>
                                        <?= $orderHasCatalogLines
                                                ? 'Choisis ce qu’il advient des produits au moment de confirmer le remboursement.'
                                                : 'Cette commande ne contient que des montants libres : aucun stock ne sera modifié.' ?>
                                    </p>
                                </div>

                                <form method="POST" action="index.php?controller=admin&amp;action=refundOrder" class="aordshow_refund_choice_form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">

                                    <?php if ($orderHasCatalogLines): ?>
                                        <div class="aordshow_refund_choice_fields">
                                            <label for="refund_stock_action_full">Sort des produits remboursés</label>
                                            <select id="refund_stock_action_full" name="refund_stock_action" required>
                                                <option value="restock">Produit reversé au stock</option>
                                                <option value="consumed">Produit consommé / détruit</option>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="refund_stock_action" value="consumed">
                                    <?php endif; ?>

                                    <div class="aordshow_refund_choice_actions">
                                        <button type="submit" class="aord_btn aord_btn_primary aord_btn_full">
                                            Confirmer le remboursement
                                        </button>
                                        <a class="aord_btn aord_btn_secondary aord_btn_full" href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= (int) ($order['id'] ?? 0) ?>#order-header-actions">
                                            Annuler
                                        </a>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <a class="aord_btn aord_btn_primary aord_btn_full" href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= (int) ($order['id'] ?? 0) ?>&amp;refund_flow=full#order-header-actions">
                                Tout rembourser
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="aordshow_kpi_grid">
                <article class="aordshow_kpi_card">
                    <span>Total commande</span>
                    <strong><?= number_format((float) ($order['total_price'] ?? 0), 2, ',', ' ') ?> €</strong>
                </article>

                <article class="aordshow_kpi_card aordshow_kpi_card_success">
                    <span>Total encaissé</span>
                    <strong><?= number_format((float) ($order['captured_paid_total'] ?? 0), 2, ',', ' ') ?> €</strong>
                </article>

                <article class="aordshow_kpi_card aordshow_kpi_card_warning">
                    <span>Total remboursé</span>
                    <strong><?= number_format((float) ($order['refunded_total'] ?? 0), 2, ',', ' ') ?> €</strong>
                </article>

                <article class="aordshow_kpi_card">
                    <span>Net encaissé</span>
                    <strong><?= number_format((float) ($order['net_paid_total'] ?? 0), 2, ',', ' ') ?> €</strong>
                </article>

                <article class="aordshow_kpi_card">
                    <span>Reste remboursable</span>
                    <strong><?= number_format((float) ($order['remaining_refundable_total'] ?? 0), 2, ',', ' ') ?> €</strong>
                </article>

                <article class="aordshow_kpi_card">
                    <span>Lignes facturées</span>
                    <strong><?= count($order['items'] ?? []) ?></strong>
                </article>
            </div>
        </article>
    </section>

    <section class="admin_dashboard_section">
        <div class="section_heading aord_section_heading">
            <span class="section_kicker">Facturation</span>
            <h3>Contenu de la commande</h3>
            <p>Visualise les lignes commandées et gère les actions disponibles selon l’état de paiement.</p>
        </div>

        <?php if (empty($order['items'])): ?>
            <div class="empty_state aord_empty_state">
                <h3>Aucune ligne de commande</h3>
                <p>Cette commande ne contient aucune ligne facturée.</p>
            </div>
        <?php else: ?>
            <div class="aorditem_list">
                <?php foreach ($order['items'] as $item): ?>
                    <?php
                    $isCustomLine = ($item['line_type'] ?? 'product') === 'custom';
                    $itemImage = $isCustomLine
                        ? 'product-placeholder.svg'
                        : (($item['variant_image'] ?? '') ?: (($item['product_image'] ?? '') ?: 'php.png'));
                    $itemRemainingQuantity = (int) ($item['remaining_quantity'] ?? 0);
                    $itemRefundedAmount = (float) ($item['refunded_amount'] ?? 0);
                    $itemDisplayName = trim((string) ($item['display_name'] ?? ''));

                    $itemActionLabel = 'Aucune action';
                    $itemActionClass = 'aorditem_badge_muted';

                    if (!empty($item['fully_refunded'])) {
                        $itemActionLabel = 'Totalement remboursé';
                        $itemActionClass = 'aorditem_badge_cancelled';
                    } elseif (!empty($order['can_cancel'])) {
                        $itemActionLabel = 'Annulable';
                        $itemActionClass = 'aorditem_badge_warning';
                    } elseif (!empty($order['is_refundable']) && $itemRemainingQuantity > 0) {
                        $itemActionLabel = 'Remboursable';
                        $itemActionClass = 'aorditem_badge_success';
                    }
                    ?>

                    <article class="aorditem_card">
                        <div class="aorditem_main">
                            <div class="aorditem_visual_block">
                                <div class="aorditem_thumb">
                                    <img
                                            src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($itemImage) ?>"
                                            alt="<?= htmlspecialchars((string) ($item['display_name'] ?? $item['product_name'] ?? 'Produit')) ?>"
                                    >
                                </div>

                                <div class="aorditem_identity">
                                    <div class="aorditem_title_row">
                                        <div>
                                            <h4><?= htmlspecialchars((string) ($item['product_name'] ?? 'Produit')) ?></h4>
                                            <?php if ($itemDisplayName !== ''): ?>
                                                <p class="aorditem_variant_name"><?= htmlspecialchars($itemDisplayName) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <span class="aorditem_badge <?= htmlspecialchars($itemActionClass) ?>">
                                            <?= htmlspecialchars($itemActionLabel) ?>
                                        </span>
                                    </div>

                                    <div class="aorditem_meta_grid">
                                        <div class="aorditem_meta_card">
                                            <span>Qté commandée</span>
                                            <strong><?= (int) ($item['quantity'] ?? 0) ?></strong>
                                        </div>

                                        <div class="aorditem_meta_card">
                                            <span>Qté restante</span>
                                            <strong><?= $itemRemainingQuantity ?></strong>
                                        </div>

                                        <div class="aorditem_meta_card">
                                            <span>Prix unitaire</span>
                                            <strong><?= number_format((float) ($item['unit_price'] ?? 0), 2, ',', ' ') ?> €</strong>
                                        </div>

                                        <div class="aorditem_meta_card">
                                            <span>Total ligne</span>
                                            <strong><?= number_format((float) ($item['line_total'] ?? 0), 2, ',', ' ') ?> €</strong>
                                        </div>

                                        <div class="aorditem_meta_card aorditem_meta_card_alt">
                                            <span>Déjà remboursé</span>
                                            <strong><?= number_format($itemRefundedAmount, 2, ',', ' ') ?> €</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="aorditem_side">
                            <?php
                            $showItemRefundChoice = $refundFlow === 'item'
                                    && $refundItemId === (int) ($item['id'] ?? 0)
                                    && !empty($order['is_refundable'])
                                    && $itemRemainingQuantity > 0
                                    && (float) ($order['remaining_refundable_total'] ?? 0) > 0;
                            ?>

                            <?php if (currentUserCan('orders.refund') && !empty($order['is_refundable']) && $itemRemainingQuantity > 0 && (float) ($order['remaining_refundable_total'] ?? 0) > 0): ?>
                                <div id="refund-item-<?= (int) ($item['id'] ?? 0) ?>" class="aorditem_refund_box">
                                    <div class="aorditem_refund_head">
                                        <span class="aorditem_refund_overline">Action</span>
                                        <h5>Remboursement partiel</h5>
                                        <p>
                                            <?= $isCustomLine
                                                    ? 'Confirme le remboursement de cette ligne libre. Aucun stock ne sera modifié.'
                                                    : 'Choisis la quantité, puis le sort du produit au moment de confirmer.' ?>
                                        </p>
                                    </div>

                                    <?php if ($showItemRefundChoice): ?>
                                        <form
                                                method="POST"
                                                action="index.php?controller=admin&amp;action=refundOrderItem"
                                                class="aorditem_refund_form"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                            <input type="hidden" name="order_item_id" value="<?= (int) ($item['id'] ?? 0) ?>">

                                            <div class="aorditem_refund_fields">
                                                <label for="refund_qty_<?= (int) ($item['id'] ?? 0) ?>">Quantité à rembourser</label>
                                                <input
                                                        type="number"
                                                        id="refund_qty_<?= (int) ($item['id'] ?? 0) ?>"
                                                        name="quantity"
                                                        min="1"
                                                        max="<?= $itemRemainingQuantity ?>"
                                                        value="1"
                                                        required
                                                >
                                            </div>

                                            <?php if ($isCustomLine): ?>
                                                <input type="hidden" name="refund_stock_action" value="consumed">
                                            <?php else: ?>
                                                <div class="aorditem_refund_fields">
                                                    <label for="refund_stock_action_<?= (int) ($item['id'] ?? 0) ?>">Sort des produits remboursés</label>
                                                    <select
                                                            id="refund_stock_action_<?= (int) ($item['id'] ?? 0) ?>"
                                                            name="refund_stock_action"
                                                            required
                                                    >
                                                        <option value="restock">Produit reversé au stock</option>
                                                        <option value="consumed">Produit consommé / détruit</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <div class="aorditem_refund_actions">
                                                <button type="submit" class="aord_btn aord_btn_primary aord_btn_full">
                                                    Confirmer le remboursement
                                                </button>
                                                <a class="aord_btn aord_btn_secondary aord_btn_full" href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= (int) ($order['id'] ?? 0) ?>#refund-item-<?= (int) ($item['id'] ?? 0) ?>">
                                                    Annuler
                                                </a>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <a
                                                class="aord_btn aord_btn_primary aord_btn_full"
                                                href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= (int) ($order['id'] ?? 0) ?>&amp;refund_flow=item&amp;refund_item_id=<?= (int) ($item['id'] ?? 0) ?>#refund-item-<?= (int) ($item['id'] ?? 0) ?>"
                                        >
                                            Rembourser cette ligne
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="aorditem_side_note">
                                    <span class="aorditem_refund_overline">État</span>
                                    <p>Aucune action complémentaire disponible pour cette ligne actuellement.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin_dashboard_section">
        <div class="section_heading aord_section_heading">
            <span class="section_kicker">Historique</span>
            <h3>Remboursements effectués</h3>
            <p>Journal des remboursements déjà passés sur cette commande.</p>
        </div>

        <?php if (empty($order['refunds'])): ?>
            <div class="empty_state aord_empty_state">
                <h3>Aucun remboursement</h3>
                <p>Cette commande n’a encore fait l’objet d’aucun remboursement.</p>
            </div>
        <?php else: ?>
            <div class="aordhist_list">
                <?php foreach ($order['refunds'] as $refund): ?>
                    <article class="aordhist_card">
                        <div class="aordhist_amount_block">
                            <span class="aordhist_label">Montant</span>
                            <strong><?= number_format((float) ($refund['amount'] ?? 0), 2, ',', ' ') ?> €</strong>
                        </div>

                        <div class="aordhist_body">
                            <h4>
                                <?= htmlspecialchars((string) ($refund['product_name'] ?? 'Commande')) ?>
                                <?php if (!empty($refund['flavor']) || !empty($refund['variant_name'])): ?>
                                    — <?= htmlspecialchars((string) (($refund['flavor'] ?? '') ?: ($refund['variant_name'] ?? ''))) ?>
                                <?php endif; ?>
                            </h4>

                            <div class="aordhist_meta_grid">
                                <div class="aordhist_meta_item">
                                    <span>Quantité</span>
                                    <strong><?= (int) ($refund['quantity_refunded'] ?? 0) ?></strong>
                                </div>

                                <div class="aordhist_meta_item">
                                    <span>Motif</span>
                                    <strong><?= htmlspecialchars($formatRefundReason($refund['reason'] ?? null)) ?></strong>
                                </div>

                                <div class="aordhist_meta_item">
                                    <span>Par</span>
                                    <strong><?= htmlspecialchars((string) ($refund['admin_username'] ?? 'admin')) ?></strong>
                                </div>

                                <div class="aordhist_meta_item">
                                    <span>Le</span>
                                    <strong><?= !empty($refund['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $refund['created_at']))) : '-' ?></strong>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
