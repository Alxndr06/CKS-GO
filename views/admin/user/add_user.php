<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Création</span>
            <h2>Ajouter un utilisateur</h2>
            <p>Crée un nouveau compte directement depuis l’admin.</p>
        </section>

        <section class="admin_dashboard_section">
            <form
                method="post"
                action="index.php?controller=admin&action=createUser"
                onsubmit="return confirm('Confirmer la création de cet utilisateur ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <label for="username">Pseudo</label>
                <input type="text" name="username" id="username" required>

                <label for="lastname">Nom</label>
                <input type="text" name="lastname" id="lastname" required>

                <label for="firstname">Prénom</label>
                <input type="text" name="firstname" id="firstname" required>

                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>

                <label for="unit">Service</label>
                <select name="unit" id="unit" required>
                    <option value="">Sélectionner</option>
                    <option value="mineurs">mineurs</option>
                    <option value="vif">vif</option>
                    <option value="syndicat">syndicat</option>
                </select>

                <label for="role">Rôle</label>
                <select name="role" id="role" required>
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                </select>

                <label for="note">Note initiale</label>
                <input type="number" name="note" id="note" min="0" step="0.01" value="0">

                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>

                <label class="checkbox_line">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Compte actif
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Créer l'utilisateur</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=showAllUsers">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>