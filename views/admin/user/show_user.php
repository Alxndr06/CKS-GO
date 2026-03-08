<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Utilisateur</span>
            <h2>Fiche utilisateur</h2>
            <p>
                Consulte les informations détaillées du compte et accède rapidement aux actions d’administration.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="admin_user_show_card">
                <div class="admin_user_show_header">
                    <div class="admin_user_identity">
                        <div class="admin_user_avatar">
                            <?= strtoupper(substr((string)($user['firstname'] ?? $user['username'] ?? 'U'), 0, 1)) ?>
                        </div>

                        <div class="admin_user_identity_text">
                            <h3>
                                <?= htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''))) ?>
                            </h3>
                            <p class="admin_user_username">
                                @<?= htmlspecialchars($user['username'] ?? '') ?>
                            </p>
                        </div>
                    </div>

                    <div class="admin_user_badges">
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

                <div class="admin_user_show_grid">
                    <article class="admin_user_info_block">
                        <h4>Informations générales</h4>

                        <div class="admin_user_info_list">
                            <div class="admin_user_info_row">
                                <span>ID</span>
                                <strong>#<?= (int)$user['id'] ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Nom</span>
                                <strong><?= htmlspecialchars($user['lastname'] ?? '') ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Prénom</span>
                                <strong><?= htmlspecialchars($user['firstname'] ?? '') ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Pseudo</span>
                                <strong><?= htmlspecialchars($user['username'] ?? '') ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Email</span>
                                <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>
                            </div>
                        </div>
                    </article>

                    <article class="admin_user_info_block">
                        <h4>Compte & caisse</h4>

                        <div class="admin_user_info_list">
                            <div class="admin_user_info_row">
                                <span>Service</span>
                                <strong><?= htmlspecialchars($user['unit'] ?? '') ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Rôle</span>
                                <strong><?= htmlspecialchars($user['role'] ?? '') ?></strong>
                            </div>

                            <div class="admin_user_info_row">
                                <span>Note actuelle</span>
                                <strong><?= number_format((float)($user['note'] ?? 0), 2, ',', ' ') ?> €</strong>
                            </div>

                            <?php if (isset($user['total_spent'])): ?>
                                <div class="admin_user_info_row">
                                    <span>Total dépensé</span>
                                    <strong><?= number_format((float)$user['total_spent'], 2, ',', ' ') ?> €</strong>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($user['created_at'])): ?>
                                <div class="admin_user_info_row">
                                    <span>Créé le</span>
                                    <strong><?= htmlspecialchars($user['created_at']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="admin_user_show_actions">
                    <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=editUser&id=<?= (int)$user['id'] ?>">
                        Modifier l'utilisateur
                    </a>

                    <a class="home_btn home_btn_secondary" href="index.php?controller=admin&action=showAllUsers">
                        Retour à la liste
                    </a>

                    <form
                            method="post"
                            action="index.php?controller=admin&action=deleteUser"
                            onsubmit="return confirm('Confirmer la suppression de cet utilisateur ?');"
                            class="admin_user_delete_form"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                        <button type="submit" class="remove_btn">Supprimer l'utilisateur</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>