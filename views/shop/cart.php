<?php
require_once __DIR__ . '/../partials/header.php';

$csrfToken = getCsrfToken();

$itemCount = (int)($cart['item_count'] ?? 0);
$subtotal = (float)($cart['subtotal'] ?? 0);
$subtotalFormatted = number_format($subtotal, 2, ',', ' ') . ' €';
$isEmptyCart = empty($cart['items']);
?>

    <main class="main_part cart_page cart_page_redesign <?= $isEmptyCart ? 'cart_page_empty' : '' ?>">
        <section class="cart_intro cart_intro_redesign">
            <div class="cart_intro_text">
                <a class="cart_back_link" href="index.php?controller=shop&action=index">
                    <span aria-hidden="true">←</span> Continuer mes achats
                </a>
                <?php if (!$isEmptyCart): ?>
                    <div>
                        <span class="section_kicker">Dernière vérification</span>
                        <h1>Tout est presque prêt.</h1>
                        <p>Ajuste les quantités si besoin, puis confirme ta sélection.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isEmptyCart): ?>
                <div class="cart_intro_overview">
                    <span>Ta sélection</span>
                    <strong><?= $subtotalFormatted ?></strong>
                    <small><?= $itemCount ?> article<?= $itemCount > 1 ? 's' : '' ?> au total</small>
                    <form method="post" action="index.php?controller=shop&action=clearCart" class="clear_cart_form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="clear_cart_btn" data-confirm-message="Vider complètement le panier ?">Vider le panier</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($isEmptyCart): ?>
            <section class="cart_empty_shell">
                <div class="cart_empty_card">
                    <div class="cart_empty_icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M4 5h2l2 10h9l2-7H7"></path>
                            <circle cx="10" cy="19" r="1"></circle>
                            <circle cx="17" cy="19" r="1"></circle>
                        </svg>
                    </div>
                    <h3>Ton panier est vide</h3>
                    <p>
                        Ajoute quelques produits depuis la boutique pour commencer.
                        Tu pourras ensuite ajuster les quantités et valider en quelques clics.
                    </p>

                    <div class="cart_empty_points">
                        <div class="cart_empty_point">
                            <strong>Choisis</strong>
                            <span>Parcours la boutique et sélectionne tes produits.</span>
                        </div>

                        <div class="cart_empty_point">
                            <strong>Ajuste</strong>
                            <span>Modifie les quantités selon le stock disponible.</span>
                        </div>

                        <div class="cart_empty_point">
                            <strong>Valide</strong>
                            <span>Confirme ton panier quand tout est prêt.</span>
                        </div>
                    </div>

                    <div class="cart_empty_actions">
                        <a class="home_btn home_btn_primary" href="index.php?controller=shop&action=index">
                            Retour à la boutique
                        </a>

                        <a class="home_btn home_btn_secondary" href="index.php?controller=home&action=index">
                            Retour à l’accueil
                        </a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="cart_layout">
                <div class="cart_items_list">
                    <?php foreach ($cart['items'] as $item): ?>
                        <article class="cart_item_card <?= !$item['is_available'] ? 'cart_item_unavailable' : '' ?>">
                            <div class="cart_item_image">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars(resolvePublicImageFilename($item['product_image'] ?? null)) ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                >
                            </div>

                            <div class="cart_item_body">
                                <div class="cart_item_head">
                                    <div class="cart_item_title_block">
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
                                        <strong class="cart_item_line_price">
                                            <?= number_format((float)$item['line_total'], 2, ',', ' ') ?> €
                                        </strong>
                                    </div>
                                </div>

                                <?php if (!empty($item['product_description'])): ?>
                                    <p class="cart_item_desc"><?= htmlspecialchars($item['product_description']) ?></p>
                                <?php endif; ?>

                                <div class="cart_item_meta">
                                    <p>
                                        <span>Prix unitaire</span>
                                        <strong><?= number_format((float)$item['unit_price'], 2, ',', ' ') ?> €</strong>
                                    </p>
                                    <p>
                                        <span>Quantité actuelle</span>
                                        <strong><?= (int)$item['quantity'] ?></strong>
                                    </p>
                                    <p>
                                        <span>Stock dispo</span>
                                        <strong><?= (int)$item['stock_quantity'] ?></strong>
                                    </p>
                                </div>

                                <div class="cart_item_actions">
                                    <form method="post" action="index.php?controller=shop&action=updateCartItem" class="cart_update_form" data-cart-update-form>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">

                                        <div class="cart_quantity_control" data-quantity-control>
                                            <span>Quantité</span>
                                            <div class="shop_quantity_stepper cart_quantity_stepper">
                                                <button type="button" data-qty-minus aria-label="Diminuer la quantité">−</button>
                                                <input
                                                        type="number"
                                                        name="quantity"
                                                        min="1"
                                                        max="<?= max(1, (int)$item['stock_quantity']) ?>"
                                                        value="<?= (int)$item['quantity'] ?>"
                                                        inputmode="numeric"
                                                        aria-label="Quantité de <?= htmlspecialchars($item['product_name']) ?>"
                                                >
                                                <button type="button" data-qty-plus aria-label="Augmenter la quantité">+</button>
                                            </div>
                                        </div>

                                        <button type="submit" class="cart_update_btn">Actualiser</button>
                                    </form>

                                    <form method="post" action="index.php?controller=shop&action=removeCartItem" class="cart_remove_form" data-confirm-message="Retirer cet article du panier ?">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">
                                        <button type="submit" class="remove_btn">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M4 7h16"></path>
                                                <path d="M9 7V4h6v3"></path>
                                                <path d="m7 7 1 13h8l1-13"></path>
                                            </svg>
                                            Retirer
                                        </button>
                                    </form>

                                    <details class="cart_alert_box">
                                        <summary class="cart_alert_summary">
                                            <span class="cart_alert_summary_icon" aria-hidden="true"><?= renderUiIcon('alert') ?></span>
                                            <span>Signaler un souci sur cet article</span>
                                        </summary>

                                        <form method="post" action="index.php?controller=shop&action=reportAlert" class="cart_alert_form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="source_context" value="cart">
                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                            <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                                            <input type="hidden" name="priority" value="medium">
                                            <input type="hidden" name="redirect" value="index.php?controller=shop&action=cart">

                                            <label>
                                                Type d’alerte
                                                <select name="type">
                                                    <option value="missing_product">Absent du frigo</option>
                                                    <option value="stock_mismatch">Stock incohérent</option>
                                                    <option value="wrong_variant">Mauvaise variante</option>
                                                    <option value="damaged_product">Produit abîmé</option>
                                                    <option value="manual_check_required">Contrôle manuel</option>
                                                </select>
                                            </label>

                                            <label>
                                                Détail
                                                <textarea
                                                        name="message"
                                                        placeholder="Exemple : affiché disponible mais plus rien dans le frigo."
                                                ></textarea>
                                            </label>

                                            <div class="cart_alert_submit_wrap">
                                                <button type="submit" class="cart_alert_submit">Envoyer le signalement</button>
                                            </div>
                                        </form>
                                    </details>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="cart_summary">
                    <div class="cart_summary_card">
                        <div class="cart_summary_head">
                            <span class="section_kicker">Résumé</span>
                            <h3>Ta commande</h3>
                            <p>Un dernier contrôle du stock sera effectué.</p>
                        </div>

                        <div class="cart_summary_line">
                            <span>Articles</span>
                            <strong><?= $itemCount ?></strong>
                        </div>

                        <div class="cart_summary_line">
                            <span>Sous-total</span>
                            <strong><?= $subtotalFormatted ?></strong>
                        </div>

                            <div class="cart_summary_note cart_summary_steps">
                                <p><span>1</span> Vérification du stock</p>
                                <p><span>2</span> Enregistrement immédiat</p>
                                <p><span>3</span> Confirmation de la commande</p>
                        </div>

                        <div class="cart_summary_actions">
                            <form method="post" action="index.php?controller=shop&action=checkout" class="checkout_form checkout_form_full">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" class="checkout_btn checkout_btn_full">
                                    Confirmer la commande
                                    <span aria-hidden="true">→</span>
                                </button>
                            </form>

                            <form method="post" action="index.php?controller=shop&action=clearCart" class="clear_cart_form cart_summary_mobile_clear">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" class="clear_cart_btn cart_summary_mobile_clear_btn">Vider le panier</button>
                            </form>

                            <a class="home_btn home_btn_secondary cart_continue_btn" href="index.php?controller=shop&action=index">
                                Continuer mes achats
                            </a>
                        </div>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
