<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_payments_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Facturation</span>
            <h2>Encaissement des commandes</h2>
            <p>
                Cette page permet d’enregistrer un paiement sur une commande passée,
                d’encaisser toute la note d’un utilisateur, ou de saisir un montant libre.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Filtre</span>
                <h3>Rechercher par utilisateur</h3>
            </div>

            <form method="get" action="index.php">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="payments">

                <label for="user_id">Utilisateur</label>
                <select name="user_id" id="user_id">
                    <option value="0">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= ((int)$filterUserId === (int)$user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '') . ' — ' . ($user['username'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Filtrer</button>
            </form>
        </section>

        <?php if ($filterUserId > 0): ?>
            <section class="admin_dashboard_section">
                <div class="section_heading">
                    <span class="section_kicker">Utilisateur filtré</span>
                    <h3>Encaissement global ou partiel</h3>
                    <p>
                        Note restante détectée :
                        <strong><?= number_format((float)$pendingTotalForUser, 2, ',', ' ') ?> €</strong>
                    </p>

                    <?php if ($pendingTotalForUser <= 0): ?>
                        <div class="empty_state">
                            <h3>Aucune note à encaisser</h3>
                            <p>Cet utilisateur n’a actuellement aucun montant dû.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($pendingTotalForUser > 0): ?>
                    <div class="admin_dashboard_content">
                        <div class="admin_dashboard_column">
                            <section class="admin_panel_card">
                                <div class="section_heading compact">
                                    <h3>Tout encaisser</h3>
                                    <p>Solde toutes les commandes passées en attente de cet utilisateur.</p>
                                </div>

                                <form
                                        method="post"
                                        action="index.php?controller=admin&action=captureUserBalance"
                                        onsubmit="return confirm('Confirmer l’encaissement total de la note de cet utilisateur ?');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$filterUserId ?>">

                                    <label for="bulk_method">Méthode</label>
                                    <select name="method" id="bulk_method" required>
                                        <option value="cash">Espèces</option>
                                        <option value="card">Carte</option>
                                        <option value="bank_transfer">Virement</option>
                                        <option value="internal">Interne</option>
                                    </select>

                                    <label for="bulk_provider">Prestataire</label>
                                    <input type="text" name="provider" id="bulk_provider" placeholder="Ex: SumUp, Stripe, caisse locale">

                                    <label for="bulk_provider_ref">Référence</label>
                                    <input type="text" name="provider_ref" id="bulk_provider_ref" placeholder="Référence globale de transaction">

                                    <button type="submit">Tout encaisser</button>
                                </form>
                            </section>
                        </div>

                        <div class="admin_dashboard_column wide">
                            <section class="admin_panel_card">
                                <div class="section_heading compact">
                                    <h3>Encaisser un montant libre</h3>
                                    <p>Exemple : 10 € sur une note totale de 35 €.</p>
                                </div>

                                <form
                                        method="post"
                                        action="index.php?controller=admin&action=captureUserCustomAmount"
                                        onsubmit="return confirm('Confirmer l’encaissement de ce montant pour cet utilisateur ?');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$filterUserId ?>">

                                    <label for="custom_amount">Montant à encaisser</label>
                                    <input
                                            type="number"
                                            name="amount"
                                            id="custom_amount"
                                            min="0.01"
                                            max="<?= number_format((float)$pendingTotalForUser, 2, '.', '') ?>"
                                            step="0.01"
                                            placeholder="Ex: 10.00"
                                            required
                                    >

                                    <label for="custom_method">Méthode</label>
                                    <select name="method" id="custom_method" required>
                                        <option value="cash">Espèces</option>
                                        <option value="card">Carte</option>
                                        <option value="bank_transfer">Virement</option>
                                        <option value="internal">Interne</option>
                                    </select>

                                    <label for="custom_provider">Prestataire</label>
                                    <input type="text" name="provider" id="custom_provider" placeholder="Ex: SumUp, Stripe, caisse locale">

                                    <label for="custom_provider_ref">Référence</label>
                                    <input type="text" name="provider_ref" id="custom_provider_ref" placeholder="Référence de transaction">

                                    <button type="submit">Encaisser ce montant</button>
                                </form>
                            </section>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">À encaisser</span>
                <h3>Commandes passées en attente de paiement</h3>
            </div>

            <?php if (empty($pendingOrders)): ?>
                <div class="empty_state">
                    <h3>Aucune commande en attente de paiement</h3>
                    <p>Tout est à jour pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="admin_orders_list">
                    <?php foreach ($pendingOrders as $order): ?>
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
                                <p>Déjà payé : <strong><?= number_format((float)$order['paid_amount'], 2, ',', ' ') ?> €</strong></p>
                                <p>Reste dû : <strong><?= number_format((float)$order['remaining_due'], 2, ',', ' ') ?> €</strong></p>
                                <p>Note utilisateur : <strong><?= number_format((float)$order['note'], 2, ',', ' ') ?> €</strong></p>
                                <p>Date : <strong><?= htmlspecialchars($order['created_at']) ?></strong></p>
                            </div>

                            <form
                                    method="post"
                                    action="index.php?controller=admin&action=capturePayment"
                                    onsubmit="return confirm('Confirmer l’encaissement du reste de cette commande ?');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                                <label for="method_<?= (int)$order['id'] ?>">Méthode</label>
                                <select name="method" id="method_<?= (int)$order['id'] ?>" required>
                                    <option value="cash">Espèces</option>
                                    <option value="card">Carte</option>
                                    <option value="bank_transfer">Virement</option>
                                    <option value="internal">Interne</option>
                                </select>

                                <label for="provider_<?= (int)$order['id'] ?>">Prestataire</label>
                                <input type="text" name="provider" id="provider_<?= (int)$order['id'] ?>" placeholder="Ex: SumUp, Stripe, caisse locale">

                                <label for="provider_ref_<?= (int)$order['id'] ?>">Référence</label>
                                <input type="text" name="provider_ref" id="provider_ref_<?= (int)$order['id'] ?>" placeholder="Référence de transaction">

                                <button type="submit">Encaisser le reste de cette commande</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Historique</span>
                <h3>Derniers paiements</h3>
            </div>

            <?php if (empty($recentPayments)): ?>
                <div class="empty_state">
                    <h3>Aucun paiement</h3>
                    <p>Aucun règlement n’a encore été enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table_wrapper">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Commande</th>
                            <th>Payer</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td>#<?= (int)$payment['id'] ?></td>
                                <td><?= !empty($payment['order_id']) ? '#' . (int)$payment['order_id'] : '—' ?></td>
                                <td>
                                    <?= htmlspecialchars(trim(($payment['payer_firstname'] ?? '') . ' ' . ($payment['payer_lastname'] ?? ''))) ?>
                                    <?php if (!empty($payment['payer_username'])): ?>
                                        — <?= htmlspecialchars($payment['payer_username']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format((float)$payment['amount_paid'], 2, ',', ' ') ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                <td><?= htmlspecialchars($payment['method'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($payment['status']) ?></td>
                                <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>