<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Modification</span>
            <h2>Modifier l'utilisateur</h2>
            <p>Met à jour les informations du compte.</p>
        </section>

        <section class="admin_dashboard_section">
            <form
                method="post"
                action="index.php?controller=admin&action=updateUser"
                onsubmit="return confirm('Confirmer la modification de cet utilisateur ?');"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                <label for="username">Pseudo</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>

                <label for="lastname">Nom</label>
                <input type="text" name="lastname" id="lastname" value="<?= htmlspecialchars($user['lastname']) ?>" required>

                <label for="firstname">Prénom</label>
                <input type="text" name="firstname" id="firstname" value="<?= htmlspecialchars($user['firstname']) ?>" required>

                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                <label for="unit">Service</label>
                <select name="unit" id="unit" required>
                    <option value="mineurs" <?= $user['unit'] === 'mineurs' ? 'selected' : '' ?>>mineurs</option>
                    <option value="vif" <?= $user['unit'] === 'vif' ? 'selected' : '' ?>>vif</option>
                    <option value="syndicat" <?= $user['unit'] === 'syndicat' ? 'selected' : '' ?>>syndicat</option>
                </select>

                <label for="role">Rôle</label>
                <select name="role" id="role" required>
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>user</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                </select>

                <label for="note">Note</label>
                <input type="number" name="note" id="note" min="0" step="0.01" value="<?= htmlspecialchars((string)($user['note'] ?? 0)) ?>">

                <label for="password">Nouveau mot de passe</label>
                <input type="password" name="password" id="password" placeholder="Laisser vide pour ne pas changer">

                <label class="checkbox_line">
                    <input type="checkbox" name="is_active" value="1" <?= (int)$user['is_active'] === 1 ? 'checked' : '' ?>>
                    Compte actif
                </label>

                <div class="form_actions_inline">
                    <button type="submit">Enregistrer</button>
                    <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=showUser&id=<?= (int)$user['id'] ?>">
                        Annuler
                    </a>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>