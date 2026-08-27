<?php
require_once __DIR__ . '/../partials/header.php';
?>

<main class="main_part auth_page">
    <h2>Mot de passe oublié</h2>

    <p>
        Renseigne ton e-mail ou ton pseudo.
        Si un compte correspondant existe, un lien de réinitialisation sera envoyé à l’adresse associée.
    </p>

    <form method="POST" action="index.php?controller=user&action=sendPasswordResetLink">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <label for="identifier">Email ou pseudo</label>
        <input type="text" id="identifier" name="identifier" required>

        <button type="submit">Envoyer le lien</button>
    </form>

    <p><a href="index.php?controller=user&action=login">Retour à la connexion</a></p>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
