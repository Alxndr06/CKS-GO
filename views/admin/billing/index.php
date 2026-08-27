<?php
require_once __DIR__ . '/../../partials/header.php';

$users = is_array($users ?? null) ? $users : [];
?>

<main class="main_part admin_dashboard_page billing_directory_page">
    <section class="admin_dashboard_intro admin_dashboard_intro_compact">
        <span class="section_kicker">Facturation</span>
        <h2>Sélectionner un utilisateur</h2>
        <p>Choisis le compte à facturer.</p>
    </section>

    <section class="user_directory_panel" data-billing-directory>
        <div class="billing_directory_search_row">
            <label class="user_directory_search billing_directory_search">
                <span>Rechercher un utilisateur</span>
                <span class="user_directory_search_control">
                    <?= renderUiIcon('search') ?>
                    <input
                        type="search"
                        placeholder="Nom, prénom, pseudo ou e-mail"
                        autocomplete="off"
                        data-billing-user-search
                    >
                </span>
            </label>
            <button type="button" class="billing_directory_reset" data-billing-search-reset hidden>
                Réinitialiser
            </button>
        </div>

        <div class="user_directory_results_head">
            <div>
                <h2>Utilisateurs actifs</h2>
                <p><span data-billing-visible-count><?= count($users) ?></span> utilisateur(s)</p>
            </div>
        </div>

        <div class="user_directory_list billing_user_list">
            <?php foreach ($users as $user): ?>
                <?php
                $userId = (int)($user['id'] ?? 0);
                $firstname = trim((string)($user['firstname'] ?? ''));
                $lastname = trim((string)($user['lastname'] ?? ''));
                $username = trim((string)($user['username'] ?? 'utilisateur'));
                $email = trim((string)($user['email'] ?? ''));
                $unit = trim((string)($user['unit'] ?? ''));
                $displayName = trim($firstname . ' ' . $lastname);
                $displayName = $displayName !== '' ? $displayName : $username;
                $initials = mb_strtoupper(mb_substr($firstname, 0, 1) . mb_substr($lastname, 0, 1));
                $initials = $initials !== '' ? $initials : mb_strtoupper(mb_substr($username, 0, 2));
                $searchValue = mb_strtolower(trim(implode(' ', [
                    $firstname,
                    $lastname,
                    $username,
                    $email,
                    $unit,
                ])));
                ?>
                <a
                    class="user_directory_row billing_user_row"
                    href="index.php?controller=admin&amp;action=billing&amp;user_id=<?= $userId ?>"
                    data-billing-user-card
                    data-billing-search="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <span class="user_directory_identity">
                        <span class="user_directory_avatar" aria-hidden="true">
                            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span>
                            <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>
                                @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($email !== ''): ?> · <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </small>
                        </span>
                    </span>
                    <span class="billing_user_unit">
                        <?= htmlspecialchars($unit !== '' ? ucfirst($unit) : 'Service non renseigné', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="billing_user_select_label">Sélectionner <span aria-hidden="true">→</span></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="user_directory_empty" data-billing-directory-empty <?= $users === [] ? '' : 'hidden' ?>>
            <span aria-hidden="true"><?= renderUiIcon('users') ?></span>
            <h3>Aucun utilisateur trouvé</h3>
            <p>Essaie une autre recherche.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
