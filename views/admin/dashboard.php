<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

    <main class="main_part dashboard">
        <h2>Panel d'Administration</h2>
        <section class="dashboard_info">
            <p>Vous êtes sur le panel d'administration de CKS. Si vous ne savez pas ce que vous faites, ne touchez à rien !</p>
        </section>

        <section class="dashboard_actions">
            <a href="#" class="btn">🗞️ News</a>
            <a href="#" class="btn">👤 Utilisateurs</a>
            <a href="#" class="btn">🛒 Boutique</a>
            <a href="#" class="btn">💰 Facturation</a>
            <a href="#" class="btn">📅 Evénements</a>
            <a href="#" class="btn">📜 Logs</a>
            <a href="#" class="btn">⚙️ Paramètres</a>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>