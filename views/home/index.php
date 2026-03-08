<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

    <main class="main_part home_page">
        <section class="home_hero">
            <div class="home_hero_text">
                <span class="home_badge">Gestionnaire de caisse café</span>
                <h2>Une interface simple, chaleureuse et efficace pour CKS GO</h2>
                <p class="home_intro">
                    Centralise les accès à la boutique, à ton espace personnel et à l’administration
                    dans une interface plus propre, plus lisible et plus agréable au quotidien.
                </p>

                <div class="home_cta">
                    <a class="home_btn home_btn_primary" href="index.php?controller=shop&action=index">
                        Accéder à la boutique
                    </a>

                    <?php if (isUserLoggedIn()): ?>
                        <a class="home_btn home_btn_secondary" href="index.php?controller=user&action=dashboard">
                            Mon tableau de bord
                        </a>
                    <?php else: ?>
                        <a class="home_btn home_btn_secondary" href="index.php?controller=user&action=login">
                            Se connecter
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="home_hero_card">
                <h3>CKS GO</h3>
                <p>
                    Une base claire pour gérer les consommations, accéder rapidement aux actions utiles
                    et faire évoluer l’application sans perdre ta mécanique actuelle.
                </p>

                <ul class="home_hero_features">
                    <li>Navigation simple</li>
                    <li>Accès rapide aux espaces clés</li>
                    <li>Design plus humain et plus moderne</li>
                </ul>
            </aside>
        </section>

        <section class="home_quick_access">
            <article class="home_card">
                <h3>Boutique</h3>
                <p>Consulter les produits disponibles et accéder rapidement à l’espace d’achat.</p>
                <a href="index.php?controller=shop&action=index">Ouvrir la boutique</a>
            </article>

            <article class="home_card">
                <h3>Espace utilisateur</h3>
                <p>Retrouver tes informations, ton suivi et les actions liées à ton compte.</p>
                <?php if (isUserLoggedIn()): ?>
                    <a href="index.php?controller=user&action=dashboard">Accéder au tableau de bord</a>
                <?php else: ?>
                    <a href="index.php?controller=user&action=login">Se connecter</a>
                <?php endif; ?>
            </article>

            <?php if (isAdmin()): ?>
                <article class="home_card home_card_admin">
                    <h3>Administration</h3>
                    <p>Gérer les utilisateurs, superviser l’application et accéder aux réglages.</p>
                    <a href="index.php?controller=admin&action=dashboard">Ouvrir l’administration</a>
                </article>
            <?php endif; ?>
        </section>

        <section class="home_news">
            <div class="section_heading">
                <span class="section_kicker">Accueil</span>
                <h3>Les news</h3>
                <p>
                    Zone prête à accueillir tes annonces internes, nouveautés ou infos utiles.
                </p>
            </div>

            <div class="home_news_grid">
                <article class="news_card">
                    <h4>Nouvelle interface</h4>
                    <p>La page d’accueil évolue avec une présentation plus claire et plus agréable.</p>
                </article>

                <article class="news_card">
                    <h4>Accès rapides</h4>
                    <p>Les liens principaux sont mis en avant pour améliorer la navigation.</p>
                </article>

                <article class="news_card">
                    <h4>Base évolutive</h4>
                    <p>Cette structure permettra d’ajouter du contenu dynamique plus tard.</p>
                </article>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>