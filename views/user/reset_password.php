<?php
$page_referrer_policy = 'no-referrer';
require_once __DIR__ . '/../partials/header.php';
?>

<main class="main_part auth_page">
    <h2>Réinitialiser mon mot de passe</h2>

    <p>Choisis un nouveau mot de passe pour finaliser la récupération de ton compte.</p>

    <form method="POST" action="<?= htmlspecialchars((BASE_URL !== '' ? BASE_URL : '') . '/index.php?controller=user&action=doResetPassword', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="new_password">Nouveau mot de passe</label>
        <input type="password" id="new_password" name="new_password" minlength="15" maxlength="128" autocomplete="new-password" required>

        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="15" maxlength="128" autocomplete="new-password" required>

        <button type="submit">Mettre à jour le mot de passe</button>
    </form>

    <p>
        <a href="<?= htmlspecialchars((BASE_URL !== '' ? BASE_URL : '') . '/index.php?controller=user&action=login', ENT_QUOTES, 'UTF-8') ?>">
            Retour à la connexion
        </a>
    </p>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
