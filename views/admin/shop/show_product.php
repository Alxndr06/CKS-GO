<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Produit</span>
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p>
                Consulte les variantes du produit, ajuste leur stock et accède aux formulaires de modification.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="admin_product_detail_card">
                <div class="admin_product_detail_media">
                    <img
                            src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($product['image'] ?: 'php.png') ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                    >
                </div>

                <div class="admin_product_detail_body">
                    <div class="admin_product_head">
                        <div>
                            <h3><?= htmlspecialchars($product['name']) ?></h3>

                            <?php if (!empty($product['category_name'])): ?>
                                <p class="admin_product_category">
                                    Catégorie : <strong><?= htmlspecialchars($product['category_name']) ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="admin_product_badges">
                            <?php if ((int)$product['is_active'] === 1): ?>
                                <span class="product_badge product_badge_stock in_stock">Actif</span>
                            <?php else: ?>
                                <span class="product_badge product_badge_stock out_stock">Inactif</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                        <p class="admin_product_desc"><?= htmlspecialchars($product['description']) ?></p>
                    <?php endif; ?>

                    <div class="admin_product_meta">
                        <p>Variantes : <strong><?= (int)$product['variant_count'] ?></strong></p>
                        <p>Stock total : <strong><?= (int)$product['total_stock'] ?></strong></p>
                    </div>

                    <div class="admin_product_actions">
                        <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=editProduct&id=<?= (int)$product['id'] ?>">
                            Modifier le produit
                        </a>

                        <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=addVariant&product_id=<?= (int)$product['id'] ?>">
                            Ajouter une variante
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Variantes</span>
                <h3>Gestion du stock</h3>
                <p>Ajuste le stock variante par variante.</p>
            </div>

            <?php if (empty($product['variants'])): ?>
                <div class="empty_state">
                    <h3>Aucune variante</h3>
                    <p>Ce produit n’a encore aucune variante enregistrée.</p>
                </div>
            <?php else: ?>
                <div class="admin_variant_manage_list">
                    <?php foreach ($product['variants'] as $variant): ?>
                        <article class="admin_variant_manage_card">
                            <div class="admin_variant_manage_infos">
                                <div>
                                    <h4><?= htmlspecialchars($variant['display_name']) ?></h4>
                                    <p>
                                        Prix : <strong><?= number_format((float)$variant['price'], 2, ',', ' ') ?> €</strong>
                                    </p>
                                    <p>
                                        Stock actuel : <strong><?= (int)$variant['stock_quantity'] ?></strong>
                                    </p>
                                </div>

                                <div class="admin_variant_badges">
                                    <?php if ((int)$variant['is_active'] === 1): ?>
                                        <span class="product_badge product_badge_stock in_stock">Active</span>
                                    <?php else: ?>
                                        <span class="product_badge product_badge_stock out_stock">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="admin_variant_manage_actions">
                                <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=editVariant&id=<?= (int)$variant['id'] ?>">
                                    Modifier la variante
                                </a>
                            </div>

                            <form
                                    method="post"
                                    action="index.php?controller=shop&action=updateVariantStock"
                                    onsubmit="return confirm('Confirmer la mise à jour du stock de cette variante ?');"
                                    class="admin_variant_stock_form"
                            >
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="variant_id" value="<?= (int)$variant['id'] ?>">

                                <label for="stock_quantity_<?= (int)$variant['id'] ?>">Nouveau stock</label>
                                <input
                                        type="number"
                                        name="stock_quantity"
                                        id="stock_quantity_<?= (int)$variant['id'] ?>"
                                        min="0"
                                        value="<?= (int)$variant['stock_quantity'] ?>"
                                        required
                                >

                                <button type="submit">Mettre à jour le stock</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin_dashboard_section">
            <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=manageShop">
                Retour à la liste des produits
            </a>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>