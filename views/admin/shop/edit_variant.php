<?php
require_once __DIR__ . '/../../partials/header.php';

$currentImage = trim((string) ($variant['image'] ?? ''));
if ($currentImage === '') {
    $currentImage = trim((string) ($variant['product_image'] ?? 'php.png'));
}
?>

<main class="main_part admin_dashboard_page admin_shop_form_page">
    <section class="admin_dashboard_intro">
        <span class="section_kicker">Boutique</span>
        <h2>Modifier une variante</h2>
        <p>
            Ajuste les informations, le visuel et la publication de cette variante
            depuis une interface compacte et propre.
        </p>
    </section>

    <section class="showp_toolbar" aria-label="Navigation édition variante">
        <div class="showp_toolbar_row">
            <div class="showp_toolbar_left">
                <span class="showp_toolbar_label">Produit parent</span>
                <span class="showp_toolbar_count">Produit #<?= (int) $variant['product_id'] ?></span>
            </div>

            <div class="showp_toolbar_actions">
                <a
                        class="showp_action_link showp_action_link_soft"
                        href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $variant['product_id'] ?>"
                >
                    Retour
                </a>

                <a
                        class="showp_action_link showp_action_link_primary"
                        href="index.php?controller=shop&action=editProduct&id=<?= (int) $variant['product_id'] ?>"
                >
                    Modifier le produit
                </a>
            </div>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <article class="showp_summary_card shopf_summary_card">
            <div class="showp_summary_media">
                <img
                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($currentImage) ?>"
                        alt="<?= htmlspecialchars($variant['name']) ?>"
                >
            </div>

            <div class="showp_summary_body">
                <div class="showp_summary_top">
                    <div class="showp_summary_title_wrap">
                        <h3><?= htmlspecialchars($variant['product_name']) ?></h3>

                        <div class="showp_summary_badges">
                            <?php if (!empty($variant['category_name'])): ?>
                                <span class="showp_badge"><?= htmlspecialchars($variant['category_name']) ?></span>
                            <?php endif; ?>

                            <?php if ((int) $variant['is_active'] === 1): ?>
                                <span class="showp_badge showp_badge_success">Variante active</span>
                            <?php else: ?>
                                <span class="showp_badge showp_badge_warning">Variante inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <p class="shopf_summary_meta">
                    <span><strong>Produit :</strong> #<?= (int) $variant['product_id'] ?></span>
                    <span><strong>Variante :</strong> #<?= (int) $variant['id'] ?></span>
                    <span><strong>Prix :</strong> <?= number_format((float) $variant['price'], 2, ',', ' ') ?> €</span>
                    <span><strong>Ordre :</strong> <?= (int) ($variant['sort_order'] ?? 0) ?></span>
                </p>
            </div>
        </article>
    </section>

    <section class="admin_dashboard_section">
        <form
                method="POST"
                action="index.php?controller=shop&action=updateVariant"
                enctype="multipart/form-data"
                class="shopf_form"
                data-confirm-message="Confirmer la modification de cette variante ?"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>">
            <input type="hidden" name="product_id" value="<?= (int) $variant['product_id'] ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($variant['image'] ?? '') ?>">

            <div class="shopf_grid">
                <article class="shopf_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Variante</span>
                        <h3>Détails</h3>
                        <p>Nom, attribut, prix, ordre d’affichage et état de publication.</p>
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-name">Nom *</label>
                        <input
                                type="text"
                                id="variant-name"
                                name="name"
                                value="<?= htmlspecialchars($variant['name']) ?>"
                                required
                                maxlength="120"
                        >
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-flavor">Saveur / attribut</label>
                        <input
                                type="text"
                                id="variant-flavor"
                                name="flavor"
                                value="<?= htmlspecialchars($variant['flavor'] ?? '') ?>"
                                maxlength="120"
                                placeholder="Ex. Cola, citron, fraise"
                        >
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-sku">SKU</label>
                        <input
                                type="text"
                                id="variant-sku"
                                name="sku"
                                value="<?= htmlspecialchars((string)($variant['sku'] ?? '')) ?>"
                                maxlength="64"
                                placeholder="Généré automatiquement si vide"
                        >
                    </div>

                    <div class="shopf_subgrid">
                        <div class="form_group shopf_field">
                            <label for="variant-price">Prix (€) *</label>
                            <input
                                    type="number"
                                    id="variant-price"
                                    name="price"
                                    value="<?= htmlspecialchars(number_format((float) $variant['price'], 2, '.', '')) ?>"
                                    step="0.01"
                                    min="0"
                                    required
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-sort-order">Ordre *</label>
                            <input
                                    type="number"
                                    id="variant-sort-order"
                                    name="sort_order"
                                    value="<?= (int) ($variant['sort_order'] ?? 0) ?>"
                                    min="0"
                                    step="1"
                                    required
                            >
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-low-stock">Seuil d’alerte</label>
                            <input
                                    type="number"
                                    id="variant-low-stock"
                                    name="low_stock_threshold"
                                    value="<?= (int)($variant['low_stock_threshold'] ?? 5) ?>"
                                    min="0"
                                    step="1"
                                    required
                            >
                        </div>
                    </div>

                    <small class="shopf_helper_text">
                        La variante avec l’ordre le plus petit apparaîtra en premier.
                    </small>

                    <label class="shopf_checkbox" for="variant-active">
                        <input
                                type="checkbox"
                                id="variant-active"
                                name="is_active"
                                value="1"
                                <?= ((int) $variant['is_active'] === 1) ? 'checked' : '' ?>
                        >
                        <span>
                            Variante active
                            <small>Elle restera visible et exploitable dans la boutique.</small>
                        </span>
                    </label>
                </article>

                <article class="shopf_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Image</span>
                        <h3>Visuel de la variante</h3>
                        <p>Conserve l’image actuelle ou remplace-la par un nouveau fichier.</p>
                    </div>

                    <div class="admin_current_image_block">
                        <p class="admin_current_image_label">Image actuelle</p>

                        <div class="admin_current_image_preview">
                            <img
                                    src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($currentImage) ?>"
                                    alt="<?= htmlspecialchars($variant['name']) ?>"
                            >
                        </div>

                        <p class="admin_current_image_name">
                            Fichier actuel :
                            <strong><?= htmlspecialchars($currentImage) ?></strong>
                        </p>
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-image">Nouvelle image</label>
                        <input
                                type="file"
                                id="variant-image"
                                name="image"
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
                        href="index.php?controller=shop&action=showAdminProduct&id=<?= (int) $variant['product_id'] ?>"
                >
                    Annuler
                </a>

                <button type="submit" class="showp_btn showp_btn_primary">
                    Enregistrer la variante
                </button>
            </div>
        </form>

        <?php if (currentUserCan('catalog.delete')): ?>
            <div class="shopf_secondary_actions">
                <form
                        method="post"
                        action="index.php?controller=shop&action=deleteVariant"
                        class="shopf_delete_form"
                        data-confirm-message="Archiver cette variante ? Elle sera retirée des paniers, mais son historique sera conservé."
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $variant['product_id'] ?>">
                    <input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>">

                    <button type="submit" class="showp_btn showp_btn_danger">
                        Archiver cette variante
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
