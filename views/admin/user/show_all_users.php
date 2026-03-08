<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Utilisateurs</span>
            <h2>Gestion des utilisateurs</h2>
            <p>
                Consulte les comptes, retrouve rapidement un utilisateur et accède aux actions d’administration.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="admin_user_list_topbar">
                <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=addUser">
                    Ajouter un utilisateur
                </a>
            </div>

            <form method="get" action="index.php" class="admin_catalog_search_form">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="showAllUsers">

                <div class="search_row">
                    <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q ?? '') ?>"
                            placeholder="Rechercher un pseudo, un email, un service, un rôle..."
                    >
                    <button type="submit">Rechercher</button>
                </div>
            </form>
        </section>

        <section class="admin_dashboard_section">
            <?php if (empty($users)): ?>
                <div class="empty_state">
                    <h3>Aucun utilisateur trouvé</h3>
                    <p>La recherche n’a retourné aucun résultat.</p>
                </div>
            <?php else: ?>
                <div class="admin_users_grid">
                    <?php foreach ($users as $user): ?>
                        <article class="admin_user_card">
                            <div class="admin_user_card_header">
                                <div class="admin_user_card_identity">
                                    <div class="admin_user_card_avatar">
                                        <?= strtoupper(substr((string)($user['firstname'] ?? $user['username'] ?? 'U'), 0, 1)) ?>
                                    </div>

                                    <div class="admin_user_card_identity_text">
                                        <h3>
                                            <?= htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''))) ?>
                                        </h3>
                                        <p class="admin_user_card_username">
                                            @<?= htmlspecialchars($user['username'] ?? '') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="admin_user_card_badges">
                                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                                        <span class="product_badge product_badge_category">Administrateur</span>
                                    <?php else: ?>
                                        <span class="product_badge product_badge_category">Utilisateur</span>
                                    <?php endif; ?>

                                    <?php if ((int)($user['is_active'] ?? 0) === 1): ?>
                                        <span class="product_badge product_badge_stock in_stock">Actif</span>
                                    <?php else: ?>
                                        <span class="product_badge product_badge_stock out_stock">Inactif</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="admin_user_card_body">
                                <div class="admin_user_card_info_grid">
                                    <div class="admin_user_info_item">
                                        <span>ID</span>
                                        <strong>#<?= (int)$user['id'] ?></strong>
                                    </div>

                                    <div class="admin_user_info_item">
                                        <span>Email</span>
                                        <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>
                                    </div>

                                    <div class="admin_user_info_item">
                                        <span>Service</span>
                                        <strong><?= htmlspecialchars($user['unit'] ?? '') ?></strong>
                                    </div>

                                    <div class="admin_user_info_item">
                                        <span>Note</span>
                                        <strong><?= number_format((float)($user['note'] ?? 0), 2, ',', ' ') ?> €</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="admin_user_card_actions">
                                <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=showUser&id=<?= (int)$user['id'] ?>">
                                    Voir la fiche
                                </a>

                                <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=editUser&id=<?= (int)$user['id'] ?>">
                                    Modifier
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>