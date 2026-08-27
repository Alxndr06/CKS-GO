<?php
require_once __DIR__ . '/../../partials/header.php';

$payment = is_array($payment ?? null) ? $payment : [];

$paymentId = (int) ($payment['id'] ?? 0);
$orderId = (int) ($payment['order_id'] ?? 0);
$payerId = (int) ($payment['payer_user_id'] ?? 0);
$adminUserId = (int) ($payment['admin_user_id'] ?? 0);

$status = (string) ($payment['status'] ?? '');
$method = (string) ($payment['method'] ?? '');
$currency = (string) ($payment['currency'] ?? 'EUR');
$provider = trim((string) ($payment['provider'] ?? ''));
$providerRef = trim((string) ($payment['provider_ref'] ?? ''));

$statusLabels = [
    'captured' => 'Capturé',
    'pending' => 'En attente',
    'failed' => 'Échoué',
    'refunded' => 'Remboursé',
];

$methodLabels = [
    'cash' => 'Espèces',
    'card' => 'Carte',
    'bank_transfer' => 'Virement',
    'internal' => 'Interne',
    'credit' => 'Avoir CKS GO',
];

$payerName = trim(((string) ($payment['payer_firstname'] ?? '')) . ' ' . ((string) ($payment['payer_lastname'] ?? '')));
if ($payerName === '') {
    $payerName = (string) ($payment['payer_username'] ?? 'Utilisateur');
}

$adminName = trim(((string) ($payment['admin_firstname'] ?? '')) . ' ' . ((string) ($payment['admin_lastname'] ?? '')));
if ($adminName === '') {
    $adminName = (string) ($payment['admin_username'] ?? 'Administrateur');
}

$paymentDateRaw = (string) ($payment['payment_date'] ?? '');
$paymentDateLabel = $paymentDateRaw !== '' ? date('d/m/Y H:i', strtotime($paymentDateRaw)) : '—';
$orderDateRaw = (string) ($payment['order_created_at'] ?? '');
$orderDateLabel = $orderDateRaw !== '' ? date('d/m/Y H:i', strtotime($orderDateRaw)) : '—';
?>

<main class="main_part admin_dashboard_page admin_payments_page">
    <section class="page_heading">
        <div>
            <span class="section_kicker">Paiement</span>
            <h2>Paiement #<?= $paymentId ?></h2>
            <p>Fiche détaillée du règlement enregistré, avec ses rattachements admin, utilisateur et commande.</p>
        </div>

        <div class="page_heading_actions">
            <a class="btn_link btn_link_secondary" href="index.php?controller=admin&action=payments">Retour aux paiements</a>
            <?php if ($orderId > 0): ?>
                <a class="btn_link btn_link_secondary" href="index.php?controller=admin&action=showOrder&id=<?= $orderId ?>">Voir la commande</a>
            <?php endif; ?>
            <?php if ($payerId > 0): ?>
                <a class="btn_link" href="index.php?controller=admin&action=showUser&id=<?= $payerId ?>">Voir l'utilisateur</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <article class="admin_panel_card">
            <div class="section_heading compact">
                <h3>Résumé</h3>
                <p>Les informations les plus utiles au même endroit.</p>
            </div>

            <div class="ticket_meta_grid">
                <div class="ticket_meta_item">
                    <span>Montant</span>
                    <strong><?= number_format((float) ($payment['amount_paid'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></strong>
                </div>

                <div class="ticket_meta_item">
                    <span>Statut</span>
                    <strong><?= htmlspecialchars($statusLabels[$status] ?? ($status !== '' ? $status : '—')) ?></strong>
                </div>

                <div class="ticket_meta_item">
                    <span>Méthode</span>
                    <strong><?= htmlspecialchars($methodLabels[$method] ?? ($method !== '' ? $method : '—')) ?></strong>
                </div>

                <div class="ticket_meta_item">
                    <span>Enregistré le</span>
                    <strong><?= htmlspecialchars($paymentDateLabel) ?></strong>
                </div>
            </div>
        </article>
    </section>

    <section class="admin_dashboard_section">
        <div class="paymx_layout">
            <div class="paymx_main_column">
                <article class="admin_panel_card">
                    <div class="section_heading compact">
                        <h3>Références</h3>
                        <p>Les liens rapides vers les éléments concernés par le paiement.</p>
                    </div>

                    <div class="aushow_rows">
                        <div class="aushow_row">
                            <span>Commande liée</span>
                            <strong>
                                <?php if ($orderId > 0): ?>
                                    <a class="admin_logs_link" href="index.php?controller=admin&action=showOrder&id=<?= $orderId ?>">Commande #<?= $orderId ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="aushow_row">
                            <span>Total commande</span>
                            <strong>
                                <?= $orderId > 0 ? number_format((float) ($payment['order_total_price'] ?? 0), 2, ',', ' ') . ' ' . htmlspecialchars($currency) : '—' ?>
                            </strong>
                        </div>

                        <div class="aushow_row">
                            <span>Statut commande</span>
                            <strong><?= $orderId > 0 ? htmlspecialchars((string) ($payment['order_status'] ?? '—')) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Création commande</span>
                            <strong><?= $orderId > 0 ? htmlspecialchars($orderDateLabel) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Prestataire</span>
                            <strong><?= $provider !== '' ? htmlspecialchars($provider) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Référence externe</span>
                            <strong><?= $providerRef !== '' ? htmlspecialchars($providerRef) : '—' ?></strong>
                        </div>
                    </div>
                </article>
            </div>

            <aside class="paymx_side_column">
                <article class="admin_panel_card">
                    <div class="section_heading compact">
                        <h3>Utilisateur concerné</h3>
                        <p>Le compte sur lequel ce paiement a été enregistré.</p>
                    </div>

                    <div class="aushow_rows">
                        <div class="aushow_row">
                            <span>Utilisateur</span>
                            <strong>
                                <?php if ($payerId > 0): ?>
                                    <a class="admin_logs_link" href="index.php?controller=admin&action=showUser&id=<?= $payerId ?>">
                                        <?= htmlspecialchars($payerName) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($payerName) ?>
                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="aushow_row">
                            <span>Pseudo</span>
                            <strong><?= !empty($payment['payer_username']) ? htmlspecialchars((string) $payment['payer_username']) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Email</span>
                            <strong><?= !empty($payment['payer_email']) ? htmlspecialchars((string) $payment['payer_email']) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Service</span>
                            <strong><?= !empty($payment['payer_unit']) ? htmlspecialchars((string) $payment['payer_unit']) : '—' ?></strong>
                        </div>
                    </div>
                </article>

                <article class="admin_panel_card">
                    <div class="section_heading compact">
                        <h3>Traçabilité admin</h3>
                        <p>Qui a enregistré ce paiement.</p>
                    </div>

                    <div class="aushow_rows">
                        <div class="aushow_row">
                            <span>Administrateur</span>
                            <strong>
                                <?php if ($adminUserId > 0): ?>
                                    <a class="admin_logs_link" href="index.php?controller=admin&action=showUser&id=<?= $adminUserId ?>">
                                        <?= htmlspecialchars($adminName) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($adminName) ?>
                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="aushow_row">
                            <span>Pseudo admin</span>
                            <strong><?= !empty($payment['admin_username']) ? htmlspecialchars((string) $payment['admin_username']) : '—' ?></strong>
                        </div>

                        <div class="aushow_row">
                            <span>Email admin</span>
                            <strong><?= !empty($payment['admin_email']) ? htmlspecialchars((string) $payment['admin_email']) : '—' ?></strong>
                        </div>
                    </div>
                </article>
            </aside>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
