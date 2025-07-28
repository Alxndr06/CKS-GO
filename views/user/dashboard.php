<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

<main class="main_part dashboard">
    <h2>Tableau de bord</h2>
    <section class="dashboard_info">
        <p>Bonjour <?= $_SESSION['user']['firstname'] ?> !</p>
        <p>Votre note</p>
        <p>Votre rôle</p>
    </section>

    <section class="dashboard_actions">
        <a href="#" class="btn">👤 Mon profil</a>
        <a href="#" class="btn">🛒 Mes achats</a>
        <a href="#" class="btn">🪙 Mes paiements</a>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>