<?php
$pageStylesheets = array_merge($pageStylesheets ?? [], ['user-orders.css']);
require_once __DIR__ . '/../partials/header.php';

$csrfToken = getCsrfToken();
$reportWindowHours = 10;
$currentListUrl = 'index.php?controller=user&action=orders&q=' . urlencode((string)($q ?? '')) . '&page=' . max(1, (int)($page ?? 1));
$totalOrdersCount = (int)($totalOrders ?? count($orders ?? []));

$orderStatusMap = [
        'pending_payment' => ['label' => 'Paiement en attente', 'class' => 'is_pending'],
        'paid' => ['label' => 'Payée', 'class' => 'is_paid'],
        'partially_refunded' => ['label' => 'Partiellement remboursée', 'class' => 'is_refunded'],
        'refunded' => ['label' => 'Remboursée', 'class' => 'is_refunded'],
        'cancelled' => ['label' => 'Annulée', 'class' => 'is_cancelled'],
];

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
?>

<main class="main_part user_orders_page user_orders_page_redesign">
    <section class="uorders_hero">
        <div class="uorders_hero_icon" aria-hidden="true"><?= renderUiIcon('orders') ?></div>
        <div class="uorders_hero_copy">
            <span class="section_kicker">Mon historique</span>
            <h1>Mes commandes</h1>
            <p>Retrouve rapidement tes achats, leur règlement et les produits associés.</p>
        </div>
        <div class="uorders_hero_actions">
            <span class="uorders_total_badge">
                <strong><?= $totalOrdersCount ?></strong>
                commande<?= $totalOrdersCount > 1 ? 's' : '' ?>
            </span>
            <a class="uorders_shop_link" href="index.php?controller=shop&action=index">
                <?= renderUiIcon('shop') ?>
                Retour à la boutique
            </a>
        </div>
    </section>

    <section class="uorders_toolbar" aria-label="Rechercher dans les commandes">
        <form method="get" action="index.php" class="uorders_search_form">
            <input type="hidden" name="controller" value="user">
            <input type="hidden" name="action" value="orders">

            <label class="uorders_search_field">
                <span class="visually_hidden">Rechercher une commande</span>
                <span class="uorders_search_icon" aria-hidden="true"><?= renderUiIcon('search') ?></span>
                <input
                        type="search"
                        name="q"
                        value="<?= htmlspecialchars((string)($q ?? '')) ?>"
                        placeholder="Numéro, statut ou date..."
                        autocomplete="off"
                >
            </label>
            <button type="submit">Rechercher</button>
            <?php if (($q ?? '') !== ''): ?>
                <a href="index.php?controller=user&action=orders">Effacer</a>
            <?php endif; ?>
        </form>

        <span class="uorders_result_count">
            <?= count($orders ?? []) ?> affichée<?= count($orders ?? []) > 1 ? 's' : '' ?>
            <?php if ($totalOrdersCount > count($orders ?? [])): ?> sur <?= $totalOrdersCount ?><?php endif; ?>
        </span>
    </section>

    <section class="uorders_results" aria-label="Historique des commandes">
        <?php if (empty($orders)): ?>
            <div class="uorders_empty_state">
                <span aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                <h2>Aucune commande trouvée</h2>
                <p><?= ($q ?? '') !== '' ? 'Essaie avec un autre numéro, statut ou une autre date.' : 'Tes prochaines commandes apparaîtront ici.' ?></p>
                <a href="index.php?controller=shop&action=index">Découvrir la boutique</a>
            </div>
        <?php else: ?>
            <div class="uorders_list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $orderId = (int)($order['id'] ?? 0);
                    $statusKey = strtolower(trim((string)($order['status'] ?? 'pending_payment')));
                    $statusConfig = $orderStatusMap[$statusKey] ?? [
                            'label' => ucfirst(str_replace('_', ' ', $statusKey)),
                            'class' => 'is_pending',
                    ];

                    $createdTimestamp = strtotime((string)($order['created_at'] ?? ''));
                    $createdLabel = $createdTimestamp ? date('d/m/Y à H:i', $createdTimestamp) : 'Date indisponible';
                    $createdIso = $createdTimestamp ? date(DATE_ATOM, $createdTimestamp) : '';

                    $totalPrice = (float)($order['total_price'] ?? 0);
                    $paidTotal = (float)($order['paid_total'] ?? 0);
                    $refundedTotal = (float)($order['refunded_total'] ?? 0);
                    $netPaidTotal = (float)($order['net_paid_total'] ?? max($paidTotal - $refundedTotal, 0));
                    $paymentProgress = $totalPrice > 0
                            ? min(100, max(0, (int)round(($paidTotal / $totalPrice) * 100)))
                            : 0;

                    $reportItems = array_values((array)($order['report_items'] ?? []));
                    $previewItems = array_slice($reportItems, 0, 3);
                    $remainingPreviewCount = max(0, count($reportItems) - count($previewItems));
                    $itemQuantityTotal = array_sum(array_map(
                            static fn(array $item): int => max(0, (int)($item['quantity'] ?? 0)),
                            $reportItems
                    ));

                    $reportInfo = $buildReportWindow($order['created_at'] ?? null, $reportWindowHours);
                    $reportBoxId = 'user-order-report-' . $orderId;
                    ?>

                    <article class="uorder_card <?= htmlspecialchars($statusConfig['class']) ?>" id="commande-<?= $orderId ?>">
                        <header class="uorder_card_header">
                            <div class="uorder_identity">
                                <span class="uorder_identity_icon" aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                                <div>
                                    <span>Commande</span>
                                    <h2>#<?= $orderId ?></h2>
                                    <time<?= $createdIso !== '' ? ' datetime="' . htmlspecialchars($createdIso) . '"' : '' ?>>
                                        <?= htmlspecialchars($createdLabel) ?>
                                    </time>
                                </div>
                            </div>

                            <span class="uorder_status <?= htmlspecialchars($statusConfig['class']) ?>">
                                <?= htmlspecialchars($statusConfig['label']) ?>
                            </span>

                            <div class="uorder_total">
                                <span>Total</span>
                                <strong><?= number_format($totalPrice, 2, ',', ' ') ?> €</strong>
                            </div>

                            <a class="uorder_detail_link" href="index.php?controller=user&action=showOrder&id=<?= $orderId ?>">
                                <?= renderUiIcon('eye') ?>
                                Voir le détail
                            </a>
                        </header>

                        <div class="uorder_card_body">
                            <section class="uorder_products" aria-label="Produits de la commande">
                                <div class="uorder_section_heading">
                                    <h3>Contenu</h3>
                                    <span>
                                        <?= $itemQuantityTotal > 0 ? $itemQuantityTotal : (int)($order['item_lines'] ?? 0) ?>
                                        article<?= ($itemQuantityTotal > 0 ? $itemQuantityTotal : (int)($order['item_lines'] ?? 0)) > 1 ? 's' : '' ?>
                                    </span>
                                </div>

                                <?php if ($previewItems !== []): ?>
                                    <div class="uorder_product_preview">
                                        <?php foreach ($previewItems as $previewItem): ?>
                                            <?php
                                            $previewName = trim((string)($previewItem['product_name'] ?? 'Produit')) ?: 'Produit';
                                            $previewVariant = trim((string)($previewItem['variant_name'] ?? ''));
                                            $previewQuantity = max(1, (int)($previewItem['quantity'] ?? 1));
                                            ?>
                                            <span class="uorder_product_chip">
                                                <strong><?= htmlspecialchars($previewName) ?></strong>
                                                <?php if ($previewVariant !== '' && strcasecmp($previewVariant, 'Standard') !== 0): ?>
                                                    <small><?= htmlspecialchars($previewVariant) ?></small>
                                                <?php endif; ?>
                                                <em>×<?= $previewQuantity ?></em>
                                            </span>
                                        <?php endforeach; ?>

                                        <?php if ($remainingPreviewCount > 0): ?>
                                            <span class="uorder_product_more">+<?= $remainingPreviewCount ?> autre<?= $remainingPreviewCount > 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="uorder_product_fallback">
                                        <?= (int)($order['item_lines'] ?? 0) ?> ligne<?= (int)($order['item_lines'] ?? 0) > 1 ? 's' : '' ?> de commande
                                    </p>
                                <?php endif; ?>
                            </section>

                            <section class="uorder_payment" aria-label="Règlement de la commande">
                                <div class="uorder_section_heading">
                                    <h3>Règlement</h3>
                                    <span><?= $paymentProgress ?> % encaissé</span>
                                </div>

                                <div class="uorder_payment_metrics">
                                    <p><span>Payé</span><strong><?= number_format($paidTotal, 2, ',', ' ') ?> €</strong></p>
                                    <p><span>Remboursé</span><strong><?= number_format($refundedTotal, 2, ',', ' ') ?> €</strong></p>
                                    <p><span>Net payé</span><strong><?= number_format($netPaidTotal, 2, ',', ' ') ?> €</strong></p>
                                </div>

                                <progress
                                        class="uorder_payment_track"
                                        max="100"
                                        value="<?= $paymentProgress ?>"
                                        aria-hidden="true"
                                ><?= $paymentProgress ?> %</progress>
                            </section>
                        </div>

                        <?php if ($reportInfo['can_report']): ?>
                            <footer class="uorder_card_footer">
                                <details class="order_issue_box order_issue_box_inline uorder_issue_box" id="<?= htmlspecialchars($reportBoxId) ?>">
                                    <summary class="order_issue_summary">
                                        <span class="order_issue_summary_main">
                                            <span class="order_issue_summary_icon" aria-hidden="true"><?= renderUiIcon('alert') ?></span>
                                            <span>Signaler un souci</span>
                                        </span>
                                        <span class="order_issue_summary_hint">Encore <?= htmlspecialchars((string)$reportInfo['remaining_label']) ?></span>
                                    </summary>

                                    <form method="post" action="index.php?controller=shop&action=reportAlert" class="order_issue_form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="source_context" value="user_order">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                        <input type="hidden" name="priority" value="medium">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentListUrl . '#' . $reportBoxId) ?>">

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
                                                <?php foreach ($reportItems as $reportItem): ?>
                                                    <?php
                                                    $reportLabel = (string)($reportItem['product_name'] ?? 'Produit');
                                                    $reportVariant = trim((string)($reportItem['variant_name'] ?? ''));
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
                                            <button type="submit" class="order_issue_submit">Envoyer</button>
                                        </div>
                                    </form>
                                </details>

                                <p>Signalement possible jusqu’au <?= htmlspecialchars((string)$reportInfo['deadline_label']) ?>.</p>
                            </footer>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <nav class="uorders_pagination" aria-label="Pagination des commandes">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a
                                class="<?= $i === (int)$page ? 'is_active' : '' ?>"
                                href="index.php?controller=user&action=orders&q=<?= urlencode((string)($q ?? '')) ?>&page=<?= $i ?>"
                                <?= $i === (int)$page ? 'aria-current="page"' : '' ?>
                        >
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
