<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Facturation admin</span>
            <h2>Facturer des produits à un utilisateur</h2>
            <p>
                Cette action crée une seule commande avec plusieurs lignes produits,
                décrémente les stocks concernés, et augmente la note de l’utilisateur.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Nouvelle facturation</span>
                <h3>Sélectionner les produits à facturer</h3>
            </div>

            <form
                    method="post"
                    action="index.php?controller=admin&action=createUserProductCharge"
                    onsubmit="return confirm('Confirmer la facturation de ces produits à cet utilisateur ?');"
                    id="multi_charge_form"
                    class="multi_charge_form"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <label for="user_id">Utilisateur</label>
                <select name="user_id" id="user_id" required>
                    <option value="">Sélectionner un utilisateur</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int)$user['id'] ?>">
                            <?= htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '') . ' — ' . ($user['username'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="charge_lines_wrapper" class="charge_lines_wrapper">
                    <div class="charge_line">
                        <div class="charge_line_fields">
                            <label>Produit / variante</label>
                            <select name="variant_id[]" required>
                                <option value="">Sélectionner une variante</option>
                                <?php foreach ($products as $product): ?>
                                    <?php foreach ($product['variants'] as $variant): ?>
                                        <option
                                                value="<?= (int)$variant['id'] ?>"
                                                <?= ($variant['stock_quantity'] <= 0) ? 'disabled' : '' ?>
                                        >
                                            <?= htmlspecialchars($product['name']) ?>
                                            —
                                            <?= htmlspecialchars(!empty($variant['flavor']) ? $variant['flavor'] : $variant['name']) ?>
                                            —
                                            <?= number_format((float)$variant['price'], 2, ',', ' ') ?> €
                                            —
                                            Stock : <?= (int)$variant['stock_quantity'] ?>
                                            <?= ($variant['stock_quantity'] <= 0) ? ' (Rupture)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>

                            <label>Quantité</label>
                            <input type="number" name="quantity[]" min="1" value="1" required>
                        </div>

                        <div class="charge_line_actions">
                            <button type="button" class="remove_charge_line_btn">
                                Retirer cette ligne
                            </button>
                        </div>
                    </div>
                </div>

                <div class="multi_charge_actions">
                    <button type="button" id="add_charge_line_btn" class="secondary_action_btn">
                        Ajouter un autre produit
                    </button>

                    <button type="submit" class="primary_action_btn">
                        Facturer ces produits à l’utilisateur
                    </button>
                </div>
            </form>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.getElementById('charge_lines_wrapper');
            const addBtn = document.getElementById('add_charge_line_btn');

            if (!wrapper || !addBtn) {
                return;
            }

            function bindRemoveButtons() {
                const buttons = wrapper.querySelectorAll('.remove_charge_line_btn');

                buttons.forEach((button) => {
                    button.onclick = function () {
                        const lines = wrapper.querySelectorAll('.charge_line');
                        if (lines.length <= 1) {
                            return;
                        }

                        button.closest('.charge_line').remove();
                    };
                });
            }

            addBtn.addEventListener('click', function () {
                const firstLine = wrapper.querySelector('.charge_line');
                if (!firstLine) {
                    return;
                }

                const clone = firstLine.cloneNode(true);

                clone.querySelectorAll('select').forEach((select) => {
                    select.selectedIndex = 0;
                });

                clone.querySelectorAll('input').forEach((input) => {
                    if (input.type === 'number') {
                        input.value = 1;
                    } else {
                        input.value = '';
                    }
                });

                wrapper.appendChild(clone);
                bindRemoveButtons();
            });

            bindRemoveButtons();
        });
    </script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>