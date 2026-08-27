<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part">
        <section class="admin_page_shell">
            <div class="admin_intro_card">
                <span class="admin_section_eyebrow">Erreur 500</span>
                <h1>Une erreur interne est survenue</h1>
                <p>
                    L’application a rencontré un problème inattendu. L’incident a été journalisé.
                </p>
            </div>

            <section class="admin_detail_card">
                <div class="admin_detail_body">
                    <p>
                        Réessaie dans quelques instants. Si le problème persiste, contacte un administrateur.
                    </p>

                    <div class="admin_detail_actions">
                        <a class="btn btn_secondary" href="index.php?controller=home&action=index">
                            Retour à l’accueil
                        </a>

                        <?php if (!empty($_SESSION['user'])): ?>
                            <a class="btn btn_primary" href="index.php?controller=user&action=dashboard">
                                Tableau de bord
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>