<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

<main class="main_part dashboard">
    <h2>Tableau de bord</h2>
    <section class="dashboard_info">
        <p>Connecté en tant que: <strong><?= $_SESSION['user']['firstname'] ?></strong></p>
        <p>Votre note: <strong><?= $_SESSION['user']['note'] ?> €</strong></p>
        <p>Votre rôle: <strong><?= ucfirst($_SESSION['user']['role']) ?></strong></p>
    </section>

    <section class="dashboard_actions">
        <ul class="simple_list">
            <li><a href="#">👤 Mon profil</a></li>
            <li><a href="#">🛒 Mes achats</a></li>
           <li><a href="#">🪙 Mes paiements</a></li>
        </ul>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>