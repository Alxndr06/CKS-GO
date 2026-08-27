<?php
require_once __DIR__ . '/../../partials/header.php';
$productArchived = !empty($product['archived_at']);
$productImage = resolvePublicImageFilename($product['image'] ?? '');
?>

<main class="main_part admin_dashboard_page admin_product_show_page">
    <section class="showp_page_header">
        <div class="showp_page_header_text">
            <span class="section_kicker">Produit</span>
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p>Vue admin compacte pour piloter le produit, ses variantes et son stock sans friction.</p>
        </div>
    </section>

    <section class="showp_toolbar" aria-label="Navigation produit">
        <div class="showp_toolbar_row">
            <div class="showp_toolbar_left">
                <span class="showp_toolbar_label">Navigation / lecture rapide</span>
                <span class="showp_toolbar_count">
                    <?= (int) ($product['variant_count'] ?? 0) ?> variante(s)
                </span>
            </div>

            <div class="showp_toolbar_actions">
                <a class="showp_action_link showp_action_link_soft" href="index.php?controller=shop&action=allProducts">
                    Retour
                </a>
            </div>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <article class="showp_summary_card">
            <div class="showp_summary_media">
                <img
                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($productImage) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                >
            </div>

            <div class="showp_summary_body">
                <div class="showp_summary_top">
                    <div class="showp_summary_title_wrap">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>

                        <div class="showp_summary_badges">
                            <?php if ($productArchived): ?>
                                <span class="showp_badge showp_badge_warning">Archivé</span>
                            <?php elseif ((int) $product['is_active'] === 1): ?>
                                <span class="showp_badge showp_badge_success">Actif</span>
                            <?php else: ?>
                                <span class="showp_badge showp_badge_warning">Désactivé</span>
                            <?php endif; ?>

                            <?php if (($product['visibility'] ?? 'public') === 'admin_only'): ?>
                                <span class="showp_badge">Staff uniquement</span>
                            <?php elseif (($product['visibility'] ?? 'public') === 'authenticated'): ?>
                                <span class="showp_badge">Membres connectés</span>
                            <?php else: ?>
                                <span class="showp_badge">Public</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($product['description'])): ?>
                    <p class="showp_summary_description"><?= htmlspecialchars($product['description']) ?></p>
                <?php endif; ?>

                <div class="showp_summary_stats">
                    <div class="showp_stat_card">
                        <span>Catégorie</span>
                        <strong><?= !empty($product['category_name']) ? htmlspecialchars($product['category_name']) : '—' ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>Variantes</span>
                        <strong><?= (int) ($product['variant_count'] ?? 0) ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>Stock physique</span>
                        <strong><?= (int) ($product['total_stock'] ?? 0) ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>Stock vendable</span>
                        <strong><?= (int)($product['sellable_stock'] ?? 0) ?></strong>
                    </div>
                </div>

                <div class="showp_summary_actions">
                    <?php if (!$productArchived): ?>
                    <a class="showp_btn showp_btn_light" href="index.php?controller=shop&action=editProduct&id=<?= (int) $product['id'] ?>">
                        Modifier
                    </a>

                    <?php if ((int) $product['is_active'] === 1): ?>
                        <form
                                method="post"
                                action="index.php?controller=shop&action=disableProduct"
                                class="showp_inline_form"
                                data-confirm-message="Désactiver ce produit ? Les variantes seront aussi désactivées."
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="showp_btn showp_btn_light">
                                Désactiver
                            </button>
                        </form>
                    <?php else: ?>
                        <form
                                method="post"
                                action="index.php?controller=shop&action=enableProduct"
                                class="showp_inline_form"
                                data-confirm-message="Réactiver ce produit ?"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="showp_btn showp_btn_primary">
                                Réactiver
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (currentUserCan('catalog.delete')): ?>
                        <form
                                method="post"
                                action="index.php?controller=shop&action=deleteProduct"
                                class="showp_inline_form"
                                data-confirm-message="Archiver ce produit ? Il sera retiré de la boutique et des paniers, mais son historique sera conservé."
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="showp_btn showp_btn_danger">
                                Archiver
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php elseif (currentUserCan('catalog.delete')): ?>
                        <form method="post" action="index.php?controller=shop&amp;action=restoreProduct" class="showp_inline_form" data-confirm-message="Restaurer ce produit ?">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                            <button type="submit" class="showp_btn showp_btn_primary">Restaurer</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </section>

    <section class="admin_dashboard_section">
        <div class="showp_section_header">
            <div class="showp_section_header_text">
                <span class="section_kicker">Variantes</span>
                <h3>Liste des variantes</h3>
            </div>

            <?php if (!$productArchived): ?>
            <div class="showp_section_header_actions">
                <a
                        class="showp_action_link showp_action_link_primary"
                        href="index.php?controller=shop&action=addVariant&product_id=<?= (int) $product['id'] ?>"
                >
                    Ajouter une variante
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($product['variants'])): ?>
            <div class="empty_state">
                <h3>Aucune variante</h3>
                <p>Ce produit n’a encore aucune variante enregistrée.</p>
            </div>
        <?php else: ?>
            <div class="showp_table">
                <div class="showp_table_head">
                    <div>Variante</div>
                    <div>Prix</div>
                    <div>Stock</div>
                    <div>Ordre</div>
                    <div>État</div>
                    <div>Actions</div>
                </div>

                <?php foreach ($product['variants'] as $variant): ?>
                    <?php
                    $variantId = (int) $variant['id'];
                    $stockQty = (int) $variant['stock_quantity'];
                    $stockThreshold = (int)($variant['low_stock_threshold'] ?? 5);
                    $variantArchived = !empty($variant['archived_at']);
                    $stockClass = $stockQty <= 0 ? 'showp_stock_out' : ($stockQty <= $stockThreshold ? 'showp_stock_low' : 'showp_stock_ok');
                    $variantImage = resolvePublicImageFilename($variant['image'] ?? '', $productImage);
                    ?>
                    <article class="showp_row <?= $variantArchived ? 'is_archived' : '' ?>">
                        <div class="showp_variant_main">
                            <div class="showp_variant_thumb">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($variantImage) ?>"
                                        alt="<?= htmlspecialchars($variant['display_name']) ?>"
                                >
                            </div>

                            <div class="showp_variant_identity">
                                <h4><?= htmlspecialchars($variant['display_name']) ?></h4>
                                <small class="showp_variant_sku"><?= htmlspecialchars((string)($variant['sku'] ?? '')) ?></small>

                                <p class="showp_variant_meta_mobile">
                                    <?= number_format((float) $variant['price'], 2, ',', ' ') ?> €
                                    · Ordre : <?= (int) ($variant['sort_order'] ?? 0) ?>
                                    · <?= (int) $variant['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </p>
                            </div>
                        </div>

                        <div class="showp_variant_price">
                            <?= number_format((float) $variant['price'], 2, ',', ' ') ?> €
                        </div>

                        <div class="showp_variant_stock">
                            <span class="showp_stock_badge <?= $stockClass ?>">
                                <?= $stockQty ?>
                            </span>
                            <small>Seuil <?= $stockThreshold ?></small>
                        </div>

                        <div class="showp_variant_order">
                            <?= (int) ($variant['sort_order'] ?? 0) ?>
                        </div>

                        <div class="showp_variant_state">
                            <?php if ($variantArchived): ?>
                                <span class="showp_state_badge is_inactive">
                                    <span class="showp_state_dot"></span>
                                    Archivée
                                </span>
                            <?php elseif ((int) $variant['is_active'] === 1): ?>
                                <span class="showp_state_badge is_active">
                                    <span class="showp_state_dot"></span>
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="showp_state_badge is_inactive">
                                    <span class="showp_state_dot"></span>
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="showp_variant_actions">
                            <?php if ($variantArchived): ?>
                                <?php if (currentUserCan('catalog.delete') && !$productArchived): ?>
                                    <form method="post" action="index.php?controller=shop&amp;action=restoreVariant" class="showp_inline_form" data-confirm-message="Restaurer cette variante ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                        <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                                        <button type="submit" class="showp_btn showp_btn_small showp_btn_primary">Restaurer</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                            <a
                                    class="showp_btn showp_btn_small showp_btn_light"
                                    href="index.php?controller=shop&action=editVariant&id=<?= $variantId ?>"
                            >
                                Modifier
                            </a>

                            <?php if (currentUserCan('inventory.adjust')): ?>
                            <button
                                    type="button"
                                    class="showp_btn showp_btn_small showp_btn_primary"
                                    data-stock-form-target="stock-form-<?= $variantId ?>"
                                    aria-controls="stock-form-<?= $variantId ?>"
                                    aria-expanded="false"
                            >
                                Stock
                            </button>
                            <?php endif; ?>

                            <?php if (currentUserCan('catalog.delete')): ?>
                                <form
                                        method="post"
                                        action="index.php?controller=shop&action=deleteVariant"
                                        class="showp_inline_form showp_inline_form_variant"
                                        data-confirm-message="Archiver cette variante ? Elle sera retirée des paniers, mais son historique sera conservé."
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                                    <button type="submit" class="showp_btn showp_btn_small showp_btn_danger">
                                        Archiver
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!$variantArchived && currentUserCan('inventory.adjust')): ?>
                        <form
                                method="post"
                                action="index.php?controller=shop&action=updateVariantStock"
                                class="showp_stock_form"
                                id="stock-form-<?= $variantId ?>"
                                data-confirm-message="Confirmer la mise à jour du stock de cette variante ?"
                        >
                            <div class="showp_stock_form_field">
                                <label for="stock_quantity_<?= $variantId ?>">Stock</label>
                                <input
                                        type="number"
                                        name="stock_quantity"
                                        id="stock_quantity_<?= $variantId ?>"
                                        min="0"
                                        value="<?= (int) $variant['stock_quantity'] ?>"
                                        required
                                >
                            </div>

                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <input type="hidden" name="variant_id" value="<?= $variantId ?>">

                            <button type="submit" class="showp_btn showp_btn_small showp_btn_primary">
                                Mettre à jour
                            </button>
                        </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
