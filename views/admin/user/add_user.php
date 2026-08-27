<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page admin_shop_form_page user_management_form_page">
        <section class="admin_dashboard_intro admin_dashboard_intro_compact">
            <span class="section_kicker">Utilisateurs</span>
            <h2>Ajouter un utilisateur</h2>
            <p>
                Crée un nouveau compte depuis l’admin dans une vue compacte,
                claire et cohérente avec le reste du back-office.
            </p>
        </section>

        <section class="showp_toolbar" aria-label="Navigation création utilisateur">
            <div class="showp_toolbar_row">
                <div class="showp_toolbar_left">
                    <span class="showp_toolbar_label">Création de compte</span>
                    <span class="showp_toolbar_count">Admin</span>
                </div>

                <div class="showp_toolbar_actions">
                    <a
                            class="showp_action_link showp_action_link_soft"
                            href="index.php?controller=admin&action=showAllUsers"
                    >
                        Retour
                    </a>
                </div>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <form
                    method="post"
                    action="index.php?controller=admin&action=createUser"
                    class="shopf_form"
                    data-confirm-message="Confirmer la création de cet utilisateur ?"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                <div class="shopf_grid">
                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Identité</span>
                            <h3>Compte</h3>
                            <p>Pseudo, email et mot de passe de connexion.</p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="username">Pseudo *</label>
                            <input type="text" name="username" id="username" required maxlength="100">
                        </div>

                        <div class="form_group shopf_field">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" required maxlength="190">
                        </div>

                        <div class="form_group shopf_field">
                            <label for="password">Mot de passe *</label>
                            <input type="password" name="password" id="password" minlength="15" maxlength="128" autocomplete="new-password" required>
                        </div>
                    </article>

                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Profil</span>
                            <h3>Informations utilisateur</h3>
                            <p>Nom, service, rôle et état du compte.</p>
                        </div>

                        <div class="shopf_subgrid">
                            <div class="form_group shopf_field">
                                <label for="lastname">Nom *</label>
                                <input type="text" name="lastname" id="lastname" required maxlength="100">
                            </div>

                            <div class="form_group shopf_field">
                                <label for="firstname">Prénom *</label>
                                <input type="text" name="firstname" id="firstname" required maxlength="100">
                            </div>
                        </div>

                        <div class="shopf_subgrid">
                            <div class="form_group shopf_field">
                                <label for="unit">Service *</label>
                                <select name="unit" id="unit" required>
                                    <option value="">Sélectionner</option>
                                    <option value="mineurs">mineurs</option>
                                    <option value="vif">vif</option>
                                    <option value="syndicat">syndicat</option>
                                </select>
                            </div>

                            <div class="form_group shopf_field">
                                <label for="role">Rôle *</label>
                                <select name="role" id="role" required>
                                    <?php foreach (getAssignableRoles() as $roleValue => $roleDefinition): ?>
                                        <option
                                                value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"
                                                data-role-label="<?= htmlspecialchars((string)$roleDefinition['label'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-role-description="<?= htmlspecialchars((string)$roleDefinition['description'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <?= htmlspecialchars((string)$roleDefinition['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="role_help_card" data-role-help>
                            <strong data-role-help-title>Des droits adaptés au poste</strong>
                            <p data-role-help-description>Un responsable ne peut attribuer que des rôles inférieurs au sien. L’administrateur conserve les réglages système.</p>
                        </div>

                        <div class="shopf_note">
                            <p>
                                La note n’est plus définie manuellement à la création.
                                Les montants dus sont générés via les commandes et la facturation admin.
                            </p>
                        </div>

                        <label class="shopf_checkbox" for="user-active">
                            <input type="checkbox" id="user-active" name="is_active" value="1" checked>
                            <span>
                            Compte actif
                            <small>Le compte pourra être utilisé immédiatement après création.</small>
                        </span>
                        </label>
                    </article>
                </div>

                <div class="shopf_actions">
                    <a
                            class="showp_btn showp_btn_soft"
                            href="index.php?controller=admin&action=showAllUsers"
                    >
                        Annuler
                    </a>

                    <button type="submit" class="showp_btn showp_btn_primary">
                        Créer l’utilisateur
                    </button>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
