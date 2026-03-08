<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Modification</span>
            <h2>Modifier la variante</h2>
            <p>
                Mets à jour les informations de la variante associée à
                <strong> <?= htmlspecialchars($variant['product_name']) ?></strong>.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <form
                method="post"
                action="index.php?controller=shop&action=updateVariant"
                onsubmit="return confirm('Confirmer la modification de cette variante ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="variant_id" value="<?= (int)$variant['id'] ?>">
                <input type="hidden" name="product_id" value="<?= (int)$variant['product_id'] ?>">

                <label for="name">Nom de la variante</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="<?= htmlspecialchars($variant['name']) ?>"
                    required
                >

                <label for="price">Prix</label>
                <input
                    type="number"
                    name="price"
                    id="price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars((string)$variant['price']) ?>"
                    required
                >

                <label class="checkbox_line">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= ((int)$variant['is_active'] === 1) ? 'checked' : '' ?>
                    >
                    Variante active
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Enregistrer les modifications</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=showAdminProduct&id=<?= (int)$variant['product_id'] ?>">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>