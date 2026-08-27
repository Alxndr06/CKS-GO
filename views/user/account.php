<?php
require_once __DIR__ . '/../partials/header.php';

$firstName = trim((string)($user['firstname'] ?? ''));
$lastName = trim((string)($user['lastname'] ?? ''));
$username = trim((string)($user['username'] ?? 'utilisateur'));
$fullName = trim($firstName . ' ' . $lastName);
$displayName = $fullName !== '' ? $fullName : $username;
$initials = mb_strtoupper(mb_substr($firstName !== '' ? $firstName : $username, 0, 1) . mb_substr($lastName, 0, 1));
$createdAt = !empty($user['created_at']) ? date('d/m/Y', strtotime((string)$user['created_at'])) : '—';
$emailVerified = !empty($user['email_verified_at']);
?>

<main class="main_part user_account_page user_account_redesign">
    <a class="account_back_link" href="index.php?controller=user&action=dashboard">
        <?= renderUiIcon('back') ?> Retour à mon espace
    </a>

    <section class="account_profile_hero">
        <div class="account_profile_identity">
            <span class="account_profile_avatar" aria-hidden="true"><?= htmlspecialchars($initials !== '' ? $initials : 'U') ?></span>
            <div>
                <span class="section_kicker">Mon compte</span>
                <h1><?= htmlspecialchars($displayName) ?></h1>
                <p>@<?= htmlspecialchars($username) ?> · membre depuis le <?= htmlspecialchars($createdAt) ?></p>
            </div>
        </div>

        <div class="account_profile_status" aria-label="État du compte">
            <span class="is_verified"><?= renderUiIcon('shield') ?> Compte actif</span>
            <span class="<?= $emailVerified ? 'is_verified' : 'is_attention' ?>">
                <?= renderUiIcon('mail') ?> <?= $emailVerified ? 'E-mail vérifié' : 'E-mail à vérifier' ?>
            </span>
        </div>
    </section>

    <div class="account_workspace">
        <aside class="account_sidebar">
            <section class="account_summary_card">
                <span>Informations du profil</span>
                <dl>
                    <div><dt>Service</dt><dd><?= htmlspecialchars(ucfirst((string)($user['unit'] ?? '—'))) ?></dd></div>
                    <div><dt>Rôle</dt><dd><?= htmlspecialchars(getRoleLabel($user['role'] ?? 'user')) ?></dd></div>
                    <div><dt>Adresse actuelle</dt><dd><?= htmlspecialchars((string)($user['email'] ?? '—')) ?></dd></div>
                </dl>
            </section>

            <nav class="account_section_nav" aria-label="Rubriques du compte">
                <a href="#account-email"><?= renderUiIcon('mail') ?><span><strong>Adresse e-mail</strong><small>Identifiant et notifications</small></span></a>
                <a href="#account-security"><?= renderUiIcon('key') ?><span><strong>Mot de passe</strong><small>Sécurité de connexion</small></span></a>
                <a href="#account-danger"><?= renderUiIcon('delete') ?><span><strong>Suppression</strong><small>Action définitive</small></span></a>
            </nav>

            <p class="account_security_tip">
                <?= renderUiIcon('shield') ?>
                <span><strong>Conseil sécurité</strong> Utilisez un mot de passe unique, jamais réutilisé sur un autre service.</span>
            </p>
        </aside>

        <div class="account_panels">
            <section class="account_panel" id="account-email">
                <header class="account_panel_header">
                    <span class="account_panel_icon is_sky" aria-hidden="true"><?= renderUiIcon('mail') ?></span>
                    <div>
                        <span>Coordonnées</span>
                        <h2>Adresse e-mail</h2>
                        <p>Elle sert à vous connecter et à recevoir les confirmations importantes.</p>
                    </div>
                </header>

                <form method="post" action="index.php?controller=user&action=updateEmail" class="account_form account_form_refined">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <label for="email">
                        <span>Nouvelle adresse e-mail</span>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)$user['email']) ?>" autocomplete="email" required>
                    </label>

                    <label for="current_password_email">
                        <span>Mot de passe actuel</span>
                        <span class="account_password_control">
                            <input type="password" id="current_password_email" name="current_password" autocomplete="current-password" required>
                            <button type="button" data-password-toggle="current_password_email" aria-label="Afficher le mot de passe"><?= renderUiIcon('eye') ?></button>
                        </span>
                    </label>

                    <p class="account_form_notice">Après le changement, vous serez déconnecté et devrez confirmer la nouvelle adresse.</p>
                    <button type="submit">Enregistrer la nouvelle adresse</button>
                </form>
            </section>

            <section class="account_panel" id="account-security">
                <header class="account_panel_header">
                    <span class="account_panel_icon is_mint" aria-hidden="true"><?= renderUiIcon('key') ?></span>
                    <div>
                        <span>Sécurité</span>
                        <h2>Mot de passe</h2>
                        <p>Choisissez au moins 15 caractères. Une phrase longue et unique est souvent plus simple à retenir.</p>
                    </div>
                </header>

                <form method="post" action="index.php?controller=user&action=updatePassword" class="account_form account_form_refined" data-password-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <label for="current_password">
                        <span>Mot de passe actuel</span>
                        <span class="account_password_control">
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                            <button type="button" data-password-toggle="current_password" aria-label="Afficher le mot de passe"><?= renderUiIcon('eye') ?></button>
                        </span>
                    </label>

                    <div class="account_password_grid">
                        <label for="new_password">
                            <span>Nouveau mot de passe</span>
                            <span class="account_password_control">
                                <input type="password" id="new_password" name="new_password" minlength="15" maxlength="128" autocomplete="new-password" required data-new-password>
                                <button type="button" data-password-toggle="new_password" aria-label="Afficher le mot de passe"><?= renderUiIcon('eye') ?></button>
                            </span>
                        </label>

                        <label for="confirm_password">
                            <span>Confirmation</span>
                            <span class="account_password_control">
                                <input type="password" id="confirm_password" name="confirm_password" minlength="15" maxlength="128" autocomplete="new-password" required data-confirm-password>
                                <button type="button" data-password-toggle="confirm_password" aria-label="Afficher le mot de passe"><?= renderUiIcon('eye') ?></button>
                            </span>
                        </label>
                    </div>

                    <div class="account_password_strength" data-password-strength>
                        <span><i></i></span>
                        <small>Saisissez un nouveau mot de passe.</small>
                    </div>

                    <button type="submit">Mettre à jour le mot de passe</button>
                </form>
            </section>

            <section class="account_danger_zone" id="account-danger">
                <details>
                    <summary>
                        <span class="account_panel_icon is_coral" aria-hidden="true"><?= renderUiIcon('delete') ?></span>
                        <span><strong>Supprimer mon compte</strong><small>Afficher les options de suppression définitive</small></span>
                        <b aria-hidden="true">+</b>
                    </summary>

                    <div class="account_danger_content">
                        <p>Cette action est définitive. Elle n’est possible que si votre solde est à zéro et qu’aucun montant ne reste dû.</p>

                        <form method="post" action="index.php?controller=user&action=deleteAccount" class="account_form account_form_refined">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <label for="delete_password">
                                <span>Mot de passe actuel</span>
                                <input type="password" id="delete_password" name="current_password" autocomplete="current-password" required>
                            </label>

                            <label for="confirm_text">
                                <span>Tapez SUPPRIMER pour confirmer</span>
                                <input type="text" id="confirm_text" name="confirm_text" autocomplete="off" pattern="SUPPRIMER" required>
                            </label>

                            <button type="submit" class="danger_btn" data-confirm-message="Supprimer définitivement votre compte ?">Supprimer définitivement mon compte</button>
                        </form>
                    </div>
                </details>
            </section>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
