<?php
require_once __DIR__ . '/../../partials/header.php';

$displayedCount = is_array($categories ?? null) ? count($categories) : 0;
$activeDisplayedCount = 0;

if (!empty($categories) && is_array($categories)) {
    foreach ($categories as $category) {
        if ((int)($category['is_active'] ?? 0) === 1) {
            $activeDisplayedCount++;
        }
    }
}
?>

<main class="main_part admin_dashboard_page admin_shop_form_page">
    <section class="admin_dashboard_intro">
        <span class="section_kicker">Boutique</span>
        <h2>Catégories produit</h2>
        <p>Crée, ajuste et active les catégories utilisées dans le catalogue.</p>
    </section>

    <section class="showp_toolbar" aria-label="Navigation catégories">
        <div class="showp_toolbar_row">
            <div class="showp_toolbar_left">
                <span class="showp_toolbar_label">Gestion / catalogue</span>
                <span class="showp_toolbar_count">
                    <?= $displayedCount ?> catégorie(s) affichée(s) · <?= $activeDisplayedCount ?> active(s)
                </span>
            </div>

            <div class="showp_toolbar_actions">
                <a class="showp_action_link showp_action_link_soft" href="index.php?controller=shop&action=manageShop">
                    Retour
                </a>

                <a class="showp_action_link showp_action_link_primary" href="index.php?controller=shop&action=allProducts">
                    Voir le catalogue
                </a>
            </div>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <form
                method="POST"
                action="index.php?controller=shop&action=storeCategory"
                class="shopf_form catm_create_form"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <article class="shopf_card">
                <div class="shopf_card_head">
                    <span class="section_kicker">Création</span>
                    <h3>Ajouter une catégorie</h3>
                    <p>Ajoute une nouvelle famille de produits dans une carte claire et compacte.</p>
                </div>

                <div class="catm_fields catm_fields_create">
                    <div class="form_group catm_field">
                        <label for="category-name">Nom</label>
                        <input type="text" id="category-name" name="name" required maxlength="100" placeholder="Ex. Boissons">
                    </div>

                    <div class="form_group catm_field">
                        <label for="category-slug">Slug</label>
                        <input type="text" id="category-slug" name="slug" maxlength="120" placeholder="Ex. boissons">
                    </div>

                    <div class="form_group catm_field catm_field_small">
                        <label for="category-sort-order">Ordre</label>
                        <input type="number" id="category-sort-order" name="sort_order" value="0">
                    </div>
                </div>

                <label class="shopf_checkbox catm_checkbox" for="category-active">
                    <input type="checkbox" id="category-active" name="is_active" checked>
                    <span>
                        Catégorie active
                        <small>Elle pourra être utilisée immédiatement dans le catalogue.</small>
                    </span>
                </label>
            </article>

            <div class="shopf_actions">
                <a class="showp_btn showp_btn_soft" href="index.php?controller=shop&action=manageShop">
                    Retour boutique
                </a>

                <button type="submit" class="showp_btn showp_btn_primary">
                    Créer la catégorie
                </button>
            </div>
        </form>
    </section>

    <section class="admin_dashboard_section">
        <div class="section_heading">
            <span class="section_kicker">Gestion</span>
            <h3>Catégories existantes</h3>
            <p class="catm_section_text">Clique sur une ligne pour ouvrir l’édition de la catégorie.</p>
        </div>

        <form method="GET" action="index.php" class="admin_catalog_search_form">
            <input type="hidden" name="controller" value="shop">
            <input type="hidden" name="action" value="categories">

            <div class="search_row">
                <input
                        type="text"
                        name="q"
                        value="<?= htmlspecialchars($q ?? '') ?>"
                        placeholder="Rechercher une catégorie par nom ou slug."
                >
                <button type="submit">Rechercher</button>
            </div>
        </form>

        <?php if (empty($categories)): ?>
            <div class="empty_state">
                <h3>Aucune catégorie trouvée</h3>
                <p>La recherche n’a retourné aucun résultat.</p>
            </div>
        <?php else: ?>
            <div class="catm_list">
                <?php foreach ($categories as $category): ?>
                    <?php $isActive = (int)($category['is_active'] ?? 0) === 1; ?>

                    <details class="catm_item">
                        <summary class="catm_summary">
                            <div class="catm_summary_main">
                                <div class="catm_identity">
                                    <h4><?= htmlspecialchars($category['name']) ?></h4>
                                    <p><?= htmlspecialchars($category['slug']) ?></p>
                                </div>
                            </div>

                            <div class="catm_summary_meta">
                                <span class="catm_chip">Ordre <?= (int)$category['sort_order'] ?></span>
                                <span class="catm_chip <?= $isActive ? 'catm_chip_success' : 'catm_chip_muted' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                                <span class="catm_chip catm_chip_action">Modifier</span>
                            </div>
                        </summary>

                        <div class="catm_body">
                            <div class="catm_body_grid">
                                <form
                                        method="POST"
                                        action="index.php?controller=shop&action=updateCategory"
                                        class="shopf_card catm_edit_form"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">

                                    <div class="shopf_card_head">
                                        <span class="section_kicker">Édition</span>
                                        <h3>Modifier la catégorie</h3>
                                        <p>Ajuste le nom, le slug, l’ordre et l’état d’activation.</p>
                                    </div>

                                    <div class="catm_fields">
                                        <div class="form_group catm_field">
                                            <label for="cat-name-<?= (int)$category['id'] ?>">Nom</label>
                                            <input
                                                    type="text"
                                                    id="cat-name-<?= (int)$category['id'] ?>"
                                                    name="name"
                                                    value="<?= htmlspecialchars($category['name']) ?>"
                                                    required
                                            >
                                        </div>

                                        <div class="form_group catm_field">
                                            <label for="cat-slug-<?= (int)$category['id'] ?>">Slug</label>
                                            <input
                                                    type="text"
                                                    id="cat-slug-<?= (int)$category['id'] ?>"
                                                    name="slug"
                                                    value="<?= htmlspecialchars($category['slug']) ?>"
                                                    required
                                            >
                                        </div>

                                        <div class="form_group catm_field catm_field_small">
                                            <label for="cat-order-<?= (int)$category['id'] ?>">Ordre</label>
                                            <input
                                                    type="number"
                                                    id="cat-order-<?= (int)$category['id'] ?>"
                                                    name="sort_order"
                                                    value="<?= (int)$category['sort_order'] ?>"
                                            >
                                        </div>
                                    </div>

                                    <label class="shopf_checkbox catm_checkbox" for="cat-active-<?= (int)$category['id'] ?>">
                                        <input
                                                type="checkbox"
                                                id="cat-active-<?= (int)$category['id'] ?>"
                                                name="is_active"
                                                value="1"
                                                <?= $isActive ? 'checked' : '' ?>
                                        >
                                        <span>
                                            Catégorie active
                                            <small>Décoche-la si tu veux la laisser enregistrée mais indisponible.</small>
                                        </span>
                                    </label>

                                    <div class="shopf_actions catm_actions">
                                        <button type="submit" class="showp_btn showp_btn_primary">
                                            Enregistrer
                                        </button>
                                    </div>
                                </form>

                                <aside class="shopf_card shopf_summary_card catm_sidecard">
                                    <div class="shopf_card_head">
                                        <span class="section_kicker">Statut</span>
                                        <h3>Activation rapide</h3>
                                        <p>Bascule l’état de la catégorie sans modifier le reste.</p>
                                    </div>

                                    <p class="shopf_summary_meta catm_summary_badges">
                                        <span><strong>Slug</strong> <?= htmlspecialchars($category['slug']) ?></span>
                                        <span><strong>Ordre</strong> <?= (int)$category['sort_order'] ?></span>
                                    </p>

                                    <div class="catm_status_line">
                                        <?php if ($isActive): ?>
                                            <span class="log_badge log_badge_success">Active</span>
                                        <?php else: ?>
                                            <span class="log_badge log_badge_error">Inactive</span>
                                        <?php endif; ?>
                                    </div>

                                    <form
                                            method="POST"
                                            action="index.php?controller=shop&action=toggleCategory"
                                            class="catm_toggle_form"
                                            data-confirm-message="Changer le statut de cette catégorie ?"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">

                                        <button type="submit" class="showp_btn showp_btn_soft catm_toggle_btn">
                                            <?= $isActive ? 'Désactiver la catégorie' : 'Activer la catégorie' ?>
                                        </button>
                                    </form>
                                </aside>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
