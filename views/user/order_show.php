<?php
require_once __DIR__ . '/../partials/header.php';

$csrfToken = getCsrfToken();
$reportWindowHours = 10;
$reportRedirect = 'index.php?controller=user&action=showOrder&id=' . (int)$order['id'] . '#user-order-reporting';

$buildReportWindow = static function (?string $createdAt, int $hours = 10): array {
    $createdAt = trim((string)$createdAt);

    if ($createdAt === '') {
        return [
                'can_report' => false,
                'deadline_label' => null,
                'remaining_label' => null,
        ];
    }

    try {
        $created = new DateTimeImmutable($createdAt);
    } catch (Throwable) {
        return [
                'can_report' => false,
                'deadline_label' => null,
                'remaining_label' => null,
        ];
    }

    $deadline = $created->modify('+' . $hours . ' hours');
    $now = new DateTimeImmutable('now');

    if ($now > $deadline) {
        return [
                'can_report' => false,
                'deadline_label' => $deadline->format('d/m/Y à H:i'),
                'remaining_label' => null,
        ];
    }

    $diff = $now->diff($deadline);
    $remainingParts = [];

    if ($diff->d > 0) {
        $remainingParts[] = $diff->d . ' j';
    }

    $remainingParts[] = $diff->h . ' h';
    $remainingParts[] = $diff->i . ' min';

    return [
            'can_report' => true,
            'deadline_label' => $deadline->format('d/m/Y à H:i'),
            'remaining_label' => trim(implode(' ', $remainingParts)),
    ];
};

$reportInfo = $buildReportWindow($order['created_at'] ?? null, $reportWindowHours);
?>

