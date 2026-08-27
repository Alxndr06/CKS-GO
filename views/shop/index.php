<?php
$pageStylesheets = array_merge($pageStylesheets ?? [], ['shop-variant-experiment.css']);
$pageScripts = array_merge($pageScripts ?? [], ['shop-variant-experiment.js']);
require_once __DIR__ . '/../partials/header.php';

$quickCartItemCount = (int)($quickCart['item_count'] ?? 0);
$quickCartSubtotal = (float)($quickCart['subtotal'] ?? 0);
$quickCartSubtotalFormatted = number_format($quickCartSubtotal, 2, ',', ' ') . ' €';
$quickCartItems = (array)($quickCart['items'] ?? []);
$quickCartTotalLines = count($quickCartItems);
$quickCartCollapsedLines = 2;
$quickCartExpandedVisibleLines = 3;
$productCount = count($products ?? []);
$activeFilterCount = (!empty($categorySlug) ? 1 : 0) + (!empty($q) ? 1 : 0);

$csrfToken = getCsrfToken();

$currentShopParams = [
        'controller' => 'shop',
        'action' => 'index',
];

if (!empty($categorySlug)) {
    $currentShopParams['cat'] = $categorySlug;
}

if (!empty($q)) {
    $currentShopParams['q'] = $q;
}

