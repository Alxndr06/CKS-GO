<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page admin_shop_form_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Boutique</span>
            <h2>Ajouter un produit</h2>
            <p>
                Crée un nouveau produit avec sa première variante dans une vue admin compacte,
                claire et cohérente avec le reste du catalogue.
            </p>
        </section>

        <section class="showp_toolbar" aria-label="Navigation création produit">
            <div class="showp_toolbar_row">
                <div class="showp_toolbar_left">
                    <span class="showp_toolbar_label">Création / configuration</span>
                    <span class="showp_toolbar_count">Produit + variante initiale</span>
                </div>

                <div class="showp_toolbar_actions">
                    <a
                            class="showp_action_link showp_action_link_soft"
                            href="index.php?controller=shop&action=manageShop"
                    >
                        Retour
                    </a>

                    <a
                            class="showp_action_link showp_action_link_primary"
                            href="index.php?controller=shop&action=allProducts"
                    >
                        Voir le catalogue
                    </a>
                </div>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <form
                    method="POST"
                    action="index.php?controller=shop&action=storeProduct"
                    enctype="multipart/form-data"
                    class="shopf_form"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="shopf_grid">
                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Produit</span>
                            <h3>Informations générales</h3>
                            <p>Nom, catégorie, description et image principale du produit.</p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="product-name">Nom du produit *</label>
                            <input
                                    type="text"
                                    id="product-name"
                                    name="name"
                                    required
                                    maxlength="150"
                                    placeholder="Ex. Coca-Cola"
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="product-category">Catégorie</label>
                            <select id="product-category" name="category_id">
                                <option value="">Aucune catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="product-description">Description</label>
                            <textarea
                                    id="product-description"
                                    name="description"
                                    rows="6"
                                    placeholder="Décris rapidement le produit."
                            ></textarea>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="product-image">Image produit</label>
                            <input
                                    type="file"
                                    id="product-image"
                                    name="image"
                                    accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                            >
                            <small class="shopf_helper_text">
                                Formats autorisés : JPG, PNG, WEBP, GIF — 5 Mo max.
                            </small>
                        </div>

                        <label class="shopf_checkbox" for="product-active">
                            <input type="checkbox" id="product-active" name="is_active" checked>
                            <span>
                            Produit actif
                            <small>Le produit pourra être affiché et utilisé immédiatement.</small>
                        </span>
                        </label>

                        <div class="shopf_visibility_group" data-product-visibility>
                            <span class="shopf_visibility_title">Audience du produit</span>

                            <label class="shopf_checkbox" for="visible-to-guests">
                                <input type="checkbox" id="visible-to-guests" name="visible_to_guests" checked data-visible-to-guests>
                                <span>
                                    Visible sans connexion
                                    <small>Le produit apparaît aussi pour les visiteurs qui ne sont pas connectés.</small>
                                </span>
                            </label>

                            <label class="shopf_checkbox" for="staff-only">
                                <input type="checkbox" id="staff-only" name="staff_only" data-staff-only>
                                <span>
                                    Réservé au staff
                                    <small>Seuls les membres de l’équipe ayant accès à Gestion pourront voir ce produit.</small>
                                </span>
                            </label>
                        </div>
                    </article>

                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Variante</span>
                            <h3>Variante initiale</h3>
                            <p>Première déclinaison créée en même temps que le produit.</p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-name">Nom de la variante *</label>
                            <input
                                    type="text"
                                    id="variant-name"
                                    name="variant_name"
                                    required
                                    maxlength="120"
                                    value="Format standard"
                                    placeholder="Ex. Canette 33cl"
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-flavor">Saveur / attribut</label>
                            <input
                                    type="text"
                                    id="variant-flavor"
                                    name="variant_flavor"
                                    maxlength="120"
                                    placeholder="Ex. Cola, citron, fraise"
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-sku">SKU</label>
                            <input
                                    type="text"
                                    id="variant-sku"
                                    name="variant_sku"
                                    maxlength="64"
                                    placeholder="Généré automatiquement si vide"
                            >
                            <small class="shopf_helper_text">Référence unique utilisée dans la recherche et les mouvements.</small>
                        </div>

                        <div class="shopf_subgrid">
                            <div class="form_group shopf_field">
                                <label for="variant-price">Prix (€) *</label>
                                <input
                                        type="number"
                                        id="variant-price"
                                        name="variant_price"
                                        step="0.01"
                                        min="0"
                                        required
                                        placeholder="0.00"
                                >
                            </div>

                            <div class="form_group shopf_field">
                                <label for="variant-stock">Stock initial *</label>
                                <input
                                        type="number"
                                        id="variant-stock"
                                        name="variant_stock"
                                        min="0"
                                        required
                                        value="0"
                                >
                            </div>

                            <div class="form_group shopf_field">
                                <label for="variant-low-stock">Seuil d’alerte</label>
                                <input
                                        type="number"
                                        id="variant-low-stock"
                                        name="variant_low_stock_threshold"
                                        min="0"
                                        value="5"
                                >
                            </div>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-sort-order">Ordre d’affichage</label>
                            <input
                                    type="number"
                                    id="variant-sort-order"
                                    name="variant_sort_order"
                                    min="1"
                                    value="1"
                                    placeholder="1"
                            >
                            <small class="shopf_helper_text">
                                1 = affichée en premier dans le produit.
                            </small>
                        </div>

                        <label class="shopf_checkbox" for="variant-active">
                            <input type="checkbox" id="variant-active" name="variant_is_active" checked>
                            <span>
                            Variante active
                            <small>La variante sera directement exploitable dans la boutique.</small>
                        </span>
                        </label>
                    </article>
                </div>

                <div class="shopf_actions">
                    <a
                            class="showp_btn showp_btn_soft"
                            href="index.php?controller=shop&action=manageShop"
                    >
                        Retour
                    </a>

                    <button type="submit" class="showp_btn showp_btn_primary">
                        Enregistrer le produit
                    </button>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