<main class="main_part user_order_detail_page">
    <section class="user_page_intro">
        <span class="section_kicker">Commande</span>
        <h2>Commande #<?= (int)$order['id'] ?></h2>
        <p>Détail complet de ta commande, de ses lignes et de ses paiements.</p>
    </section>

    <section class="admin_dashboard_section">
        <div class="user_order_top_actions">
            <a class="home_btn home_btn_secondary" href="index.php?controller=user&action=orders">Retour aux commandes</a>
            <a class="home_btn" target="_blank" rel="noopener" href="index.php?controller=user&action=printOrder&id=<?= (int)$order['id'] ?>">Générer / imprimer la facture</a>
        </div>

        <div class="user_order_summary_grid">
            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Statut</span>
                <strong class="dashboard_stat_value dashboard_stat_value_small"><?= htmlspecialchars((string)$order['status']) ?></strong>
                <p>État actuel de la commande.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Total commande</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$order['total_price'], 2, ',', ' ') ?> €</strong>
                <p>Montant total de la commande.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Total payé</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$order['paid_total'], 2, ',', ' ') ?> €</strong>
                <p>Montants capturés sur cette commande.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Net payé</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$order['net_paid_total'], 2, ',', ' ') ?> €</strong>
                <p>Montant conservé après remboursements.</p>
            </article>
        </div>

        <?php if ($reportInfo['can_report']): ?>
            <div class="order_issue_panel" id="user-order-reporting">
                <div class="order_issue_meta">
                    <p class="order_issue_deadline">Signalement possible jusqu’au <?= htmlspecialchars((string)$reportInfo['deadline_label']) ?>.</p>
                    <p class="order_issue_hint">Temps restant : <?= htmlspecialchars((string)$reportInfo['remaining_label']) ?>.</p>
                </div>

                <details class="order_issue_box">
                    <summary class="order_issue_summary">
                        <span class="order_issue_summary_main">
                            <span class="order_issue_summary_icon" aria-hidden="true"><?= renderUiIcon('alert') ?></span>
                            <span>Signaler un souci sur cette commande</span>
                        </span>
                        <span class="order_issue_summary_hint">Formulaire rapide</span>
                    </summary>

                    <form method="post" action="index.php?controller=shop&action=reportAlert" class="order_issue_form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="source_context" value="user_order">
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                        <input type="hidden" name="priority" value="medium">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($reportRedirect) ?>">

                        <fieldset class="order_issue_product_picker" data-alert-item-picker>
                            <legend>Produits concernés</legend>
                            <label class="order_issue_product_option order_issue_product_option_all">
                                <input type="checkbox" name="all_products" value="1" data-alert-select-all>
                                <span>
                                    <strong>Toute la commande</strong>
                                    <small>Sélectionne automatiquement tous les produits.</small>
                                </span>
                            </label>
                            <div class="order_issue_product_list">
                                <?php foreach ((array)$order['items'] as $reportItem): ?>
                                    <?php
                                    $reportVariant = trim((string)($reportItem['display_name'] ?? $reportItem['variant_name'] ?? ''));
                                    $reportLabel = (string)($reportItem['product_name'] ?? 'Produit');
                                    if ($reportVariant !== '' && strcasecmp($reportVariant, 'Standard') !== 0) {
                                        $reportLabel .= ' — ' . $reportVariant;
                                    }
                                    $reportLabel .= ' (×' . (int)($reportItem['quantity'] ?? 1) . ')';
                                    ?>
                                    <label class="order_issue_product_option">
                                        <input type="checkbox" name="order_item_ids[]" value="<?= (int)($reportItem['id'] ?? 0) ?>" data-alert-item>
                                        <span><?= htmlspecialchars($reportLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="order_issue_selection_hint" data-alert-selection-hint>Choisis un, plusieurs ou tous les produits.</p>
                        </fieldset>

                        <label>
                            Type d’alerte
                            <select name="type">
                                <option value="missing_product">Produit absent</option>
                                <option value="stock_mismatch">Stock incohérent</option>
                                <option value="wrong_variant">Mauvaise variante</option>
                                <option value="damaged_product">Produit abîmé</option>
                                <option value="manual_check_required">Contrôle manuel</option>
                            </select>
                        </label>

                        <label>
                            Détail
                            <textarea name="message" placeholder="Décris le souci rencontré sur cette commande."></textarea>
                        </label>

                        <div class="order_issue_actions">
                            <button type="submit" class="order_issue_submit">Envoyer le signalement</button>
                        </div>
                    </form>
                </details>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin_dashboard_section">
        <div class="section_heading">
            <span class="section_kicker">Contenu</span>
            <h3>Lignes de commande</h3>
        </div>

        <div class="user_order_items">
            <?php foreach ($order['items'] as $item): ?>
                <?php
                $isCustomLine = ($item['line_type'] ?? 'product') === 'custom';
                $itemImage = $isCustomLine
                    ? 'product-placeholder.svg'
                    : (($item['variant_image'] ?? '') ?: (($item['product_image'] ?? '') ?: 'php.png'));
                ?>
                <article class="user_order_item_card">
                    <div class="user_order_item_visual">
                        <img src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($itemImage) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                    </div>

                    <div class="user_order_item_content">
                        <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                        <p class="user_order_item_variant"><?= htmlspecialchars($item['display_name']) ?></p>
                        <div class="user_order_item_meta">
                            <p><?= $isCustomLine ? 'Type' : 'Quantité' ?> : <strong><?= $isCustomLine ? 'Montant libre' : (int)$item['quantity'] ?></strong></p>
                            <p>Prix unitaire : <strong><?= number_format((float)$item['unit_price'], 2, ',', ' ') ?> €</strong></p>
                            <p>Total ligne : <strong><?= number_format((float)$item['line_total'], 2, ',', ' ') ?> €</strong></p>
                            <?php if ((float)$item['refunded_amount'] > 0): ?>
                                <p>Remboursé : <strong><?= number_format((float)$item['refunded_amount'], 2, ',', ' ') ?> €</strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <div class="section_heading">
            <span class="section_kicker">Paiements</span>
            <h3>Historique</h3>
        </div>

        <?php if (empty($order['payments'])): ?>
            <div class="empty_state">
                <h3>Aucun paiement</h3>
                <p>Aucun paiement n’est encore enregistré sur cette commande.</p>
            </div>
        <?php else: ?>
            <div class="user_payment_list">
                <?php foreach ($order['payments'] as $payment): ?>
                    <article class="user_payment_card">
                        <div>
                            <h4>Paiement #<?= (int)$payment['id'] ?></h4>
                            <p><?= !empty($payment['payment_date']) ? date('d/m/Y H:i', strtotime($payment['payment_date'])) : '-' ?></p>
                        </div>
                        <div class="user_payment_meta">
                            <p>Montant : <strong><?= number_format((float)$payment['amount_paid'], 2, ',', ' ') ?> €</strong></p>
                            <p>Méthode : <strong><?= htmlspecialchars((string)($payment['method'] ?? '—')) ?></strong></p>
                            <p>Statut : <strong><?= htmlspecialchars((string)($payment['status'] ?? '—')) ?></strong></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
