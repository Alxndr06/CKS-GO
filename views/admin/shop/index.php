<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page admin_shop_landing_page">
        <section class="management_module_header">
            <div class="management_module_header_copy">
                <span class="section_kicker">Boutique</span>
            <h2>Gestion de la boutique</h2>
            <p>
                Gère les produits, les catégories et les outils liés au catalogue
                depuis cet espace d’administration.
                </p>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Gestion</span>
                <h3>Outils boutique</h3>
                <p>Accès rapide aux actions principales sans afficher la liste complète ici.</p>
            </div>

            <div class="admin_management_grid">
                <a class="dashboard_action_card" href="index.php?controller=shop&action=allProducts">
                    <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('logs') ?></span>
                    <div>
                        <h3>Liste des produits</h3>
                        <p>Ouvrir la page dédiée avec recherche, pagination et actions rapides.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=shop&action=addProduct">
                    <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('add') ?></span>
                    <div>
                        <h3>Ajouter un produit</h3>
                        <p>Créer un nouveau produit dans la boutique.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=shop&action=categories">
                    <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('categories') ?></span>
                    <div>
                        <h3>Gérer les catégories</h3>
                        <p>Créer, modifier et activer les catégories du catalogue.</p>
                    </div>
                </a>

                <?php if (currentUserCan('billing.manage')): ?>
                    <a class="dashboard_action_card" href="index.php?controller=admin&action=billing">
                        <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                        <div>
                            <h3>Facturer un utilisateur</h3>
                            <p>Ajouter un produit directement à la note d’un utilisateur.</p>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (currentUserCan('inventory.adjust')): ?>
                    <a class="dashboard_action_card" href="index.php?controller=shop&action=inventory">
                        <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('inventory') ?></span>
                        <div>
                            <h3>Piloter les stocks</h3>
                            <p>Voir les ruptures, ajuster une quantité et consulter les derniers mouvements.</p>
                        </div>
                    </a>

                    <a class="dashboard_action_card" href="index.php?controller=shop&action=inventoryIssues">
                        <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('inventory') ?></span>
                        <div>
                            <h3>Incidents de stock</h3>
                            <p>Déclarer une perte ou un vol et suivre les sorties exceptionnelles.</p>
                        </div>
                    </a>
                <?php endif; ?>

            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