$currentShopUrl = 'index.php?' . http_build_query($currentShopParams);
?>

    <main class="main_part shop_page shop_page_redesign shop_variant_experiment">
        <form
                class="shop_filters shop_filters_redesign is_compact"
                method="get"
                action="index.php"
                aria-label="Rechercher et filtrer les produits"
                data-shop-live-search-form
                data-initial-query="<?= htmlspecialchars($q ?? '') ?>"
        >
            <input type="hidden" name="controller" value="shop">
            <input type="hidden" name="action" value="index">

            <?php if (!empty($categorySlug)): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($categorySlug) ?>">
            <?php endif; ?>

            <div class="search_row shop_search_row">
                <label class="shop_search_field">
                    <span class="visually_hidden">Rechercher un produit</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m16.2 16.2 4 4"></path>
                    </svg>
                    <input
                            type="search"
                            name="q"
                            value="<?= htmlspecialchars($q ?? '') ?>"
                            placeholder="Rechercher un produit…"
                            autocomplete="off"
                            data-shop-live-search
                    >
                </label>

                <a
                        class="shop_filter_reset"
                        href="index.php?controller=shop&action=index"
                        data-shop-search-reset
                        <?= $activeFilterCount > 0 ? '' : 'hidden' ?>
                >
                    Effacer
                </a>
            </div>

            <div class="cat_pills">
                <?php
                $allProductsParams = [
                        'controller' => 'shop',
                        'action' => 'index',
                ];

                if (!empty($q)) {
                    $allProductsParams['q'] = $q;
                }
                ?>
                <a
                        href="index.php?<?= htmlspecialchars(http_build_query($allProductsParams)) ?>"
                        class="pill <?= empty($categorySlug) ? 'active' : '' ?>"
                >
                    Tout
                </a>

                <?php foreach ($cats as $c): ?>
                    <?php
                    $categoryParams = [
                            'controller' => 'shop',
                            'action' => 'index',
                            'cat' => $c['slug'],
                    ];

                    if (!empty($q)) {
                        $categoryParams['q'] = $q;
                    }
                    ?>
                    <a
                            class="pill <?= (!empty($categorySlug) && $categorySlug === $c['slug']) ? 'active' : '' ?>"
                            href="index.php?<?= htmlspecialchars(http_build_query($categoryParams)) ?>"
                    >
                        <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="shop_catalog_layout <?= isUserLoggedIn() ? 'has_quick_cart' : 'is_guest' ?>">
        <section class="shop_catalog_panel">
            <div class="shop_catalog_heading">
                <h1 data-shop-catalog-title><?= !empty($q) ? 'Résultats pour « ' . htmlspecialchars($q) . ' »' : 'Tous les produits' ?></h1>
                <div class="shop_catalog_summary">
                    <span class="shop_result_count" data-shop-result-count>
                        <?= $productCount ?> résultat<?= $productCount > 1 ? 's' : '' ?>
                    </span>
                    <p>Les disponibilités s’ajustent à la variante choisie.</p>
                </div>
            </div>

        <div class="product_grid" data-shop-product-grid>
            <?php if (empty($products)): ?>
                <div class="empty_state shop_empty_state">
                    <span class="shop_empty_icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m16.2 16.2 4 4"></path>
                        </svg>
                    </span>
                    <h3>Aucun produit trouvé</h3>
                    <p>Essaie avec une autre catégorie ou une autre recherche.</p>
                    <a href="index.php?controller=shop&action=index">Voir tous les produits</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    $productId = (int)($p['id'] ?? 0);
                    $vars = $productVariants[$productId] ?? [];

                    $availableVars = array_values(array_filter(
                            $vars,
                            static fn(array $v): bool => (int)($v['is_active'] ?? 0) === 1 && (int)($v['stock_quantity'] ?? 0) > 0
                    ));

                    $hasAvailableVariants = !empty($availableVars);
                    $fallbackProductImage = resolvePublicImageFilename($p['image'] ?? null);

                    $initialVariant = $hasAvailableVariants
                            ? $availableVars[0]
                            : (!empty($vars) ? $vars[0] : null);

                    $initialVariantName = trim((string)($initialVariant['name'] ?? ''));
                    $initialVariantFlavor = trim((string)($initialVariant['flavor'] ?? ''));
                    $initialVariantLabel = $initialVariantFlavor !== ''
                            ? $initialVariantFlavor
                            : ($initialVariantName !== '' ? $initialVariantName : 'Variante');

                    $isVariantPanelExpanded = false;

                    $initialVariantImage = resolvePublicImageFilename(
                            $initialVariant['image'] ?? null,
                            $fallbackProductImage
                    );

                    $initialPrice = $initialVariant
                            ? number_format((float)($initialVariant['price'] ?? 0), 2, ',', ' ') . ' €'
                            : (($p['min_price'] ?? null) !== null ? number_format((float)$p['min_price'], 2, ',', ' ') . ' €' : null);

                    $initialStockState = (
                            $initialVariant
                            && (int)($initialVariant['is_active'] ?? 0) === 1
                            && (int)($initialVariant['stock_quantity'] ?? 0) > 0
                    );

                    $productSearchParts = [
                            $p['name'] ?? '',
                            $p['description'] ?? '',
                            $p['category_name'] ?? '',
                    ];

                    foreach ($vars as $variant) {
                        $productSearchParts[] = $variant['name'] ?? '';
                        $productSearchParts[] = $variant['flavor'] ?? '';
                    }

                    $productSearchText = implode(' ', array_filter(
                            array_map(static fn($value): string => trim((string)$value), $productSearchParts)
                    ));
                    ?>

                    <article
                            class="product_card product_card_redesign shop_product_card_horizontal <?= !$hasAvailableVariants ? 'is_out_of_stock' : '' ?> <?= $isVariantPanelExpanded ? 'is_variant_expanded' : '' ?>"
                            data-shop-product-card
                            data-shop-variant-card
                            data-search-text="<?= htmlspecialchars($productSearchText, ENT_QUOTES) ?>"
                    >
                        <div class="product_image_wrap">
                            <img
                                    id="shop-product-image-<?= $productId ?>"
                                    class="shop_product_image"
                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($initialVariantImage) ?>"
                                    alt="<?= htmlspecialchars($p['name'] ?? '') ?>"
                                    loading="lazy"
                                    decoding="async"
                            >

                            <span
                                    id="shop-product-stock-badge-<?= $productId ?>"
                                    class="product_badge product_badge_stock product_stock_overlay <?= $initialStockState ? 'in_stock' : 'out_stock' ?>"
                            >
                                <?= $initialStockState ? 'En stock' : 'Rupture' ?>
                            </span>
                        </div>

                        <div class="product_card_body">
                            <div class="product_card_topline">
                                <div class="product_badges">
                                    <?php if (!empty($p['category_name'])): ?>
                                        <span class="product_badge product_badge_category">
                                        <?= htmlspecialchars($p['category_name']) ?>
                                    </span>
                                    <?php endif; ?>

                                    <?php if (isStaff() && (($p['visibility'] ?? 'public') === 'admin_only')): ?>
                                        <span class="product_badge product_badge_stock out_stock">Staff uniquement</span>
                                    <?php elseif (isStaff() && (($p['visibility'] ?? 'public') === 'authenticated')): ?>
                                        <span class="product_badge product_badge_category">Membres connectés</span>
                                    <?php endif; ?>

                                </div>

                                <?php if (isUserLoggedIn()): ?>
                                    <button
                                            type="button"
                                            class="shop_alert_trigger_btn"
                                            data-shop-alert-open
                                            data-alert-product-id="<?= $productId ?>"
                                            data-alert-product-name="<?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES) ?>"
                                            data-alert-options-id="shop-alert-options-<?= $productId ?>"
                                            aria-label="Signaler un souci sur ce produit"
                                            title="Signaler un souci sur ce produit"
                                    >
                                        <span class="shop_alert_trigger_icon" aria-hidden="true"><?= renderUiIcon('alert') ?></span>
                                    </button>

                                    <template id="shop-alert-options-<?= $productId ?>">
                                        <?php foreach ($vars as $v): ?>
                                            <?php
                                            $variantLabel = !empty($v['flavor']) ? $v['flavor'] : ($v['name'] ?? '');
                                            $variantLabel = !empty($variantLabel) ? $variantLabel : 'Variante';

                                            $variantStateParts = [];

                                            if ((int)($v['is_active'] ?? 0) !== 1) {
                                                $variantStateParts[] = 'inactive';
                                            }

                                            if ((int)($v['stock_quantity'] ?? 0) <= 0) {
                                                $variantStateParts[] = 'rupture';
                                            }

                                            $variantSuffix = !empty($variantStateParts)
                                                    ? ' (' . implode(', ', $variantStateParts) . ')'
                                                    : '';
                                            ?>
                                            <option
                                                    value="<?= (int)($v['id'] ?? 0) ?>"
                                                    <?= $initialVariant && (int)($initialVariant['id'] ?? 0) === (int)($v['id'] ?? 0) ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($variantLabel) ?>
                                                — <?= number_format((float)($v['price'] ?? 0), 2, ',', ' ') ?> €
                                                <?= htmlspecialchars($variantSuffix) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </template>
                                <?php endif; ?>
                            </div>

                            <div class="product_card_head">
                                <div>
                                    <h3><?= htmlspecialchars($p['name'] ?? '') ?></h3>
                                    <?php if (!empty($p['description'])): ?>
                                        <p class="desc"><?= htmlspecialchars($p['description']) ?></p>
                                    <?php else: ?>
                                        <p class="desc muted_text">Une référence disponible dans la boutique CKS GO.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="shop_product_card_controls">
                                    <?php if ($initialPrice !== null): ?>
                                        <p class="product_price">
                                            <span>Prix de la variante</span>
                                            <strong id="shop-product-price-<?= $productId ?>">
                                                <?= htmlspecialchars($initialPrice) ?>
                                            </strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($vars)): ?>
                                        <button
                                                type="button"
                                                class="shop_variant_panel_toggle"
                                                data-shop-variant-toggle
                                                aria-expanded="<?= $isVariantPanelExpanded ? 'true' : 'false' ?>"
                                                aria-controls="shop-product-variants-<?= $productId ?>"
                                        >
                                            <span>
                                                <small>Variante sélectionnée</small>
                                                <strong data-shop-selected-variant-label><?= htmlspecialchars($initialVariantLabel) ?></strong>
                                            </span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="m7 10 5 5 5-5"></path>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div
                                    class="shop_product_footer"
                                    id="shop-product-variants-<?= $productId ?>"
                                    data-shop-variant-panel
                                    aria-hidden="<?= $isVariantPanelExpanded ? 'false' : 'true' ?>"
                            >
                                <?php if (!empty($vars)): ?>
                                    <form
                                            method="post"
                                            action="index.php?controller=shop&action=addToCart"
                                            class="variant_form shop_add_form"
                                            data-shop-add-form
                                            data-product-id="<?= $productId ?>"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="product_id" value="<?= $productId ?>">

                                        <div class="shop_variant_choices_block">
                                            <div class="shop_variant_choices_heading">
                                                <span class="shop_field_label">Choisir une variante</span>
                                                <span><?= count($vars) ?> option<?= count($vars) > 1 ? 's' : '' ?></span>
                                            </div>

                                            <div
                                                    class="shop_variant_choices"
                                                    role="listbox"
                                                    aria-label="Variantes de <?= htmlspecialchars($p['name'] ?? '') ?>"
                                                    data-shop-variant-choices
                                            >
                                                <?php foreach ($vars as $v): ?>
                                                    <?php
                                                    $isActive = (int)($v['is_active'] ?? 0) === 1;
                                                    $stockQty = (int)($v['stock_quantity'] ?? 0);
                                                    $hasStock = $stockQty > 0;
                                                    $isDisabled = !$isActive || !$hasStock;
                                                    $variantId = (int)($v['id'] ?? 0);
                                                    $variantName = trim((string)($v['name'] ?? ''));
                                                    $variantFlavor = trim((string)($v['flavor'] ?? ''));
                                                    $variantLabel = $variantFlavor !== ''
                                                            ? $variantFlavor
                                                            : ($variantName !== '' ? $variantName : 'Variante');
                                                    $variantMeta = $variantFlavor !== '' && $variantName !== '' && $variantFlavor !== $variantName
                                                            ? $variantName
                                                            : '';
                                                    $variantImage = resolvePublicImageFilename(
                                                            $v['image'] ?? null,
                                                            $fallbackProductImage
                                                    );
                                                    $isSelected = $initialVariant && (int)($initialVariant['id'] ?? 0) === $variantId;
                                                    ?>
                                                    <button
                                                            type="button"
                                                            class="shop_variant_choice <?= $isSelected ? 'is_selected' : '' ?> <?= $isDisabled ? 'is_unavailable' : '' ?>"
                                                            role="option"
                                                            aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
                                                            data-shop-variant-choice
                                                            data-variant-id="<?= $variantId ?>"
                                                            data-variant-label="<?= htmlspecialchars($variantLabel, ENT_QUOTES) ?>"
                                                            <?= $isDisabled ? 'disabled' : '' ?>
                                                    >
                                                        <span class="shop_variant_choice_check" aria-hidden="true"></span>
                                                        <span class="shop_variant_choice_thumb" aria-hidden="true">
                                                            <img
                                                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($variantImage) ?>"
                                                                    alt=""
                                                                    loading="lazy"
                                                            >
                                                        </span>
                                                        <span class="shop_variant_choice_copy">
                                                            <strong><?= htmlspecialchars($variantLabel) ?></strong>
                                                            <?php if ($variantMeta !== ''): ?>
                                                                <small><?= htmlspecialchars($variantMeta) ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="shop_variant_choice_state">
                                                            <strong><?= number_format((float)($v['price'] ?? 0), 2, ',', ' ') ?> €</strong>
                                                            <small class="<?= $isDisabled ? 'is_unavailable' : '' ?>">
                                                                <?php if (!$isActive): ?>Inactive
                                                                <?php elseif (!$hasStock): ?>Rupture
                                                                <?php else: ?><?= $stockQty ?> en stock
                                                                <?php endif; ?>
                                                            </small>
                                                        </span>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <label class="visually_hidden" for="shop-product-variant-<?= $productId ?>">Variante</label>
                                        <select
                                                id="shop-product-variant-<?= $productId ?>"
                                                name="variant_id"
                                                required
                                                class="shop_variant_select shop_variant_select_native"
                                                data-product-id="<?= $productId ?>"
                                                data-fallback-image="<?= htmlspecialchars($fallbackProductImage) ?>"
                                                hidden
                                                aria-hidden="true"
                                        >
                                            <?php foreach ($vars as $v): ?>
                                                <?php
                                                $isActive = (int)($v['is_active'] ?? 0) === 1;
                                                $stockQty = (int)($v['stock_quantity'] ?? 0);
                                                $hasStock = $stockQty > 0;
                                                $isDisabled = !$isActive || !$hasStock;
                                                $variantLabel = !empty($v['flavor']) ? $v['flavor'] : ($v['name'] ?? '');
                                                $variantLabel = !empty($variantLabel) ? $variantLabel : 'Variante';
                                                $variantImage = resolvePublicImageFilename(
                                                        $v['image'] ?? null,
                                                        $fallbackProductImage
                                                );
                                                $isSelected = $initialVariant && (int)($initialVariant['id'] ?? 0) === (int)($v['id'] ?? 0);
                                                ?>
                                                <option
                                                        value="<?= (int)($v['id'] ?? 0) ?>"
                                                        data-price="<?= htmlspecialchars(number_format((float)($v['price'] ?? 0), 2, ',', ' ')) ?> €"
                                                        data-stock="<?= $stockQty ?>"
                                                        data-active="<?= $isActive ? '1' : '0' ?>"
                                                        data-image="<?= htmlspecialchars($variantImage) ?>"
                                                        <?= $isDisabled ? 'disabled' : '' ?>
                                                        <?= $isSelected ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($variantLabel) ?>
                                                    — <?= number_format((float)($v['price'] ?? 0), 2, ',', ' ') ?> €
                                                    <?php if (!$isActive): ?> (Inactive)<?php endif; ?>
                                                    <?php if ($isActive && !$hasStock): ?> (Rupture)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <p
                                                class="shop_stock_hint <?= $initialStockState ? 'is_available' : 'is_unavailable' ?>"
                                                id="shop-product-stock-hint-<?= $productId ?>"
                                                data-shop-stock-hint
                                        >
                                            <?= $initialStockState
                                                    ? (int)($initialVariant['stock_quantity'] ?? 0) . ' unité' . ((int)($initialVariant['stock_quantity'] ?? 0) > 1 ? 's' : '') . ' disponible' . ((int)($initialVariant['stock_quantity'] ?? 0) > 1 ? 's' : '')
                                                    : 'Cette variante est indisponible' ?>
                                        </p>

                                        <div class="product_actions">
                                            <div class="qty_field shop_quantity_control" data-quantity-control>
                                                <span class="shop_field_label">Quantité</span>
                                                <div class="shop_quantity_stepper">
                                                    <button type="button" data-qty-minus aria-label="Diminuer la quantité">−</button>
                                                    <input
                                                            type="number"
                                                            name="quantity"
                                                            min="1"
                                                            max="<?= max(1, (int)($initialVariant['stock_quantity'] ?? 1)) ?>"
                                                            value="1"
                                                            inputmode="numeric"
                                                            aria-label="Quantité"
                                                    >
                                                    <button type="button" data-qty-plus aria-label="Augmenter la quantité">+</button>
                                                </div>
                                            </div>

                                            <button
                                                    type="submit"
                                                    class="shop_add_button"
                                                    id="shop-product-submit-<?= $productId ?>"
                                                    data-default-label="<?= $initialStockState ? 'Ajouter au panier' : 'Indisponible' ?>"
                                                    data-added-label="Ajouté"
                                                    <?= !$initialStockState ? 'disabled' : '' ?>
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M5 8h14l-1 12H6L5 8Z"></path>
                                                    <path d="M9 9V6a3 3 0 0 1 6 0v3"></path>
                                                </svg>
                                                <span data-shop-submit-label><?= $initialStockState ? 'Ajouter au panier' : 'Indisponible' ?></span>
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <p class="muted shop_product_unavailable">Aucune variante n’est disponible actuellement.</p>
                                <?php endif; ?>

                                <?php if (!isUserLoggedIn()): ?>
                                    <p class="shop_alert_login_hint">
                                        Connecte-toi pour signaler un souci sur ce produit.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($products)): ?>
                <div class="empty_state shop_empty_state shop_live_empty_state" data-shop-live-empty hidden>
                    <span class="shop_empty_icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m16.2 16.2 4 4"></path>
                        </svg>
                    </span>
                    <h3>Aucun produit ne correspond</h3>
                    <p>Modifie ta recherche ou efface le filtre pour retrouver tous les produits.</p>
                </div>
            <?php endif; ?>
        </div>
        </section>

        <?php if (isUserLoggedIn()): ?>
            <aside
                    class="shop_quick_cart shop_quick_cart_redesign <?= $quickCartItemCount > 0 ? 'has_items' : 'is_empty' ?>"
                    data-shop-quick-cart
                    data-remove-url="index.php?controller=shop&action=removeCartItem"
                    data-total-lines="<?= $quickCartTotalLines ?>"
                    data-collapsed-lines="<?= $quickCartCollapsedLines ?>"
                    data-expanded-visible-lines="<?= $quickCartExpandedVisibleLines ?>"
                    data-csrf-token="<?= htmlspecialchars($csrfToken) ?>"
            >
                <div class="shop_quick_cart_content">
                    <div class="shop_quick_cart_head">
                        <div class="shop_quick_cart_title">
                            <span class="shop_quick_cart_icon shop_quick_cart_icon_static" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M5 8h14l-1 12H6L5 8Z"></path>
                                    <path d="M9 9V6a3 3 0 0 1 6 0v3"></path>
                                </svg>
                            </span>
                            <a
                                    class="shop_quick_cart_icon shop_quick_cart_icon_link"
                                    href="index.php?controller=shop&amp;action=cart"
                                    aria-label="Ouvrir mon panier"
                                    title="Voir mon panier"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M5 8h14l-1 12H6L5 8Z"></path>
                                    <path d="M9 9V6a3 3 0 0 1 6 0v3"></path>
                                </svg>
                            </a>
                            <div class="shop_quick_cart_meta">
                                <span class="shop_quick_cart_label">Mon panier</span>
                                <strong class="shop_quick_cart_count" data-shop-cart-count>
                                    <?= $quickCartItemCount ?> article<?= $quickCartItemCount > 1 ? 's' : '' ?>
                                </strong>
                            </div>
                        </div>
                        <strong class="shop_quick_cart_total" data-shop-cart-subtotal><?= $quickCartSubtotalFormatted ?></strong>
                    </div>

                    <div class="shop_quick_cart_items_wrap">
                        <ul class="shop_quick_cart_items" data-shop-cart-items>
                            <?php if (empty($quickCartItems)): ?>
                                <li class="shop_quick_cart_item shop_quick_cart_item_empty">
                                    <span>Ton panier est encore vide.</span>
                                    <small>Ajoute un produit pour commencer.</small>
                                </li>
                            <?php else: ?>
                                <?php foreach ($quickCartItems as $item): ?>
                                    <li class="shop_quick_cart_item" data-cart-item-id="<?= (int)($item['cart_item_id'] ?? 0) ?>">
                                        <div class="shop_quick_cart_thumb">
                                            <img
                                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars(resolvePublicImageFilename($item['product_image'] ?? null)) ?>"
                                                    alt=""
                                                    loading="lazy"
                                            >
                                        </div>
                                        <div class="shop_quick_cart_item_main">
                                            <strong><?= htmlspecialchars($item['product_name'] ?? '') ?></strong>
                                            <span><?= htmlspecialchars($item['display_variant'] ?? 'Variante') ?> · ×<?= (int)($item['quantity'] ?? 0) ?></span>
                                        </div>

                                        <div class="shop_quick_cart_item_side">
                                            <span class="shop_quick_cart_item_qty"><?= number_format((float)($item['line_total'] ?? 0), 2, ',', ' ') ?> €</span>
                                            <button
                                                    type="button"
                                                    class="shop_quick_cart_item_remove"
                                                    data-shop-cart-remove="<?= (int)($item['cart_item_id'] ?? 0) ?>"
                                                    aria-label="Retirer <?= htmlspecialchars($item['product_name'] ?? 'ce produit') ?> du panier"
                                                    title="Retirer ce produit"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M4 7h16"></path>
                                                    <path d="M9 7V4h6v3"></path>
                                                    <path d="m7 7 1 13h8l1-13"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="shop_quick_cart_actions">
                        <a class="shop_quick_cart_link" href="index.php?controller=shop&action=cart">Voir le panier</a>

                        <form
                                class="shop_quick_cart_checkout_form"
                                method="post"
                                action="index.php?controller=shop&action=checkout"
                                data-confirm-message="Confirmer cette commande ?"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="shop_quick_cart_checkout_btn" <?= $quickCartItemCount <= 0 ? 'disabled' : '' ?>>
                                Commander
                            </button>
                        </form>
                    </div>

                </div>
            </aside>
        <?php endif; ?>
        </div>
    </main>

