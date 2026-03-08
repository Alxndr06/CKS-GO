<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Boutique</span>
            <h2>Gestion de la boutique</h2>
            <p>
                Gère les produits, prépare la boutique, et facture directement
                un produit à un utilisateur si besoin.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Gestion</span>
                <h3>Outils boutique</h3>
                <p>Les accès utiles regroupés au même endroit.</p>
            </div>

            <div class="admin_management_grid">
                <a class="dashboard_action_card" href="index.php?controller=shop&action=manageShop">
                    <span class="dashboard_action_icon">📜</span>
                    <div>
                        <h3>Liste des produits</h3>
                        <p>Voir et gérer les produits existants.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=shop&action=addProductToShop">
                    <span class="dashboard_action_icon">🛒</span>
                    <div>
                        <h3>Ajouter un produit</h3>
                        <p>Créer un nouveau produit dans la boutique.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=admin&action=billUserProduct">
                    <span class="dashboard_action_icon">💳</span>
                    <div>
                        <h3>Facturer un utilisateur</h3>
                        <p>Ajouter un produit directement à la note d’un utilisateur.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=admin&action=serverSettings">
                    <span class="dashboard_action_icon">⚙️</span>
                    <div>
                        <h3>Paramètres boutique</h3>
                        <p>Réglages et configuration liés à la boutique.</p>
                    </div>
                </a>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Catalogue</span>
                <h3>Liste des produits</h3>
                <p>Vue d’ensemble du catalogue, des variantes et du stock.</p>
            </div>

            <form method="get" action="index.php" class="admin_catalog_search_form">
                <input type="hidden" name="controller" value="shop">
                <input type="hidden" name="action" value="manageShop">

                <div class="search_row">
                    <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q ?? '') ?>"
                            placeholder="Rechercher un produit, une catégorie, une variante..."
                    >
                    <button type="submit">Rechercher</button>
                </div>
            </form>

            <?php if (empty($products)): ?>
                <div class="empty_state">
                    <h3>Aucun produit trouvé</h3>
                    <p>La recherche n’a retourné aucun résultat.</p>
                </div>
            <?php else: ?>
                <div class="admin_products_grid">
                    <?php foreach ($products as $product): ?>
                        <article class="admin_product_card">
                            <div class="admin_product_image">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($product['image'] ?: 'php.png') ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                >
                            </div>

                            <div class="admin_product_body">
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

                                    <?php if ($product['min_price'] !== null): ?>
                                        <p>
                                            Prix :
                                            <strong>
                                                <?= number_format((float)$product['min_price'], 2, ',', ' ') ?> €
                                                <?php if ((float)$product['max_price'] !== (float)$product['min_price']): ?>
                                                    à <?= number_format((float)$product['max_price'], 2, ',', ' ') ?> €
                                                <?php endif; ?>
                                            </strong>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="admin_product_variants">
                                    <h4>Variantes</h4>

                                    <?php if (empty($product['variants'])): ?>
                                        <p class="muted">Aucune variante enregistrée.</p>
                                    <?php else: ?>
                                        <div class="admin_variant_list">
                                            <?php foreach ($product['variants'] as $variant): ?>
                                                <article class="admin_variant_card">
                                                    <div>
                                                        <p class="admin_variant_name">
                                                            <strong><?= htmlspecialchars($variant['display_name']) ?></strong>
                                                        </p>
                                                        <p class="admin_variant_details">
                                                            <?= number_format((float)$variant['price'], 2, ',', ' ') ?> €
                                                            — Stock : <?= (int)$variant['stock_quantity'] ?>
                                                        </p>
                                                    </div>

                                                    <div class="admin_variant_badges">
                                                        <?php if ((int)$variant['is_active'] === 1): ?>
                                                            <span class="product_badge product_badge_stock in_stock">Active</span>
                                                        <?php else: ?>
                                                            <span class="product_badge product_badge_stock out_stock">Inactive</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="admin_product_actions">
                                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=showAdminProduct&id=<?= (int)$product['id'] ?>">
                                        Voir la fiche
                                    </a>

                                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=editProduct&id=<?= (int)$product['id'] ?>">
                                        Modifier
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>