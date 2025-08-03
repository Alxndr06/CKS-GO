<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../models/User.php';
?>

    <main class="main_part dashboard">
        <h2>Panel d'Administration</h2>
        <section class="dashboard_info">
            <strong><p class="btn">Total hors caisse:</strong> <?= User::getSumOfNotes() ?></p>
            <strong><p class="btn">Utilisateurs en attente:</strong> <?= User::getInactiveCount() ?></p>
            <strong><p class="btn">Requêtes en attente:</strong> Bientôt</p>
        </section>

        <section class="dashboard_actions">
            <a href="#" class="btn">🗞️ News</a>
            <a href="index.php?controller=user&action=allUsers" class="btn">👤 Utilisateurs</a>
            <a href="#" class="btn">🛒 Boutique</a>
            <a href="#" class="btn">💰 Facturation</a>
            <a href="#" class="btn">📅 Evénements</a>
            <a href="#" class="btn">📜 Logs</a>
            <a href="#" class="btn">⚙️ Paramètres</a>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>