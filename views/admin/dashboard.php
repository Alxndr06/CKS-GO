<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../models/User.php';
?>

    <main class="main_part dashboard">
        <h2>Panel d'Administration</h2>
        <section class="dashboard_info">
            <p class="btn"><strong>Total hors caisse :</strong> <?= User::getSumOfNotes() ?></p>
            <p class="btn"><strong>Utilisateurs en attente :</strong> <?= User::getInactiveCount() ?></p>
            <p class="btn"><strong>Requêtes en attente :</strong> Bientôt</p>
        </section>

        <section class="dashboard_actions">
            <ul class="simple_list">
                <li><a href="#">🗞️ News</a>
                <li><a href="index.php?controller=user&action=allUsers">👤 Utilisateurs</a></li>
                <li><a href="index.php?controller=shop&action=manageShop">🛒 Boutique</a></li>
                <li><a href="#">💰 Facturation</a></li>
                <li><a href="#">📅 Evénements</a></li>
                <li><a href="#">📜 Logs</a></li>
                <li><a href="index.php?controller=admin&action=serverSettings">⚙️ Paramètres</a></li>
            </ul>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>