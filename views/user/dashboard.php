<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part user_dashboard_page">
        <section class="user_dashboard_hero">
            <div class="user_dashboard_hero_text">
                <span class="section_kicker">Espace utilisateur</span>
                <h2>Bonjour <?= htmlspecialchars($user['firstname']) ?> 👋</h2>
                <p>
                    Voici ton espace personnel CKS GO avec ta note actuelle, ton activité récente
                    et un résumé de tes commandes.
                </p>
            </div>

            <div class="user_dashboard_identity">
                <p><strong><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></strong></p>
                <p><?= htmlspecialchars($user['email']) ?></p>
                <p>Service : <strong><?= htmlspecialchars($user['unit']) ?></strong></p>
                <p>Rôle : <strong><?= htmlspecialchars(ucfirst($user['role'])) ?></strong></p>
            </div>
        </section>

        <section class="user_dashboard_stats">
            <article class="dashboard_stat_card highlight">
                <span class="dashboard_stat_label">Note actuelle</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$user['note'], 2, ',', ' ') ?> €</strong>
                <p>Montant actuellement dû par l’utilisateur.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Commandes</span>
                <strong class="dashboard_stat_value"><?= (int)$orderStats['total_orders'] ?></strong>
                <p>Total des commandes enregistrées.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Montant cumulé</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$orderStats['total_orders_amount'], 2, ',', ' ') ?> €</strong>
                <p>Total historique de tes commandes.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">En attente de paiement</span>
                <strong class="dashboard_stat_value"><?= number_format((float)$orderStats['pending_amount'], 2, ',', ' ') ?> €</strong>
                <p>Montant des commandes encore non réglées.</p>
            </article>
        </section>

        <section class="user_dashboard_actions">
            <a class="dashboard_action_card" href="index.php?controller=shop&action=index">
                <span class="dashboard_action_icon">🛍️</span>
                <div>
                    <h3>Boutique</h3>
                    <p>Continuer tes achats et découvrir les produits disponibles.</p>
                </div>
            </a>

            <a class="dashboard_action_card" href="index.php?controller=shop&action=cart">
                <span class="dashboard_action_icon">🛒</span>
                <div>
                    <h3>Mon panier</h3>
                    <p>Consulter, modifier et valider ton panier actuel.</p>
                </div>
            </a>

            <a class="dashboard_action_card dashboard_action_disabled" href="#">
                <span class="dashboard_action_icon">💳</span>
                <div>
                    <h3>Payer ma note</h3>
                    <p>La fonctionnalité de paiement arrivera dans la prochaine étape.</p>
                </div>
            </a>
        </section>

        <section class="user_dashboard_orders">
            <div class="section_heading">
                <span class="section_kicker">Historique</span>
                <h3>Dernières commandes</h3>
                <p>Résumé de tes commandes les plus récentes.</p>
            </div>

            <?php if (empty($recentOrders)): ?>
                <div class="empty_state">
                    <h3>Aucune commande pour le moment</h3>
                    <p>Tu n’as pas encore validé de panier.</p>
                </div>
            <?php else: ?>
                <div class="user_orders_list">
                    <?php foreach ($recentOrders as $order): ?>
                        <article class="user_order_card">
                            <div class="user_order_head">
                                <div>
                                    <h4>Commande #<?= (int)$order['id'] ?></h4>
                                    <p><?= htmlspecialchars($order['created_at']) ?></p>
                                </div>
                                <span class="order_status_badge status_<?= htmlspecialchars($order['status']) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                            </div>

                            <div class="user_order_meta">
                                <p>Lignes : <strong><?= (int)$order['item_lines'] ?></strong></p>
                                <p>Total : <strong><?= number_format((float)$order['total_price'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>