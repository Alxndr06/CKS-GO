<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Création</span>
            <h2>Ajouter un produit à la boutique</h2>
            <p>
                Crée un produit complet avec sa première variante, son stock initial et son image.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <form
                    method="post"
                    action="index.php?controller=shop&action=createProduct"
                    enctype="multipart/form-data"
                    onsubmit="return confirm('Confirmer la création de ce produit ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="section_heading compact">
                    <h3>Produit</h3>
                </div>

                <label for="product_name">Nom du produit</label>
                <input
                        type="text"
                        name="product_name"
                        id="product_name"
                        placeholder="Ex: Café en grains"
                        required
                >

                <label for="product_description">Description</label>
                <textarea
                        name="product_description"
                        id="product_description"
                        placeholder="Décris le produit"
                ></textarea>

                <label for="category_id">Catégorie</label>
                <select name="category_id" id="category_id">
                    <option value="">Aucune catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="image_file">Image du produit</label>
                <input
                        type="file"
                        name="image_file"
                        id="image_file"
                        accept=".jpg,.jpeg,.png,.webp,.gif"
                >
                <p class="form_help_text">
                    Formats acceptés : jpg, jpeg, png, webp, gif.
                </p>

                <label class="checkbox_line">
                    <input type="checkbox" name="product_is_active" value="1" checked>
                    Produit actif
                </label>

                <div class="section_heading compact">
                    <h3>Première variante</h3>
                </div>

                <label for="variant_name">Nom de la variante</label>
                <input
                        type="text"
                        name="variant_name"
                        id="variant_name"
                        placeholder="Ex: Format standard"
                        required
                >

                <label for="variant_flavor">Flavor / goût</label>
                <input
                        type="text"
                        name="variant_flavor"
                        id="variant_flavor"
                        placeholder="Ex: Vanille"
                >

                <label for="variant_price">Prix</label>
                <input
                        type="number"
                        name="variant_price"
                        id="variant_price"
                        min="0"
                        step="0.01"
                        placeholder="Ex: 1.50"
                        required
                >

                <label for="variant_stock">Stock initial</label>
                <input
                        type="number"
                        name="variant_stock"
                        id="variant_stock"
                        min="0"
                        value="0"
                        required
                >

                <label class="checkbox_line">
                    <input type="checkbox" name="variant_is_active" value="1" checked>
                    Variante active
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Créer le produit</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=manageShop">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>