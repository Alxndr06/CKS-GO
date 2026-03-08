<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Administration</span>
            <h2>Panel d’administration</h2>
            <p>
                Vue d’ensemble sur les statistiques, les outils de gestion et les données utiles
                pour piloter CKS GO.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Statistiques</span>
                <h3>Vue d’ensemble</h3>
                <p>Les chiffres principaux regroupés au même endroit.</p>
            </div>

            <div class="admin_stats_grid">
                <article class="dashboard_stat_card highlight">
                    <span class="dashboard_stat_label">Total hors caisse</span>
                    <strong class="dashboard_stat_value"><?= number_format((float)$sum_of_notes, 2, ',', ' ') ?> €</strong>
                    <p>Montant total actuellement dû par les utilisateurs.</p>
                </article>

                <article class="dashboard_stat_card">
                    <span class="dashboard_stat_label">Utilisateurs en attente</span>
                    <strong class="dashboard_stat_value"><?= (int)$inactive_users ?></strong>
                    <p>Comptes créés mais pas encore activés.</p>
                </article>

                <article class="dashboard_stat_card">
                    <span class="dashboard_stat_label">Commandes</span>
                    <strong class="dashboard_stat_value"><?= (int)$order_stats['total_orders'] ?></strong>
                    <p>Total des commandes enregistrées.</p>
                </article>

                <article class="dashboard_stat_card">
                    <span class="dashboard_stat_label">Montant en attente</span>
                    <strong class="dashboard_stat_value"><?= number_format((float)$order_stats['pending_amount'], 2, ',', ' ') ?> €</strong>
                    <p>Commandes encore non réglées.</p>
                </article>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Gestion</span>
                <h3>Outils de gestion</h3>
                <p>Toutes les actions et modules utiles regroupés ensemble.</p>
            </div>

            <div class="admin_management_grid">
                <a class="dashboard_action_card" href="index.php?controller=admin&action=showAllUsers">
                    <span class="dashboard_action_icon">👤</span>
                    <div>
                        <h3>Utilisateurs</h3>
                        <p>Gérer les comptes et consulter les profils.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=shop&action=manageShop">
                    <span class="dashboard_action_icon">🛒</span>
                    <div>
                        <h3>Boutique</h3>
                        <p>Accéder à la gestion de la boutique.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=admin&action=serverSettings">
                    <span class="dashboard_action_icon">⚙️</span>
                    <div>
                        <h3>Paramètres</h3>
                        <p>Consulter les réglages techniques du système.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=admin&action=payments">
                    <span class="dashboard_action_icon">💰</span>
                    <div>
                        <h3>Facturation</h3>
                        <p>Encaisser les commandes et enregistrer les paiements.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="#">
                    <span class="dashboard_action_icon">🗞️</span>
                    <div>
                        <h3>News</h3>
                        <p>Module prêt pour la gestion des annonces.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="#">
                    <span class="dashboard_action_icon">📅</span>
                    <div>
                        <h3>Événements</h3>
                        <p>Structure prête pour les événements internes.</p>
                    </div>
                </a>

                <a class="dashboard_action_card" href="index.php?controller=admin&action=logs">
                    <span class="dashboard_action_icon">📜</span>
                    <div>
                        <h3>Logs</h3>
                        <p>Préparer un accès centralisé aux journaux.</p>
                    </div>
                </a>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Suivi</span>
                <h3>Données détaillées</h3>
                <p>Les infos de gestion et de pilotage regroupées en dessous.</p>
            </div>

            <div class="admin_dashboard_content">
                <div class="admin_dashboard_column">
                    <section class="admin_panel_card">
                        <div class="section_heading compact">
                            <h3>Top débiteurs</h3>
                            <p>Les utilisateurs avec les notes les plus élevées.</p>
                        </div>

                        <?php if (empty($top_debtors)): ?>
                            <div class="empty_state">
                                <h3>Aucun débiteur</h3>
                                <p>Personne n’a de note en attente actuellement.</p>
                            </div>
                        <?php else: ?>
                            <div class="admin_debtors_list">
                                <?php foreach ($top_debtors as $debtor): ?>
                                    <article class="admin_debtor_card">
                                        <div>
                                            <h4><?= htmlspecialchars(trim(($debtor['firstname'] ?? '') . ' ' . ($debtor['lastname'] ?? ''))) ?></h4>
                                            <p><?= htmlspecialchars($debtor['username'] ?? '') ?> — <?= htmlspecialchars($debtor['unit'] ?? '') ?></p>
                                        </div>
                                        <strong><?= number_format((float)$debtor['note'], 2, ',', ' ') ?> €</strong>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="admin_dashboard_column wide">
                    <section class="admin_panel_card">
                        <div class="section_heading compact">
                            <h3>Commandes récentes</h3>
                            <p>Suivi rapide des dernières consommations enregistrées.</p>
                        </div>

                        <?php if (empty($recent_orders)): ?>
                            <div class="empty_state">
                                <h3>Aucune commande</h3>
                                <p>Il n’y a pas encore de commandes enregistrées.</p>
                            </div>
                        <?php else: ?>
                            <div class="admin_orders_list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <article class="admin_order_card">
                                        <div class="admin_order_head">
                                            <div>
                                                <h4>Commande #<?= (int)$order['id'] ?></h4>
                                                <p>
                                                    <?= htmlspecialchars(trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? ''))) ?>
                                                    <?php if (!empty($order['username'])): ?>
                                                        — <?= htmlspecialchars($order['username']) ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>

                                            <span class="order_status_badge status_<?= htmlspecialchars($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                        </div>

                                        <div class="admin_order_meta">
                                            <p>Lignes : <strong><?= (int)$order['item_lines'] ?></strong></p>
                                            <p>Total : <strong><?= number_format((float)$order['total_price'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong></p>
                                            <p>Date : <strong><?= htmlspecialchars($order['created_at']) ?></strong></p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>