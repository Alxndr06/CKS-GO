<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

<main class="main_part">
    <h2>Connexion</h2>
    <?= displayErrorOrSuccessMessage(); ?>

    <form method="POST" action="index.php?controller=user&action=doLogin">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

        <label for="email">Email ou pseudo</label>
        <input type="text" id="email" name="email" required>

        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Connexion</button>
    </form>
    <p>Pas encore de compte ? <a href="index.php?controller=user&action=register">Créez en un</a></p>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>