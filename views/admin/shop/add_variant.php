<?php
require_once __DIR__ . '/../../partials/header.php';

$productId = (int) ($product['id'] ?? ($_GET['product_id'] ?? 0));
$productName = trim((string) ($product['name'] ?? 'Produit'));
$productImage = trim((string) ($product['image'] ?? 'php.png'));
$productCategoryName = !empty($product['category_name']) ? (string) $product['category_name'] : null;
$productIsActive = isset($product['is_active']) ? ((int) $product['is_active'] === 1) : true;
$productVariantCount = (int) ($product['variant_count'] ?? 0);
$productStockTotal = (int) ($product['total_stock'] ?? 0);
?>

<main class="main_part admin_dashboard_page admin_shop_form_page">
    <section class="admin_dashboard_intro">
        <span class="section_kicker">Boutique</span>
        <h2>Ajouter une variante</h2>
        <p>
            Ajoute une nouvelle déclinaison à ce produit dans une interface compacte
            et cohérente avec la vue détail admin.
        </p>
    </section>

    <section class="showp_toolbar" aria-label="Navigation création variante">
        <div class="showp_toolbar_row">
            <div class="showp_toolbar_left">
                <span class="showp_toolbar_label">Produit cible</span>
                <span class="showp_toolbar_count"><?= $productVariantCount ?> variante(s) existante(s)</span>
            </div>

            <div class="showp_toolbar_actions">
                <a
                        class="showp_action_link showp_action_link_soft"
                        href="index.php?controller=shop&action=showAdminProduct&id=<?= $productId ?>"
                >
                    Retour
                </a>

                <a
                        class="showp_action_link showp_action_link_primary"
                        href="index.php?controller=shop&action=editProduct&id=<?= $productId ?>"
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
                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($productImage ?: 'php.png') ?>"
                        alt="<?= htmlspecialchars($productName) ?>"
                >
            </div>

            <div class="showp_summary_body">
                <div class="showp_summary_top">
                    <div class="showp_summary_title_wrap">
                        <h3><?= htmlspecialchars($productName) ?></h3>

                        <div class="showp_summary_badges">
                            <?php if ($productIsActive): ?>
                                <span class="showp_badge showp_badge_success">Actif</span>
                            <?php else: ?>
                                <span class="showp_badge showp_badge_warning">Désactivé</span>
                            <?php endif; ?>

                            <?php if ($productCategoryName): ?>
                                <span class="showp_badge"><?= htmlspecialchars($productCategoryName) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="showp_summary_stats">
                    <div class="showp_stat_card">
                        <span>Produit</span>
                        <strong>#<?= $productId ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>Variantes</span>
                        <strong><?= $productVariantCount ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>Stock total</span>
                        <strong><?= $productStockTotal ?></strong>
                    </div>

                    <div class="showp_stat_card">
                        <span>État</span>
                        <strong><?= $productIsActive ? 'Actif' : 'Désactivé' ?></strong>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="admin_dashboard_section">
        <form
                method="POST"
                action="index.php?controller=shop&action=storeVariant"
                enctype="multipart/form-data"
                class="shopf_form"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="product_id" value="<?= $productId ?>">

            <div class="shopf_grid">
                <article class="shopf_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Nouvelle variante</span>
                        <h3>Identité</h3>
                        <p>Nom commercial et attribut distinctif de la variante.</p>
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-name">Nom de la variante *</label>
                        <input
                                type="text"
                                id="variant-name"
                                name="name"
                                required
                                maxlength="120"
                                placeholder="Ex. Canette 33cl"
                        >
                    </div>

                    <div class="form_group shopf_field">
                        <label for="variant-flavor">Saveur / attribut</label>
                        <input
                                type="text"
                                id="variant-flavor"
                                name="flavor"
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
                                maxlength="64"
                                placeholder="Généré automatiquement si vide"
                        >
                        <small class="shopf_helper_text">Une référence unique facilite les inventaires et la recherche.</small>
                    </div>

                    <div class="shopf_note">
                        <p>
                            Tu peux conserver l’image principale du produit ou définir une image dédiée
                            pour cette variante dès sa création.
                        </p>
                    </div>
                </article>

                <article class="shopf_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Publication</span>
                        <h3>Prix, stock et ordre</h3>
                        <p>Réglages de disponibilité et hiérarchie d’affichage.</p>
                    </div>

                    <div class="shopf_subgrid">
                        <div class="form_group shopf_field">
                            <label for="variant-price">Prix (€) *</label>
                            <input
                                    type="number"
                                    id="variant-price"
                                    name="price"
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
                                    name="stock_quantity"
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
                                    name="low_stock_threshold"
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
                                name="sort_order"
                                min="0"
                                value="<?= max(0, $productVariantCount) ?>"
                        >
                        <small class="shopf_helper_text">
                            Plus le chiffre est petit, plus la variante remonte dans la liste.
                        </small>
                    </div>

                    <label class="shopf_checkbox" for="variant-active">
                        <input type="checkbox" id="variant-active" name="is_active" value="1" checked>
                        <span>
                            Variante active
                            <small>Elle sera disponible dès son enregistrement.</small>
                        </span>
                    </label>
                </article>

                <article class="shopf_card shopf_card_span_full">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Image</span>
                        <h3>Photo de la variante</h3>
                        <p>Ajoute un visuel dédié ou laisse vide pour réutiliser l’image du produit.</p>
                    </div>

                    <div class="shopf_image_grid">
                        <div class="admin_current_image_block">
                            <p class="admin_current_image_label">Image produit actuellement utilisée</p>

                            <div class="admin_current_image_preview">
                                <img
                                        src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($productImage ?: 'php.png') ?>"
                                        alt="<?= htmlspecialchars($productName) ?>"
                                >
                            </div>

                            <p class="admin_current_image_name">
                                Fichier actuel :
                                <strong><?= htmlspecialchars($productImage ?: 'php.png') ?></strong>
                            </p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="variant-image">Photo de la variante</label>
                            <input
                                    type="file"
                                    id="variant-image"
                                    name="image"
                                    accept=".jpg,.jpeg,.png,.webp,.gif"
                            >
                            <p class="form_help_text">
                                Formats acceptés : JPG, PNG, WEBP, GIF — 5 Mo max.
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="shopf_actions">
                <a
                        class="showp_btn showp_btn_soft"
                        href="index.php?controller=shop&action=showAdminProduct&id=<?= $productId ?>"
                >
                    Retour
                </a>

                <button type="submit" class="showp_btn showp_btn_primary">
                    Enregistrer la variante
                </button>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
