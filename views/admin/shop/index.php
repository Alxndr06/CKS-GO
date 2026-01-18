<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part dashboard">
        <h2>Gestion de la boutique</h2>
        <section class="dashboard_info">
            <p class="btn"><strong>Total de produits :</strong> Bientôt</p>
            <p class="btn"><strong>En stock :</strong> Bientôt</p>
            <p class="btn"><strong>Produit vedette :</strong> Bientôt</p>
        </section>

        <section class="dashboard_actions">
            <ul class="simple_list">
                <li><a href="#">📜 Liste des produits</a></li>
                <li><a href="index.php?controller=Shop&action=addProductToShop">🛒 Ajouter un produit</a></li>
                <li><a href="index.php?controller=admin&action=serverSettings">⚙️ Paramètres de la boutique</a></li>
            </ul>
        </section>
    </main>

<?php
require_once __DIR__ . '/../../partials/footer.php';
?>