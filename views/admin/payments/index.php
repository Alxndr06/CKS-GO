<?php
require_once __DIR__ . '/../../partials/header.php';

$users = is_array($users ?? null) ? $users : [];
$pendingOrders = is_array($pendingOrders ?? null) ? $pendingOrders : [];
$recentPayments = is_array($recentPayments ?? null) ? $recentPayments : [];
$balanceMovements = is_array($balanceMovements ?? null) ? $balanceMovements : [];
$selectedUser = is_array($selectedUser ?? null) ? $selectedUser : null;
$filterUserId = (int)($filterUserId ?? 0);
$userSearch = trim((string)($userSearch ?? ''));
$pendingUsersOnly = !empty($pendingUsersOnly);
$captureToken = (string)($captureToken ?? '');

$methodLabels = [
    'cash' => 'Espèces',
    'card' => 'Carte',
    'bank_transfer' => 'Virement',
    'internal' => 'Interne',
    'credit' => 'Avoir CKS GO',
];

$selectedName = '';
$accountBalance = 0.0;
$accountBalanceCents = 0;
$orderDue = 0.0;
$orderDueCents = 0;
$legacyDue = 0.0;
$selectedIsActive = true;
$defaultPaymentMode = 'free';

if ($selectedUser) {
    $selectedName = trim(((string)($selectedUser['firstname'] ?? '')) . ' ' . ((string)($selectedUser['lastname'] ?? '')));
    if ($selectedName === '') {
        $selectedName = (string)($selectedUser['username'] ?? 'Utilisateur');
    }

    $accountBalance = (float)($selectedUser['note'] ?? 0);
    $accountBalanceCents = (int)round($accountBalance * 100);
    $selectedIsActive = (int)($selectedUser['is_active'] ?? 0) === 1;

    foreach ($pendingOrders as $order) {
        $orderDue += max(0, (float)($order['remaining_due'] ?? 0));
    }
    $orderDueCents = (int)round($orderDue * 100);
    $legacyDue = max(0, $accountBalance - $orderDue);
    $defaultPaymentMode = !empty($pendingOrders) ? 'orders' : 'free';
}

$recentGroups = [];
foreach ($recentPayments as $payment) {
    $batchId = (int)($payment['batch_id'] ?? 0);
    $paymentId = (int)($payment['id'] ?? 0);
    $key = $batchId > 0 ? 'batch-' . $batchId : 'payment-' . $paymentId;

    if (!isset($recentGroups[$key])) {
        $recentGroups[$key] = [
            'label' => $batchId > 0 ? 'Encaissement #' . $batchId : 'Paiement #' . $paymentId,
            'amount' => 0.0,
            'method' => (string)($payment['method'] ?? ''),
            'date' => (string)($payment['payment_date'] ?? ''),
            'orders' => [],
        ];
    }

    $recentGroups[$key]['amount'] += (float)($payment['amount_paid'] ?? 0);
    if (!empty($payment['order_id'])) {
        $recentGroups[$key]['orders'][] = (int)$payment['order_id'];
    }
}
?>

