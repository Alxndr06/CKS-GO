<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part cart_page">
        <section class="cart_intro">
            <div class="cart_intro_text">
                <span class="section_kicker">Panier</span>
                <h2>Ton panier CKS GO</h2>
                <p>
                    Vérifie tes articles, ajuste les quantités et valide ton panier.
                </p>
            </div>

            <?php if (!empty($cart['items'])): ?>
                <div class="cart_intro_actions">
                    <form method="post" action="index.php?controller=shop&action=checkout" class="checkout_form">
                        <button type="submit" class="checkout_btn">Valider le panier</button>
                    </form>

                    <form method="post" action="index.php?controller=shop&action=clearCart" class="clear_cart_form">
                        <button type="submit" class="clear_cart_btn">Vider le panier</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <?php if (empty($cart['items'])): ?>
            <section class="empty_state">
                <h3>Ton panier est vide</h3>
                <p>Ajoute quelques produits depuis la boutique pour commencer.</p>
                <a class="home_btn home_btn_primary" href="index.php?controller=shop&action=index">
                    Retour à la boutique
                </a>
            </section>
        <?php else: ?>
            <section class="cart_layout">
                <div class="cart_items_list">
                    <?php foreach ($cart['items'] as $item): ?>
                        <article class="cart_item_card <?= !$item['is_available'] ? 'cart_item_unavailable' : '' ?>">
                            <div class="cart_item_image">
                                <img
                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($item['product_image'] ?: 'php.png') ?>"
                                    alt="<?= htmlspecialchars($item['product_name']) ?>"
                                >
                            </div>

                            <div class="cart_item_body">
                                <div class="cart_item_head">
                                    <div>
                                        <h3><?= htmlspecialchars($item['product_name']) ?></h3>
                                        <p class="cart_variant_name">
                                            Variante : <strong><?= htmlspecialchars($item['display_variant']) ?></strong>
                                        </p>
                                    </div>

                                    <div class="cart_item_badges">
                                        <?php if ($item['is_available']): ?>
                                            <span class="product_badge product_badge_stock in_stock">Disponible</span>
                                        <?php else: ?>
                                            <span class="product_badge product_badge_stock out_stock">Indisponible</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($item['product_description'])): ?>
                                    <p class="cart_item_desc"><?= htmlspecialchars($item['product_description']) ?></p>
                                <?php endif; ?>

                                <div class="cart_item_meta">
                                    <p>Prix unitaire : <strong><?= number_format($item['unit_price'], 2, ',', ' ') ?> €</strong></p>
                                    <p>Total ligne : <strong><?= number_format($item['line_total'], 2, ',', ' ') ?> €</strong></p>
                                    <p>Stock dispo : <strong><?= (int)$item['stock_quantity'] ?></strong></p>
                                </div>

                                <div class="cart_item_actions">
                                    <form method="post" action="index.php?controller=shop&action=updateCartItem" class="cart_update_form">
                                        <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">

                                        <label>
                                            Quantité
                                            <input
                                                type="number"
                                                name="quantity"
                                                min="1"
                                                max="<?= (int)$item['stock_quantity'] ?>"
                                                value="<?= (int)$item['quantity'] ?>"
                                            >
                                        </label>

                                        <button type="submit">Mettre à jour</button>
                                    </form>

                                    <form method="post" action="index.php?controller=shop&action=removeCartItem" class="cart_remove_form">
                                        <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">
                                        <button type="submit" class="remove_btn">Retirer</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="cart_summary">
                    <div class="cart_summary_card">
                        <h3>Résumé</h3>

                        <div class="cart_summary_line">
                            <span>Articles</span>
                            <strong><?= (int)$cart['item_count'] ?></strong>
                        </div>

                        <div class="cart_summary_line">
                            <span>Sous-total</span>
                            <strong><?= number_format((float)$cart['subtotal'], 2, ',', ' ') ?> €</strong>
                        </div>

                        <div class="cart_summary_note">
                            <p>
                                À la validation : création de commande, décrément du stock, ajout à la note utilisateur.
                            </p>
                        </div>

                        <form method="post" action="index.php?controller=shop&action=checkout" class="checkout_form checkout_form_full">
                            <button type="submit" class="checkout_btn checkout_btn_full">Valider le panier</button>
                        </form>

                        <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=index">
                            Continuer mes achats
                        </a>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>