<?php if (isUserLoggedIn()): ?>
    <div class="shop_alert_modal" data-shop-alert-modal hidden>
        <div class="shop_alert_modal_backdrop" data-shop-alert-backdrop></div>

        <div
                class="shop_alert_modal_dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="shop-alert-modal-title"
                aria-describedby="shop-alert-modal-description"
                data-shop-alert-dialog
        >
            <div class="shop_alert_modal_head">
                <div class="shop_alert_modal_intro">
                    <p class="shop_alert_modal_eyebrow">Signalement</p>
                    <h3 id="shop-alert-modal-title" class="shop_alert_modal_title">Signaler un souci</h3>
                    <p id="shop-alert-modal-description" class="shop_alert_modal_hint">
                        Produit absent, stock incohérent, mauvaise variante…
                    </p>
                </div>

                <button
                        type="button"
                        class="shop_alert_modal_close"
                        data-shop-alert-close
                        aria-label="Fermer le signalement"
                        title="Fermer"
                >
                    ×
                </button>
            </div>

            <form
                    method="post"
                    action="index.php?controller=shop&action=reportAlert"
                    class="shop_alert_modal_form"
                    data-shop-alert-form
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="source_context" value="shop_product">
                <input type="hidden" name="product_id" value="" data-shop-alert-product-id>
                <input type="hidden" name="priority" value="medium">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentShopUrl) ?>">

                <div class="shop_alert_modal_product">
                    <span class="shop_alert_modal_product_label">Produit concerné</span>
                    <strong class="shop_alert_modal_product_name" data-shop-alert-product-name>Produit</strong>
                </div>

                <label class="shop_alert_modal_field" data-shop-alert-variant-field hidden>
                    Variante concernée
                    <select name="variant_id" data-shop-alert-variant-select></select>
                </label>

                <label class="shop_alert_modal_field">
                    Type d’alerte
                    <select name="type">
                        <option value="missing_product">Absent du frigo</option>
                        <option value="stock_mismatch">Stock incohérent</option>
                        <option value="wrong_variant">Mauvaise variante</option>
                        <option value="damaged_product">Produit abîmé</option>
                        <option value="manual_check_required">Contrôle manuel</option>
                    </select>
                </label>

                <label class="shop_alert_modal_field">
                    Détail
                    <textarea
                            name="message"
                            placeholder="Exemple : affiché disponible mais plus rien dans le frigo."
                    ></textarea>
                </label>

                <div class="shop_alert_modal_actions">
                    <button type="button" class="shop_alert_modal_secondary" data-shop-alert-close>Annuler</button>
                    <button type="submit" class="shop_alert_modal_submit">Envoyer le signalement</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <div class="shop_toast_stack" data-shop-toast-stack aria-live="polite" aria-atomic="true"></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