<main class="main_part admin_dashboard_page payflow_page">
    <section class="admin_dashboard_intro admin_dashboard_intro_compact payflow_intro">
        <span class="section_kicker">Paiements</span>
        <h2>Encaisser</h2>
        <p>Sélectionne un utilisateur, puis règle ses commandes ou saisis directement le montant reçu.</p>
    </section>

    <?php if (!$selectedUser): ?>
        <section class="payflow_directory">
            <form method="get" action="index.php" class="payflow_search">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="payments">

                <label class="payflow_search_field" for="payment-user-search">
                    <span>Rechercher un utilisateur</span>
                    <input id="payment-user-search" type="search" name="user_search" value="<?= htmlspecialchars($userSearch) ?>" autocomplete="off">
                </label>

                <label class="payflow_checkline" for="payment-balances-only">
                    <input id="payment-balances-only" type="checkbox" name="pending_users_only" value="1" <?= $pendingUsersOnly ? 'checked' : '' ?>>
                    <span>Afficher uniquement les soldes à traiter</span>
                </label>

                <button type="submit" class="upv2_button is_primary">Rechercher</button>
                <?php if ($userSearch !== '' || $pendingUsersOnly): ?>
                    <a class="upv2_button" href="index.php?controller=admin&amp;action=payments">Effacer</a>
                <?php endif; ?>
            </form>

            <div class="payflow_user_list">
                <?php if (empty($users)): ?>
                    <div class="payflow_empty">
                        <?= renderUiIcon('users') ?>
                        <h3>Aucun utilisateur trouvé</h3>
                        <p>Modifie les critères de recherche pour poursuivre.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int)($user['id'] ?? 0);
                        $name = trim(((string)($user['firstname'] ?? '')) . ' ' . ((string)($user['lastname'] ?? '')));
                        $name = $name !== '' ? $name : (string)($user['username'] ?? 'Utilisateur');
                        $balance = (float)($user['note'] ?? 0);
                        $pendingTotal = (float)($user['pending_total'] ?? 0);
                        $isActive = (int)($user['is_active'] ?? 0) === 1;
                        ?>
                        <a class="payflow_user_row" href="index.php?controller=admin&amp;action=payments&amp;user_id=<?= $userId ?>">
                            <span class="payflow_user_avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($name, 0, 2))) ?></span>
                            <span class="payflow_user_identity">
                                <strong><?= htmlspecialchars($name) ?></strong>
                                <small>@<?= htmlspecialchars((string)($user['username'] ?? '-')) ?> · <?= htmlspecialchars((string)($user['email'] ?? '-')) ?></small>
                            </span>
                            <span class="payflow_user_state <?= $isActive ? 'is_active' : 'is_inactive' ?>"><?= $isActive ? 'Actif' : 'Inactif' ?></span>
                            <span class="payflow_user_amount">
                                <?php if ($balance < 0): ?>
                                    <small>Avoir</small><strong class="is_credit"><?= number_format(abs($balance), 2, ',', ' ') ?> €</strong>
                                <?php elseif ($balance > 0): ?>
                                    <small>Solde</small><strong class="is_due"><?= number_format($balance, 2, ',', ' ') ?> €</strong>
                                <?php else: ?>
                                    <small>Solde</small><strong>0,00 €</strong>
                                <?php endif; ?>
                            </span>
                            <span class="payflow_user_orders"><strong><?= (int)($user['pending_orders_count'] ?? 0) ?></strong><small>commande(s) · <?= number_format($pendingTotal, 2, ',', ' ') ?> €</small></span>
                            <span class="payflow_user_open">Ouvrir</span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="payflow_context">
            <a class="payflow_back" href="index.php?controller=admin&amp;action=payments"><?= renderUiIcon('back') ?> Changer d’utilisateur</a>
            <div class="payflow_identity">
                <span class="payflow_identity_avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($selectedName, 0, 2))) ?></span>
                <div>
                    <span>Encaissement pour</span>
                    <h3><?= htmlspecialchars($selectedName) ?></h3>
                    <p>@<?= htmlspecialchars((string)($selectedUser['username'] ?? '-')) ?> · <?= htmlspecialchars((string)($selectedUser['email'] ?? '-')) ?></p>
                </div>
                <span class="payflow_user_state <?= $selectedIsActive ? 'is_active' : 'is_inactive' ?>"><?= $selectedIsActive ? 'Compte actif' : 'Compte inactif · encaissement autorisé' ?></span>
            </div>
            <div class="payflow_balance_cards">
                <article>
                    <span>Commandes ouvertes</span>
                    <strong><?= number_format($orderDue, 2, ',', ' ') ?> €</strong>
                    <small><?= count($pendingOrders) ?> commande(s)</small>
                </article>
                <article class="<?= $accountBalance < 0 ? 'is_credit' : ($accountBalance > 0 ? 'is_due' : '') ?>">
                    <span><?= $accountBalance < 0 ? 'Avoir disponible' : 'Solde du compte' ?></span>
                    <strong><?= number_format(abs($accountBalance), 2, ',', ' ') ?> €</strong>
                    <small>
                        <?php if ($accountBalance < 0): ?>
                            Sera utilisé sur les prochains achats
                        <?php elseif ($accountBalance > 0 && $legacyDue > 0.009): ?>
                            Dont <?= number_format($legacyDue, 2, ',', ' ') ?> € de solde historique
                        <?php elseif ($accountBalance > 0): ?>
                            Montant restant à régler
                        <?php else: ?>
                            Compte à jour
                        <?php endif; ?>
                    </small>
                </article>
            </div>
        </section>

        <form
            method="post"
            action="index.php?controller=admin&amp;action=captureUserPayment"
            class="payflow_workflow"
            data-payment-workflow
            data-balance-cents="<?= $accountBalanceCents ?>"
            data-order-due-cents="<?= $orderDueCents ?>"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="user_id" value="<?= $filterUserId ?>">
            <input type="hidden" name="payment_token" value="<?= htmlspecialchars($captureToken) ?>">

            <div class="payflow_main">
                <section class="payflow_card payflow_mode_card">
                    <header>
                        <span class="payflow_step">1</span>
                        <div><h3>Que souhaites-tu encaisser ?</h3><p>Choisis des commandes précises ou indique le montant réellement reçu.</p></div>
                    </header>

                    <div class="payflow_modes" role="radiogroup" aria-label="Mode d’encaissement">
                        <label class="payflow_mode <?= empty($pendingOrders) ? 'is_disabled' : '' ?>">
                            <input type="radio" name="payment_mode" value="orders" <?= $defaultPaymentMode === 'orders' ? 'checked' : '' ?> <?= !empty($pendingOrders) ? '' : 'disabled' ?>>
                            <span><?= renderUiIcon('orders') ?><strong>Commandes</strong><small>Solder une ou plusieurs commandes.</small></span>
                        </label>
                        <label class="payflow_mode">
                            <input type="radio" name="payment_mode" value="free" <?= $defaultPaymentMode === 'free' ? 'checked' : '' ?>>
                            <span><?= renderUiIcon('payment') ?><strong>Montant libre</strong><small>Paiement partiel, ancienne note ou création d’un avoir.</small></span>
                        </label>
                    </div>
                </section>

                <section class="payflow_card payflow_orders_panel" data-payment-orders-panel <?= empty($pendingOrders) ? 'hidden' : '' ?>>
                    <header>
                        <span class="payflow_step">2</span>
                        <div><h3>Commandes à régler</h3><p>Chaque commande sélectionnée sera entièrement soldée.</p></div>
                        <label class="payflow_select_all"><input type="checkbox" data-payment-select-all> Tout sélectionner</label>
                    </header>

                    <div class="payflow_order_list">
                        <?php foreach ($pendingOrders as $order): ?>
                            <?php
                            $orderId = (int)($order['id'] ?? 0);
                            $remainingDue = max(0, (float)($order['remaining_due'] ?? 0));
                            $remainingDueCents = (int)round($remainingDue * 100);
                            $createdAt = !empty($order['created_at']) ? date('d/m/Y à H:i', strtotime((string)$order['created_at'])) : '-';
                            ?>
                            <label class="payflow_order">
                                <input type="checkbox" name="order_ids[]" value="<?= $orderId ?>" data-due-cents="<?= $remainingDueCents ?>">
                                <span class="payflow_order_check" aria-hidden="true"></span>
                                <span class="payflow_order_identity"><strong>Commande #<?= $orderId ?></strong><small><?= htmlspecialchars($createdAt) ?> · <?= (int)($order['item_lines'] ?? 0) ?> ligne(s)</small></span>
                                <span><small>Total</small><strong><?= number_format((float)($order['total_price'] ?? 0), 2, ',', ' ') ?> €</strong></span>
                                <span><small>Déjà réglé</small><strong><?= number_format((float)($order['paid_amount'] ?? 0), 2, ',', ' ') ?> €</strong></span>
                                <span class="payflow_order_due"><small>Reste dû</small><strong><?= number_format($remainingDue, 2, ',', ' ') ?> €</strong></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="payflow_card payflow_free_panel" data-payment-free-panel <?= $defaultPaymentMode === 'free' ? '' : 'hidden' ?>>
                    <header>
                        <span class="payflow_step">2</span>
                        <div><h3>Montant reçu</h3><p>Il sera affecté aux commandes les plus anciennes, puis au reliquat de l’ancienne note.</p></div>
                    </header>
                    <label class="payflow_amount_field" for="payflow-amount">
                        <span>Montant à encaisser</span>
                        <span class="payflow_amount_input"><input id="payflow-amount" type="number" name="amount" min="0.01" step="0.01" inputmode="decimal"><b>€</b></span>
                    </label>
                    <div class="payflow_credit_notice">
                        <?= renderUiIcon('shield') ?>
                        <p>Si le paiement dépasse le solde total du compte, la différence est conservée comme avoir.</p>
                    </div>
                </section>

                <section class="payflow_card payflow_method_card">
                    <header>
                        <span class="payflow_step">3</span>
                        <div><h3>Informations de paiement</h3><p>Le moyen est obligatoire. Les références restent facultatives.</p></div>
                    </header>
                    <div class="payflow_fields">
                        <label><span>Moyen de paiement</span><select name="method" required><option value="cash">Espèces</option><option value="card">Carte</option><option value="bank_transfer">Virement</option><option value="internal">Interne</option></select></label>
                        <label><span>Prestataire</span><input type="text" name="provider" maxlength="50"></label>
                        <label><span>Référence de transaction</span><input type="text" name="provider_ref" maxlength="100"></label>
                    </div>
                </section>
            </div>

            <aside class="payflow_summary">
                <div class="payflow_summary_card">
                    <span class="section_kicker">Récapitulatif</span>
                    <h3>Encaissement en cours</h3>
                    <dl>
                        <div><dt>Utilisateur</dt><dd><?= htmlspecialchars($selectedName) ?></dd></div>
                        <div><dt data-payment-count-label>Commandes</dt><dd data-payment-count>0</dd></div>
                        <div><dt>Montant reçu</dt><dd class="payflow_summary_amount" data-payment-total>0,00 €</dd></div>
                        <div><dt>Nouveau solde</dt><dd data-payment-balance><?= $accountBalance < 0 ? 'Avoir ' . number_format(abs($accountBalance), 2, ',', ' ') . ' €' : number_format($accountBalance, 2, ',', ' ') . ' € à régler' ?></dd></div>
                    </dl>
                    <p class="payflow_summary_hint" data-payment-hint>Sélectionne au moins une commande.</p>
                    <button type="submit" class="payflow_submit" data-payment-submit disabled><?= renderUiIcon('payment') ?> Vérifier et encaisser</button>
                    <small>L’encaissement ne sera enregistré qu’après confirmation.</small>
                </div>

                <div class="payflow_history_card">
                    <div><span class="section_kicker">Historique</span><h3>Derniers mouvements</h3></div>
                    <?php if (empty($recentGroups)): ?>
                        <p class="payflow_history_empty">Aucun paiement enregistré.</p>
                    <?php else: ?>
                        <div class="payflow_history_list">
                            <?php foreach (array_slice($recentGroups, 0, 6) as $group): ?>
                                <?php $groupOrders = array_values(array_unique($group['orders'])); ?>
                                <article>
                                    <div><strong><?= htmlspecialchars($group['label']) ?></strong><small><?= $group['date'] !== '' ? htmlspecialchars(date('d/m/Y à H:i', strtotime($group['date']))) : '-' ?></small></div>
                                    <div><strong><?= number_format((float)$group['amount'], 2, ',', ' ') ?> €</strong><small><?= htmlspecialchars($methodLabels[$group['method']] ?? $group['method']) ?><?= !empty($groupOrders) ? ' · ' . count($groupOrders) . ' cmd' : '' ?></small></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </form>

        <dialog class="payflow_dialog" data-payment-dialog>
            <form method="dialog">
                <span class="payflow_dialog_icon"><?= renderUiIcon('payment') ?></span>
                <span class="section_kicker">Dernière vérification</span>
                <h3>Confirmer l’encaissement ?</h3>
                <p data-payment-dialog-description></p>
                <div class="payflow_dialog_balance" data-payment-dialog-balance></div>
                <div class="payflow_dialog_actions">
                    <button type="submit" value="cancel" class="upv2_button">Revenir</button>
                    <button type="button" class="upv2_button is_primary" data-payment-dialog-confirm>Confirmer l’encaissement</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
