<?php
require_once __DIR__ . '/../partials/header.php';
?>
<main class="main_part">
    <h2>Boutique</h2>

    <form class="shop_filters" method="get" action="index.php">
        <input type="hidden" name="controller" value="shop">
        <input type="hidden" name="action" value="index">

        <div class="cat_pills">
            <a href="index.php?controller=shop&action=index" class="pill <?= empty($categorySlug) ? 'active' : '' ?>">Tout</a>
            <?php foreach ($cats as $c): ?>
                <a class="pill <?= (!empty($categorySlug) && $categorySlug === $c['slug']) ? 'active' : '' ?>"
                   href="index.php?controller=shop&action=index&cat=<?= htmlspecialchars($c['slug']) ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="search_row">
            <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Rechercher un produit…">
            <button type="submit">Filtrer</button>
        </div>
    </form>

    <section class="product_grid">
        <?php if (empty($products)): ?>
            <p>Aucun produit trouvé.</p>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <article class="product_card">
                    <img src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($p['image'] ?? 'php.png') ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <h4><?= htmlspecialchars($p['name']) ?></h4>
                    <?php if (!empty($p['description'])): ?><p class="desc"><?= htmlspecialchars($p['description']) ?></p><?php endif; ?>

                    <?php $vars = $productVariants[$p['id']] ?? []; ?>
                    <?php if ($vars): ?>
                        <form method="post" action="index.php?controller=shop&action=addToCart" class="variant_form">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <label>
                                Variante (goût)
                                <select name="variant_id" required>
                                    <?php foreach ($vars as $v): ?>
                                        <option value="<?= (int)$v['id'] ?>" <?= ((int)$v['stock_quantity'] <= 0 ? 'disabled' : '') ?>>
                                            <?= htmlspecialchars($v['flavor'] ?: $v['name']) ?> — <?= number_format($v['price'], 2, ',', ' ') ?> €
                                            <?= ((int)$v['stock_quantity'] <= 0 ? ' (Rupture)' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Qté <input type="number" name="quantity" min="1" value="1"></label>
                            <button type="submit">Ajouter au panier</button>
                        </form>
                    <?php else: ?>
                        <p class="muted">Aucune variante disponible.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
