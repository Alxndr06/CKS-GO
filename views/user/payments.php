<?php require_once __DIR__ . '/../partials/header.php'; ?>
<?php
$methodLabels = [
    'cash' => 'Espèces',
    'card' => 'Carte',
    'bank_transfer' => 'Virement',
    'internal' => 'Interne',
    'credit' => 'Avoir CKS GO',
];
?>

<main class="main_part user_payments_page">
    <section class="user_page_intro">
        <span class="section_kicker">Historique</span>
        <h2>Mes paiements</h2>
        <p>Tous les paiements enregistrés sur ton compte et tes commandes.</p>
    </section>

    <section class="admin_dashboard_section">
        <form method="get" action="index.php" class="admin_catalog_search_form">
            <input type="hidden" name="controller" value="user">
            <input type="hidden" name="action" value="payments">

            <div class="search_row">
                <input
                        type="text"
                        name="q"
                        value="<?= htmlspecialchars($q ?? '') ?>"
                        placeholder="Rechercher par paiement, commande, méthode ou statut."
                >
                <button type="submit">Rechercher</button>
            </div>
        </form>

        <?php if (empty($payments)): ?>
            <div class="empty_state">
                <h3>Aucun paiement trouvé</h3>
                <p>Aucun résultat pour cette recherche.</p>
            </div>
        <?php else: ?>
            <div class="user_payment_list">
                <?php foreach ($payments as $payment): ?>
                    <article class="user_payment_card">
                        <div>
                            <h4>Paiement #<?= (int) $payment['id'] ?></h4>
                            <?php if (!empty($payment['order_id'])): ?>
                                <p>Commande #<?= (int) $payment['order_id'] ?></p>
                            <?php else: ?>
                                <p>Paiement libre sur le solde du compte</p>
                            <?php endif; ?>
                            <p><?= !empty($payment['payment_date']) ? date('d/m/Y H:i', strtotime($payment['payment_date'])) : '-' ?></p>
                        </div>

                        <div class="user_payment_meta">
                            <p>Montant : <strong><?= number_format((float) $payment['amount_paid'], 2, ',', ' ') ?> €</strong></p>
                            <p>Méthode : <strong><?= htmlspecialchars($methodLabels[(string)($payment['method'] ?? '')] ?? (string)($payment['method'] ?? '—')) ?></strong></p>
                            <p>Statut : <strong><?= htmlspecialchars((string) ($payment['status'] ?? '—')) ?></strong></p>
                            <?php if (!empty($payment['provider_ref'])): ?>
                                <p>Réf. : <strong><?= htmlspecialchars((string) $payment['provider_ref']) ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <?php
                $baseUrl = 'index.php?controller=user&action=payments';
                $querySuffix = ($q !== null && $q !== '') ? '&q=' . urlencode($q) : '';
                $currentPage = (int) ($page ?? 1);
                $pagesCount = (int) ($totalPages ?? 1);
                ?>

                <nav class="apl_pagination" aria-label="Pagination des paiements">
                    <?php if ($currentPage > 1): ?>
                        <a class="apl_page_link" href="<?= $baseUrl . $querySuffix . '&page=' . ($currentPage - 1) ?>">Précédent</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $pagesCount; $i++): ?>
                        <a
                                class="apl_page_link <?= $i === $currentPage ? 'is_active' : '' ?>"
                                href="<?= $baseUrl . $querySuffix . '&page=' . $i ?>"
                        >
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $pagesCount): ?>
                        <a class="apl_page_link" href="<?= $baseUrl . $querySuffix . '&page=' . ($currentPage + 1) ?>">Suivant</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
