<?php
require_once __DIR__ . '/../../partials/header.php';

$reasonLabels = [
    'sale' => 'Vente',
    'order_checkout' => 'Commande',
    'refund' => 'Remboursement',
    'restock' => 'Réassort',
    'count' => 'Inventaire',
    'correction' => 'Correction',
    'return' => 'Retour',
    'loss' => 'Perte',
    'theft' => 'Vol',
    'manual' => 'Manuel',
    'adjustment' => 'Ajustement',
];

$totalVariants = (int)($stats['total_variants'] ?? 0);
$physicalStock = (int)($stats['physical_stock'] ?? 0);
$sellableStock = (int)($stats['sellable_stock'] ?? 0);
$lowCount = (int)($stats['low_count'] ?? 0);
$outCount = (int)($stats['out_count'] ?? 0);
?>

<main class="main_part admin_dashboard_page admin_page_pro inventory_page">
    <section class="management_module_header">
        <div class="management_module_header_copy">
            <span class="section_kicker">Stocks</span>
            <h2>Pilotage de l’inventaire</h2>
            <p>Contrôle les disponibilités, enregistre chaque mouvement et retrouve immédiatement son origine.</p>
        </div>

        <div class="management_module_header_actions">
            <a class="showp_action_link" href="index.php?controller=shop&amp;action=inventoryIssues">
                Pertes et vols
            </a>
            <a class="showp_action_link showp_action_link_primary" href="index.php?controller=shop&amp;action=allProducts">
                Catalogue
            </a>
        </div>
    </section>

    <section class="inventory_summary_grid" aria-label="Synthèse des stocks">
        <article class="inventory_summary_card">
            <span>Variantes suivies</span>
            <strong><?= $totalVariants ?></strong>
            <small>Références non archivées</small>
        </article>
        <article class="inventory_summary_card">
            <span>Stock physique</span>
            <strong><?= $physicalStock ?></strong>
            <small>Toutes variantes actives ou non</small>
        </article>
        <article class="inventory_summary_card is_success">
            <span>Stock vendable</span>
            <strong><?= $sellableStock ?></strong>
            <small>Disponible dans la boutique</small>
        </article>
        <article class="inventory_summary_card is_warning">
            <span>À surveiller</span>
            <strong><?= $lowCount + $outCount ?></strong>
            <small><?= $lowCount ?> faible<?= $lowCount > 1 ? 's' : '' ?> · <?= $outCount ?> rupture<?= $outCount > 1 ? 's' : '' ?></small>
        </article>
    </section>

    <section class="comms_filter_bar inventory_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form" data-auto-filter-form>
            <input type="hidden" name="controller" value="shop">
            <input type="hidden" name="action" value="inventory">

            <label class="comms_search_field">
                <span>Rechercher</span>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars((string)$q) ?>"
                    placeholder="Produit, variante ou SKU..."
                    autocomplete="off"
                    data-auto-filter
                >
            </label>

            <label>
                <span>État</span>
                <select name="state" data-auto-filter>
                    <option value="all" <?= $state === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="available" <?= $state === 'available' ? 'selected' : '' ?>>Disponibles</option>
                    <option value="low" <?= $state === 'low' ? 'selected' : '' ?>>Stock faible</option>
                    <option value="out" <?= $state === 'out' ? 'selected' : '' ?>>Ruptures</option>
                    <option value="inactive" <?= $state === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                </select>
            </label>

            <button type="submit">Filtrer</button>
            <?php if ($q !== '' || $state !== 'all'): ?>
                <a href="index.php?controller=shop&amp;action=inventory">Effacer</a>
            <?php endif; ?>
        </form>

        <span class="comms_result_count"><?= count($items ?? []) ?> résultat<?= count($items ?? []) > 1 ? 's' : '' ?></span>
    </section>

    <section class="admin_dashboard_section inventory_catalog_section">
        <div class="section_heading inventory_section_heading">
            <span class="section_kicker">Inventaire</span>
            <h3>Produits et variantes</h3>
            <p>Le stock physique inclut les références inactives ; le stock vendable exige un produit et une variante actifs.</p>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty_state">
                <h3>Aucune variante trouvée</h3>
                <p>Modifie la recherche ou le filtre d’état.</p>
            </div>
        <?php else: ?>
            <div class="inventory_list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $stock = (int)$item['stock_quantity'];
                    $threshold = (int)$item['low_stock_threshold'];
                    $isSellable = (int)$item['product_is_active'] === 1 && (int)$item['variant_is_active'] === 1;
                    $stockClass = $stock <= 0 ? 'is_out' : ($stock <= $threshold ? 'is_low' : 'is_ok');
                    $displayVariant = trim((string)($item['flavor'] ?: $item['variant_name'] ?: 'Standard'));
                    $image = resolvePublicImageFilename($item['variant_image'] ?: $item['product_image']);
                    ?>
                    <article class="inventory_row <?= $stockClass ?>">
                        <div class="inventory_identity">
                            <img src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($image) ?>" alt="">
                            <div>
                                <h4><?= htmlspecialchars((string)$item['product_name']) ?></h4>
                                <p><?= htmlspecialchars($displayVariant) ?></p>
                                <span><?= htmlspecialchars((string)$item['sku']) ?></span>
                            </div>
                        </div>

                        <div class="inventory_stock_block">
                            <span>Stock actuel</span>
                            <strong><?= $stock ?></strong>
                            <small>Seuil : <?= $threshold ?></small>
                        </div>

                        <div class="inventory_availability">
                            <span class="inventory_state_badge <?= $isSellable ? 'is_active' : 'is_inactive' ?>">
                                <?= $isSellable ? 'Vendable' : 'Inactif' ?>
                            </span>
                            <small><?= $stock <= 0 ? 'Rupture' : ($stock <= $threshold ? 'À surveiller' : 'Niveau correct') ?></small>
                        </div>

                        <div class="inventory_last_movement">
                            <span>Dernier mouvement</span>
                            <?php if (!empty($item['last_movement_at'])): ?>
                                <strong><?= htmlspecialchars($reasonLabels[$item['last_movement_reason']] ?? $item['last_movement_reason']) ?></strong>
                                <small>
                                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$item['last_movement_at']))) ?>
                                    <?php if (trim((string)$item['last_movement_author']) !== ''): ?>
                                        · <?= htmlspecialchars(trim((string)$item['last_movement_author'])) ?>
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <strong>Aucun</strong>
                                <small>Stock initial non journalisé</small>
                            <?php endif; ?>
                        </div>

                        <div class="inventory_actions">
                            <a href="index.php?controller=shop&amp;action=showAdminProduct&amp;id=<?= (int)$item['product_id'] ?>">Fiche</a>
                            <details class="inventory_adjust_panel">
                                <summary>Ajuster</summary>
                                <form
                                    method="POST"
                                    action="index.php?controller=shop&amp;action=adjustVariantStock"
                                    data-inventory-adjust-form
                                    data-current-stock="<?= $stock ?>"
                                    data-confirm-message="Confirmer ce mouvement de stock ?"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
                                    <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                                    <input type="hidden" name="return_q" value="<?= htmlspecialchars((string)$q) ?>">
                                    <input type="hidden" name="return_state" value="<?= htmlspecialchars((string)$state) ?>">

                                    <label>
                                        <span>Opération</span>
                                        <select name="mode" data-inventory-mode required>
                                            <option value="increase">Ajouter</option>
                                            <option value="decrease">Retirer</option>
                                            <option value="set">Compter / fixer</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Quantité</span>
                                        <input type="number" name="quantity" min="0" value="1" data-inventory-quantity required>
                                    </label>
                                    <label>
                                        <span>Motif</span>
                                        <select name="reason" required>
                                            <option value="restock">Livraison / réassort</option>
                                            <option value="count">Inventaire</option>
                                            <option value="correction">Correction</option>
                                            <option value="return">Retour</option>
                                            <option value="loss">Perte</option>
                                            <option value="theft">Vol</option>
                                            <option value="manual">Autre</option>
                                        </select>
                                    </label>
                                    <label class="inventory_note_field">
                                        <span>Commentaire</span>
                                        <input type="text" name="note" maxlength="255" placeholder="Facultatif">
                                    </label>

                                    <p class="inventory_adjust_preview" data-inventory-preview>Stock <?= $stock ?> → <?= $stock + 1 ?></p>
                                    <button type="submit">Enregistrer le mouvement</button>
                                </form>
                            </details>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin_dashboard_section inventory_history_section">
        <div class="section_heading inventory_section_heading">
            <span class="section_kicker">Traçabilité</span>
            <h3>Derniers mouvements</h3>
            <p>Chaque vente, remboursement, inventaire ou correction est conservé.</p>
        </div>

        <div class="inventory_history_list">
            <?php foreach (($movements ?? []) as $movement): ?>
                <?php $movementVariant = trim((string)($movement['flavor'] ?: $movement['variant_name'] ?: 'Standard')); ?>
                <article class="inventory_history_row">
                    <div>
                        <strong><?= htmlspecialchars((string)$movement['product_name']) ?></strong>
                        <span><?= htmlspecialchars($movementVariant) ?> · <?= htmlspecialchars((string)$movement['sku']) ?></span>
                    </div>
                    <span class="inventory_movement_reason"><?= htmlspecialchars($reasonLabels[$movement['reason']] ?? $movement['reason']) ?></span>
                    <strong class="inventory_movement_delta <?= (int)$movement['qty'] >= 0 ? 'is_positive' : 'is_negative' ?>">
                        <?= (int)$movement['qty'] > 0 ? '+' : '' ?><?= (int)$movement['qty'] ?>
                    </strong>
                    <span>
                        <?php if ($movement['stock_before'] !== null && $movement['stock_after'] !== null): ?>
                            <?= (int)$movement['stock_before'] ?> → <?= (int)$movement['stock_after'] ?>
                        <?php else: ?>
                            Historique ancien
                        <?php endif; ?>
                    </span>
                    <small>
                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$movement['created_at']))) ?>
                        <?php if (trim((string)$movement['admin_name']) !== ''): ?>
                            · <?= htmlspecialchars(trim((string)$movement['admin_name'])) ?>
                        <?php endif; ?>
                        <?php if (!empty($movement['order_id'])): ?>
                            · Commande #<?= (int)$movement['order_id'] ?>
                        <?php endif; ?>
                    </small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
