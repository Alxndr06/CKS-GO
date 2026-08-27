<?php
require_once __DIR__ . '/../../partials/header.php';

if (!function_exists('renderAdminListActionIcon')) {
    function renderAdminListActionIcon(string $type): string
    {
        return match ($type) {
            'show' => <<<SVG
<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
</svg>
SVG,
            'edit' => <<<SVG
<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M12 20h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG,
            default => '',
        };
    }
}

?>

<main class="main_part admin_dashboard_page admin_page_pro">
    <section class="admin_dashboard_intro">
        <span class="section_kicker">Catalogue</span>
        <h2>Liste des produits</h2>
        <p>Vue compacte du catalogue pour consulter et gérer rapidement les produits.</p>
    </section>

    <section class="comms_filter_bar apl_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form" data-auto-filter-form>
            <input type="hidden" name="controller" value="shop">
            <input type="hidden" name="action" value="allProducts">

            <label class="comms_search_field">
                <span>Rechercher</span>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars((string)($q ?? '')) ?>"
                    placeholder="Produit, catégorie, variante ou SKU..."
                    autocomplete="off"
                    data-auto-filter
                >
            </label>

            <label>
                <span>Catalogue</span>
                <select name="archive" data-auto-filter>
                    <option value="active" <?= ($archiveState ?? 'active') === 'active' ? 'selected' : '' ?>>Actifs</option>
                    <option value="archived" <?= ($archiveState ?? '') === 'archived' ? 'selected' : '' ?>>Archivés</option>
                    <option value="all" <?= ($archiveState ?? '') === 'all' ? 'selected' : '' ?>>Tous</option>
                </select>
            </label>

            <button type="submit">Filtrer</button>
            <?php if (!empty($q) || ($archiveState ?? 'active') !== 'active'): ?>
                <a href="index.php?controller=shop&amp;action=allProducts">Effacer</a>
            <?php endif; ?>
        </form>

        <span class="comms_result_count"><?= (int)($total ?? 0) ?> produit<?= (int)($total ?? 0) > 1 ? 's' : '' ?></span>
        <a class="comms_primary_action" href="index.php?controller=shop&amp;action=addProduct">Nouveau produit</a>
    </section>

    <section class="admin_dashboard_section">

        <?php if (empty($products)): ?>
            <div class="empty_state">
                <h3>Aucun produit trouvé</h3>
                <p>La recherche n’a retourné aucun résultat.</p>
            </div>
        <?php else: ?>
            <div class="apl_table">
                <div class="apl_table_head">
                    <div>Produit</div>
                    <div>État</div>
                    <div>Prix</div>
                    <div>Catégorie</div>
                    <div>Vars</div>
                    <div>Stock</div>
                    <div>Actions</div>
                </div>

                <?php foreach ($products as $product): ?>
                    <?php
                    $isActive = (int) $product['is_active'] === 1;
                    $isArchived = !empty($product['archived_at']);
                    $stockTotal = (int) $product['sellable_stock'];
                    $productImage = resolvePublicImageFilename($product['image'] ?? '');

                    if ($stockTotal <= 0) {
                        $stockClass = 'apl_stock apl_stock_out';
                    } elseif ($stockTotal <= 5) {
                        $stockClass = 'apl_stock apl_stock_low';
                    } else {
                        $stockClass = 'apl_stock apl_stock_ok';
                    }
                    ?>

                    <article class="apl_row">
                        <div class="apl_product">
                            <div class="apl_product_media">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($productImage) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                >
                            </div>

                            <div class="apl_product_identity">
                                <h4>
                                    <a
                                            class="apl_product_link"
                                            href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $product['id'] ?>"
                                    >
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h4>

                                <div class="apl_product_badges">
                                    <?php if ($isArchived): ?>
                                        <span class="apl_badge">Archivé</span>
                                    <?php endif; ?>
                                    <?php if (($product['visibility'] ?? 'public') === 'admin_only'): ?>
                                        <span class="apl_badge">Staff uniquement</span>
                                    <?php elseif (($product['visibility'] ?? 'public') === 'authenticated'): ?>
                                        <span class="apl_badge">Membres connectés</span>
                                    <?php else: ?>
                                        <span class="apl_badge">Public</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="apl_state" data-label="État">
                            <span
                                    class="apl_state_dot <?= $isActive && !$isArchived ? 'is_active' : 'is_inactive' ?>"
                                    role="img"
                                    aria-label="<?= $isArchived ? 'Archivé' : ($isActive ? 'Actif' : 'Inactif') ?>"
                                    title="<?= $isArchived ? 'Archivé' : ($isActive ? 'Actif' : 'Inactif') ?>"
                            ></span>
                        </div>

                        <div class="apl_price" data-label="Prix">
                            <?php if ($product['min_price'] !== null && $product['max_price'] !== null): ?>
                                <?php if ((float) $product['min_price'] === (float) $product['max_price']): ?>
                                    <?= number_format((float) $product['min_price'], 2, ',', ' ') ?> €
                                <?php else: ?>
                                    <?= number_format((float) $product['min_price'], 2, ',', ' ') ?> €
                                    à
                                    <?= number_format((float) $product['max_price'], 2, ',', ' ') ?> €
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>

                        <div class="apl_category" data-label="Catégorie">
                            <?= !empty($product['category_name']) ? htmlspecialchars($product['category_name']) : '—' ?>
                        </div>

                        <div class="apl_variants" data-label="Vars">
                            <?= (int) $product['variant_count'] ?>
                        </div>

                        <div class="<?= $stockClass ?>" data-label="Stock">
                            <?= $stockTotal ?>
                        </div>

                        <div class="apl_actions" data-label="Actions">
                            <a
                                    class="apl_btn apl_btn_small apl_btn_light apl_action_compact"
                                    href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $product['id'] ?>"
                                    aria-label="Voir"
                                    title="Voir"
                            >
                                <span class="apl_action_label">Voir</span>
                                <span class="apl_action_icon" aria-hidden="true">
                                    <?= renderAdminListActionIcon('show') ?>
                                </span>
                            </a>

                            <?php if (!$isArchived): ?>
                                <a
                                    class="apl_btn apl_btn_small apl_btn_light apl_action_compact"
                                    href="index.php?controller=shop&action=editProduct&id=<?= (int) $product['id'] ?>"
                                    aria-label="Modifier"
                                    title="Modifier"
                            >
                                <span class="apl_action_label">Modifier</span>
                                <span class="apl_action_icon" aria-hidden="true">
                                    <?= renderAdminListActionIcon('edit') ?>
                                </span>
                                </a>
                            <?php elseif (currentUserCan('catalog.delete')): ?>
                                <form method="post" action="index.php?controller=shop&amp;action=restoreProduct" data-confirm-message="Restaurer ce produit dans le catalogue ?">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                    <button type="submit" class="apl_btn apl_btn_small apl_btn_light">Restaurer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <?php
                $productsPaginationTemplate = 'index.php?' . http_build_query([
                                'controller' => 'shop',
                                'action' => 'allProducts',
                                'page' => '__PAGE__',
                                'q' => (string) ($q ?? ''),
                                'archive' => (string)($archiveState ?? 'active'),
                        ]);

                $paginationCurrentPage = (int) ($page ?? 1);
                $paginationTotalPages = (int) ($totalPages ?? 1);
                $paginationLabel = 'Pagination des produits';
                $paginationPageTemplate = $productsPaginationTemplate;
                require __DIR__ . '/../../partials/admin_pagination.php';
                ?>
            <?php endif; ?>

        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
