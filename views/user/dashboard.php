<?php
require_once __DIR__ . '/../partials/header.php';

$firstName = trim((string)($user['firstname'] ?? ''));
$lastName = trim((string)($user['lastname'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);
$accountBalance = (float)($user['note'] ?? 0);
$balanceTone = $accountBalance < 0
    ? 'is_credit'
    : ($accountBalance >= 15 ? 'is_danger' : ($accountBalance >= 8 ? 'is_warning' : 'is_clear'));
$initials = mb_strtoupper(
    mb_substr($firstName !== '' ? $firstName : 'U', 0, 1)
    . mb_substr($lastName, 0, 1)
);
?>

    <main class="main_part user_dashboard_page">
        <section class="user_dashboard_hero user_dashboard_hero_compact">
            <div class="user_dashboard_hero_text">
                <span class="section_kicker">Espace utilisateur</span>
                <h1>Bonjour <?= htmlspecialchars($firstName !== '' ? $firstName : 'à vous') ?>.</h1>
                <p>Votre espace personnel CKS GO, simplement.</p>
            </div>

            <div class="user_dashboard_identity">
                <div class="user_dashboard_identity_head">
                    <span class="user_dashboard_identity_avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                    <div>
                        <strong><?= htmlspecialchars($fullName !== '' ? $fullName : 'Utilisateur') ?></strong>
                        <small><?= htmlspecialchars($user['email'] ?? '') ?></small>
                    </div>
                </div>

                <div class="user_dashboard_identity_meta">
                    <span>Service <strong><?= htmlspecialchars($user['unit'] ?? '') ?></strong></span>
                    <span>Rôle <strong><?= htmlspecialchars(getRoleLabel($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>
        </section>

        <section class="user_dashboard_stats">
            <article class="dashboard_stat_card dashboard_balance_card <?= $balanceTone ?>">
                <span class="dashboard_stat_label"><?= $accountBalance < 0 ? 'Avoir disponible' : 'Solde actuel' ?></span>
                <strong class="dashboard_stat_value"><?= number_format(abs($accountBalance), 2, ',', ' ') ?> €</strong>
                <p><?= $accountBalance < 0 ? 'Cet avoir sera utilisé automatiquement sur tes prochains achats.' : ($accountBalance > 0 ? 'Montant restant actuellement à régler.' : 'Ton compte est à jour.') ?></p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Commandes</span>
                <strong class="dashboard_stat_value"><?= (int)($orderStats['orders_total'] ?? 0) ?></strong>
                <p>Total des commandes enregistrées.</p>
            </article>

            <article class="dashboard_stat_card">
                <span class="dashboard_stat_label">Montant cumulé</span>
                <strong class="dashboard_stat_value"><?= number_format((float)($orderStats['orders_total_amount'] ?? 0), 2, ',', ' ') ?> €</strong>
                <p>Total historique de tes commandes.</p>
            </article>
        </section>

        <section class="user_dashboard_actions">
            <a class="dashboard_action_card is_sky" href="index.php?controller=shop&action=index">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('shop') ?></span>
                <div>
                    <h3>Boutique</h3>
                    <p>Continuer tes achats et découvrir les produits disponibles.</p>
                </div>
            </a>

            <a class="dashboard_action_card is_mint" href="index.php?controller=shop&action=cart">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('cart') ?></span>
                <div>
                    <h3>Mon panier</h3>
                    <p>Consulter, modifier et valider ton panier actuel.</p>
                </div>
            </a>

            <a class="dashboard_action_card is_gold" href="index.php?controller=user&action=orders">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                <div>
                    <h3>Mes commandes</h3>
                    <p>Retrouver tout ton historique, rechercher et ouvrir chaque détail.</p>
                </div>
            </a>

            <a class="dashboard_action_card is_navy" href="index.php?controller=user&action=payments">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                <div>
                    <h3>Mes paiements</h3>
                    <p>Consulter les paiements déjà enregistrés sur ton compte.</p>
                </div>
            </a>

            <a class="dashboard_action_card is_coral" href="index.php?controller=user&action=account">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('user') ?></span>
                <div>
                    <h3>Mon compte</h3>
                    <p>Modifier ton e-mail, ton mot de passe ou supprimer ton compte.</p>
                </div>
            </a>

            <a class="dashboard_action_card is_violet" href="index.php?controller=user&action=tickets">
                <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('ticket') ?></span>
                <div>
                    <h3>Mes tickets</h3>
                    <p>Créer un ticket et suivre les échanges avec l’administration.</p>
                </div>
            </a>
        </section>

    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
