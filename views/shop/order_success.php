<?php
require_once __DIR__ . '/../partials/header.php';

$csrfToken = $csrf_token ?? getCsrfToken();
$reportWindowHours = 10;
$reportRedirect = 'index.php?controller=shop&action=orderSuccess&id=' . (int)$order['id'] . '#order-success-reporting';

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

<main class="main_part order_success_page">
    <section class="order_success_intro">
        <span class="section_kicker">Commande</span>
        <h2>Commande validée</h2>
        <p>
            Ta commande a bien été créée et est actuellement en attente de paiement.
        </p>
    </section>

    <section class="order_success_layout">
        <div class="order_success_card">
            <h3>Commande #<?= (int)$order['id'] ?></h3>

            <div class="order_success_meta">
                <p>Statut : <strong><?= htmlspecialchars($order['status']) ?></strong></p>
                <p>Total : <strong><?= number_format((float)$order['total_price'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong></p>
                <p>Date : <strong><?= htmlspecialchars($order['created_at']) ?></strong></p>
            </div>

            <div class="order_success_items">
                <?php foreach ($order['items'] as $item): ?>
                    <?php
                    $variantLabel = trim((string)($item['display_variant'] ?? ''));

                    if ($variantLabel === '') {
                        $variantName = trim((string)($item['variant_name'] ?? ''));
                        $variantFlavor = trim((string)($item['flavor'] ?? ''));

                        if ($variantName !== '' && $variantFlavor !== '') {
                            $variantLabel = $variantName . ' - ' . $variantFlavor;
                        } elseif ($variantName !== '') {
                            $variantLabel = $variantName;
                        } elseif ($variantFlavor !== '') {
                            $variantLabel = $variantFlavor;
                        } else {
                            $variantLabel = 'Standard';
                        }
                    }
                    ?>

                    <article class="order_success_item order_success_item_stack">
                        <div class="order_success_item_top">
                            <div>
                                <p class="order_item_name"><?= htmlspecialchars($item['product_name']) ?></p>
                                <p class="order_item_variant">Variante : <?= htmlspecialchars($variantLabel) ?></p>
                            </div>
                            <div class="order_item_totals">
                                <span>x<?= (int)$item['quantity'] ?></span>
                                <strong><?= number_format((float)$item['line_total'], 2, ',', ' ') ?> €</strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($reportInfo['can_report']): ?>
                <div class="order_issue_panel" id="order-success-reporting">
                    <div class="order_issue_meta">
                        <p class="order_issue_deadline">Signalement possible jusqu’au <?= htmlspecialchars((string)$reportInfo['deadline_label']) ?>.</p>
                        <p class="order_issue_hint">Temps restant : <?= htmlspecialchars((string)$reportInfo['remaining_label']) ?>.</p>
                    </div>

                    <details class="order_issue_box">
                        <summary class="order_issue_summary">
                            <span class="order_issue_summary_main">
                                <span class="order_issue_summary_icon" aria-hidden="true"><?= renderUiIcon('alert') ?></span>
                                <span>Signaler un souci</span>
                            </span>
                            <span class="order_issue_summary_hint">Bouton discret</span>
                        </summary>

                        <form method="post" action="index.php?controller=shop&action=reportAlert" class="order_issue_form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="source_context" value="order_success">
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
                                        $reportVariant = trim((string)($reportItem['variant_name'] ?? ''));
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
                                <textarea name="message" placeholder="Décris rapidement le souci rencontré sur cette commande."></textarea>
                            </label>

                            <div class="order_issue_actions">
                                <button type="submit" class="order_issue_submit">Envoyer le signalement</button>
                            </div>
                        </form>
                    </details>
                </div>
            <?php endif; ?>

            <div class="order_success_actions">
                <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=index">Retour boutique</a>
                <a class="home_btn home_btn_primary" href="index.php?controller=user&action=dashboard">Voir mon espace</a>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
