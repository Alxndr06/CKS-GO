<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Création</span>
            <h2>Ajouter une variante</h2>
            <p>
                Ajoute une nouvelle variante au produit
                <strong><?= htmlspecialchars($product['name']) ?></strong>.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <form
                method="post"
                action="index.php?controller=shop&action=createVariant"
                onsubmit="return confirm('Confirmer la création de cette variante ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

                <label for="name">Nom de la variante</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    placeholder="Ex: Format XL"
                    required
                >

                <label for="flavor">Flavor / goût</label>
                <input
                    type="text"
                    name="flavor"
                    id="flavor"
                    placeholder="Ex: Noisette"
                >

                <label for="price">Prix</label>
                <input
                    type="number"
                    name="price"
                    id="price"
                    min="0"
                    step="0.01"
                    placeholder="Ex: 2.50"
                    required
                >

                <label for="stock_quantity">Stock initial</label>
                <input
                    type="number"
                    name="stock_quantity"
                    id="stock_quantity"
                    min="0"
                    value="0"
                    required
                >

                <label class="checkbox_line">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Variante active
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Créer la variante</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=showAdminProduct&id=<?= (int)$product['id'] ?>">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>