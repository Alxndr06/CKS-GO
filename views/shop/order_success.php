<?php
require_once __DIR__ . '/../partials/header.php';
?>

    <main class="main_part order_success_page">
        <section class="order_success_intro">
            <span class="section_kicker">Commande</span>
            <h2>Commande validée</h2>
            <p>
                Ta commande a bien été créée et est actuellement en attente de paiement.
            </p>
        </section>

        <section class="order_success_layout">
            <div class="order_success_card">
                <h3>Commande #<?= (int)$order['id'] ?></h3>

                <div class="order_success_meta">
                    <p>Statut : <strong><?= htmlspecialchars($order['status']) ?></strong></p>
                    <p>Total : <strong><?= number_format((float)$order['total_price'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong></p>
                    <p>Date : <strong><?= htmlspecialchars($order['created_at']) ?></strong></p>
                </div>

                <div class="order_success_items">
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="order_success_item">
                            <div>
                                <p class="order_item_name"><?= htmlspecialchars($item['product_name']) ?></p>
                                <p class="order_item_variant">Variante : <?= htmlspecialchars($item['display_variant']) ?></p>
                            </div>
                            <div class="order_item_totals">
                                <span>x<?= (int)$item['quantity'] ?></span>
                                <strong><?= number_format((float)$item['line_total'], 2, ',', ' ') ?> €</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order_success_actions">
                    <a class="home_btn home_btn_secondary" href="index.php?controller=shop&action=index">Retour boutique</a>
                    <a class="home_btn home_btn_primary" href="index.php?controller=user&action=dashboard">Voir mon espace</a>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>