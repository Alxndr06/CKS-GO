<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part shop_page">
        <section class="shop_intro">
            <div class="shop_intro_text">
                <span class="section_kicker">Boutique</span>
                <h2>La boutique CKS GO</h2>
                <p>
                    Parcours les produits disponibles, filtre par catégorie, choisis une variante
                    et ajoute-la rapidement à ton panier.
                </p>
            </div>
        </section>

        <form class="shop_filters" method="get" action="index.php">
            <input type="hidden" name="controller" value="shop">
            <input type="hidden" name="action" value="index">

            <div class="cat_pills">
                <a
                        href="index.php?controller=shop&action=index"
                        class="pill <?= empty($categorySlug) ? 'active' : '' ?>"
                >
                    Tout
                </a>

                <?php foreach ($cats as $c): ?>
                    <a
                            class="pill <?= (!empty($categorySlug) && $categorySlug === $c['slug']) ? 'active' : '' ?>"
                            href="index.php?controller=shop&action=index&cat=<?= htmlspecialchars($c['slug']) ?>"
                    >
                        <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="search_row">
                <input
                        type="text"
                        name="q"
                        value="<?= htmlspecialchars($q ?? '') ?>"
                        placeholder="Rechercher un produit…"
                >
                <button type="submit">Filtrer</button>
            </div>
        </form>

        <section class="product_grid">
            <?php if (empty($products)): ?>
                <div class="empty_state">
                    <h3>Aucun produit trouvé</h3>
                    <p>Essaie avec une autre catégorie ou une autre recherche.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    $productId = (int)$p['id'];
                    $vars = $productVariants[$productId] ?? [];

                    $availableVars = array_values(array_filter(
                            $vars,
                            static fn(array $v): bool =>
                                    (int)$v['is_active'] === 1 && (int)$v['stock_quantity'] > 0
                    ));

                    $hasAvailableVariants = !empty($availableVars);
                    $displayImage = !empty($p['image']) ? $p['image'] : 'php.png';

                    $initialVariant = $hasAvailableVariants
                            ? $availableVars[0]
                            : (!empty($vars) ? $vars[0] : null);

                    $initialPrice = $initialVariant
                            ? number_format((float)$initialVariant['price'], 2, ',', ' ') . ' €'
                            : ($p['min_price'] !== null ? number_format((float)$p['min_price'], 2, ',', ' ') . ' €' : null);

                    $initialStockState = (
                            $initialVariant &&
                            (int)$initialVariant['is_active'] === 1 &&
                            (int)$initialVariant['stock_quantity'] > 0
                    );
                    ?>

                    <article class="product_card <?= !$hasAvailableVariants ? 'is_out_of_stock' : '' ?>">
                        <div class="product_image_wrap">
                            <img
                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($displayImage) ?>"
                                    alt="<?= htmlspecialchars($p['name']) ?>"
                            >
                        </div>

                        <div class="product_card_body">
                            <div class="product_badges">
                                <?php if (!empty($p['category_name'])): ?>
                                    <span class="product_badge product_badge_category">
                                    <?= htmlspecialchars($p['category_name']) ?>
                                </span>
                                <?php endif; ?>

                                <span class="product_badge product_badge_stock <?= $initialStockState ? 'in_stock' : 'out_stock' ?>">
                                <?= $initialStockState ? 'Disponible' : 'Rupture' ?>
                            </span>
                            </div>

                            <div class="product_card_head">
                                <h3><?= htmlspecialchars($p['name']) ?></h3>

                                <?php if ($initialPrice !== null): ?>
                                    <p class="product_price">
                                        À partir de <strong><?= number_format((float)($p['min_price'] ?? 0), 2, ',', ' ') ?> €</strong>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($p['description'])): ?>
                                <p class="desc"><?= htmlspecialchars($p['description']) ?></p>
                            <?php else: ?>
                                <p class="desc muted_text">Aucune description disponible pour ce produit.</p>
                            <?php endif; ?>

                            <?php if (!empty($vars)): ?>
                                <form method="post" action="index.php?controller=shop&action=addToCart" class="variant_form">
                                    <input type="hidden" name="product_id" value="<?= $productId ?>">

                                    <label>
                                        Variante
                                        <select name="variant_id" required>
                                            <?php foreach ($vars as $v): ?>
                                                <?php
                                                $isActive = (int)$v['is_active'] === 1;
                                                $stockQty = (int)$v['stock_quantity'];
                                                $hasStock = $stockQty > 0;
                                                $isDisabled = !$isActive || !$hasStock;

                                                $variantLabel = !empty($v['flavor']) ? $v['flavor'] : $v['name'];
                                                $variantLabel = !empty($variantLabel) ? $variantLabel : 'Variante';

                                                $isSelected = $initialVariant && (int)$initialVariant['id'] === (int)$v['id'];
                                                ?>
                                                <option
                                                        value="<?= (int)$v['id'] ?>"
                                                        data-price="<?= htmlspecialchars(number_format((float)$v['price'], 2, ',', ' ')) ?>"
                                                        data-stock="<?= $stockQty ?>"
                                                        data-active="<?= $isActive ? '1' : '0' ?>"
                                                        <?= $isDisabled ? 'disabled' : '' ?>
                                                        <?= $isSelected ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($variantLabel) ?>
                                                    — <?= number_format((float)$v['price'], 2, ',', ' ') ?> €
                                                    <?php if (!$isActive): ?> (Inactive)<?php endif; ?>
                                                    <?php if ($isActive && !$hasStock): ?> (Rupture)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <div class="product_actions">
                                        <label class="qty_field">
                                            Qté
                                            <input type="number" name="quantity" min="1" value="1">
                                        </label>

                                        <button type="submit" <?= !$initialStockState ? 'disabled' : '' ?>>
                                            <?= $initialStockState ? 'Ajouter au panier' : 'Indisponible' ?>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p class="muted">Aucune variante disponible.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>