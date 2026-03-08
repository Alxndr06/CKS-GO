<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Modification</span>
            <h2>Modifier le produit</h2>
            <p>Met à jour les informations générales du produit.</p>
        </section>

        <section class="admin_dashboard_section">
            <form
                method="post"
                action="index.php?controller=shop&action=updateProduct"
                enctype="multipart/form-data"
                onsubmit="return confirm('Confirmer la modification de ce produit ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($product['image'] ?? '') ?>">

                <label for="name">Nom du produit</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="<?= htmlspecialchars($product['name']) ?>"
                    required
                >

                <label for="description">Description</label>
                <textarea
                    name="description"
                    id="description"
                ><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

                <div class="admin_current_image_block">
                    <p class="admin_current_image_label">Image actuelle</p>
                    <div class="admin_current_image_preview">
                        <img
                            src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars($product['image'] ?: 'php.png') ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                        >
                    </div>
                    <p class="admin_current_image_name">
                        Fichier actuel :
                        <strong><?= htmlspecialchars($product['image'] ?: 'php.png') ?></strong>
                    </p>
                </div>

                <label for="image_file">Nouvelle image</label>
                <input
                    type="file"
                    name="image_file"
                    id="image_file"
                    accept=".jpg,.jpeg,.png,.webp,.gif"
                >
                <p class="form_help_text">
                    Laisse vide pour conserver l’image actuelle.
                </p>

                <label class="checkbox_line">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= ((int)$product['is_active'] === 1) ? 'checked' : '' ?>
                    >
                    Produit actif
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Enregistrer les modifications</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=showAdminProduct&id=<?= (int)$product['id'] ?>">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>