<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

    <main class="main_part">
        <h2>Connexion</h2>
        <?= displayErrorOrSuccessMessage(); ?>

        <form method="POST" action="index.php?controller=user&action=doRegister" class="register_form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <label for="username">Pseudo</label>
            <input type="text" id="username" name="username" required>

            <label for="lastname">Nom</label>
            <input type="text" id="lastname" name="lastname" required>

            <label for="firstname">Prénom</label>
            <input type="text" id="firstname" name="firstname" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required><br><br>

            <label for="confirmPassword">Confirmer mot de passe</label>
            <input type="password" id="confirmPassword" name="confirmPassword" autocomplete="new-password" ><br><br>

            <button type="submit">Créer mon compte</button>
        </form>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>