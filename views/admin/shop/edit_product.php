<?php
require_once __DIR__ . '/../../partials/header.php';
$productImage = resolvePublicImageFilename($product['image'] ?? '');
?>

    <main class="main_part admin_dashboard_page admin_shop_form_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Boutique</span>
            <h2>Modifier le produit</h2>
            <p>
                Mets à jour les informations générales du produit dans une vue admin compacte
                et cohérente avec le reste du catalogue.
            </p>
        </section>

        <section class="showp_toolbar" aria-label="Navigation modification produit">
            <div class="showp_toolbar_row">
                <div class="showp_toolbar_left">
                    <span class="showp_toolbar_label">Produit en cours d’édition</span>
                    <span class="showp_toolbar_count">#<?= (int) $product['id'] ?></span>
                </div>

                <div class="showp_toolbar_actions">
                    <a
                            class="showp_action_link showp_action_link_soft"
                            href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $product['id'] ?>"
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
                    method="post"
                    action="index.php?controller=shop&action=updateProduct"
                    enctype="multipart/form-data"
                    class="shopf_form"
                    data-confirm-message="Confirmer la modification de ce produit ?"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($product['image'] ?? '') ?>">

                <div class="shopf_grid">
                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Produit</span>
                            <h3>Informations générales</h3>
                            <p>Nom, catégorie, description et état global du produit.</p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="name">Nom du produit *</label>
                            <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    value="<?= htmlspecialchars($product['name']) ?>"
                                    required
                                    maxlength="150"
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="category_id">Catégorie</label>
                            <select name="category_id" id="category_id">
                                <option value="">Aucune catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option
                                            value="<?= (int) $category['id'] ?>"
                                            <?= ((int) ($product['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="description">Description</label>
                            <textarea
                                    name="description"
                                    id="description"
                                    rows="6"
                                    placeholder="Décris rapidement le produit."
                            ><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>

                        <label class="shopf_checkbox" for="product-active">
                            <input
                                    type="checkbox"
                                    id="product-active"
                                    name="is_active"
                                    value="1"
                                    <?= ((int) $product['is_active'] === 1) ? 'checked' : '' ?>
                            >
                            <span>
                            Produit actif
                            <small>Le produit reste visible et exploitable dans la boutique.</small>
                        </span>
                        </label>

                        <div class="shopf_visibility_group" data-product-visibility>
                            <span class="shopf_visibility_title">Audience du produit</span>

                            <label class="shopf_checkbox" for="visible-to-guests">
                                <input
                                        type="checkbox"
                                        id="visible-to-guests"
                                        name="visible_to_guests"
                                        <?= ($product['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>
                                        data-visible-to-guests
                                >
                                <span>
                                    Visible sans connexion
                                    <small>Le produit apparaît aussi pour les visiteurs qui ne sont pas connectés.</small>
                                </span>
                            </label>

                            <label class="shopf_checkbox" for="staff-only">
                                <input
                                        type="checkbox"
                                        id="staff-only"
                                        name="staff_only"
                                        <?= ($product['visibility'] ?? 'public') === 'admin_only' ? 'checked' : '' ?>
                                        data-staff-only
                                >
                                <span>
                                    Réservé au staff
                                    <small>Seuls les membres de l’équipe ayant accès à Gestion pourront voir ce produit.</small>
                                </span>
                            </label>
                        </div>
                    </article>

                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Image</span>
                            <h3>Visuel du produit</h3>
                            <p>Conserve l’image actuelle ou remplace-la par un nouveau fichier.</p>
                        </div>

                        <div class="admin_current_image_block">
                            <p class="admin_current_image_label">Image actuelle</p>

                            <div class="admin_current_image_preview">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($productImage) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                >
                            </div>

                            <p class="admin_current_image_name">
                                Fichier actuel :
                                <strong><?= htmlspecialchars(trim((string)($product['image'] ?? '')) ?: 'Aucune image spécifique') ?></strong>
                            </p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="image_file">Nouvelle image</label>
                            <input
                                    type="file"
                                    name="image"
                                    id="image_file"
                                    accept=".jpg,.jpeg,.png,.webp,.gif"
                            >
                            <p class="form_help_text">
                                Laisse vide pour conserver l’image actuelle.
                            </p>
                        </div>
                    </article>
                </div>

                <div class="shopf_actions">
                    <a
                            class="showp_btn showp_btn_soft"
                            href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $product['id'] ?>"
                    >
                        Annuler
                    </a>

                    <button type="submit" class="showp_btn showp_btn_primary">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
