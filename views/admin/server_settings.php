<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../models/User.php';
?>

    <main class="main_part dashboard">
        <h2>Paramètres du serveur</h2>

        <section class="dashboard_actions">
            <ul class="simple_list">
                <li><a href="#">🛒 Désactiver les inscriptions autonomes</a></li>
                <li><a href="#">💰 Verrouiller la boutique</a></li>
                <!-- Ajoutez autant d’items que nécessaire -->
            </ul>
        </section>


    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>