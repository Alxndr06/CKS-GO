<?php
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/../../../helpers/functions.php';

if (!empty($user)) {
    $userId = (int)($user['id'] ?? 0);
    $isLocked = (int)($user['is_locked'] ?? 0) === 1;
    $isActive = (int)($user['is_active'] ?? 0) === 1;
    $isEmailVerified = !empty($user['email_verified_at']);
    $role = normalizeUserRole($user['role'] ?? 'user');

    $firstname = trim((string)($user['firstname'] ?? ''));
    $lastname = trim((string)($user['lastname'] ?? ''));
    $username = trim((string)($user['username'] ?? 'Utilisateur'));
    $email = trim((string)($user['email'] ?? ''));
    $unit = trim((string)($user['unit'] ?? ''));
    $displayName = trim($firstname . ' ' . $lastname) ?: $username;
    $initials = mb_strtoupper(
        mb_substr($firstname !== '' ? $firstname : $username, 0, 1)
        . mb_substr($lastname, 0, 1)
    );

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($username !== '' ? $username : 'U', 0, 2));
    }

    $createdAtFormatted = !empty($user['created_at'])
        ? date('d/m/Y', strtotime((string)$user['created_at']))
        : 'Non renseignée';
    $emailVerifiedFormatted = $isEmailVerified
        ? date('d/m/Y à H:i', strtotime((string)$user['email_verified_at']))
        : 'Non vérifiée';

    $commerceStats = is_array($commerceStats ?? null) ? $commerceStats : [];
    $ordersTotal = (int)($commerceStats['orders_total'] ?? 0);
    $ordersPaid = (int)($commerceStats['orders_paid'] ?? 0);
    $ordersPending = (int)($commerceStats['orders_pending_payment'] ?? 0);
    $totalSpent = (float)($commerceStats['paid_amount'] ?? 0);
    $pendingAmount = (float)($commerceStats['pending_amount'] ?? 0);
    $noteAmount = (float)($user['note'] ?? 0);
    $lastOrder = is_array($commerceStats['last_order'] ?? null) ? $commerceStats['last_order'] : null;
    $favoriteProduct = is_array($commerceStats['favorite_product'] ?? null) ? $commerceStats['favorite_product'] : null;
    $lastPayment = is_array($lastPayment ?? null) ? $lastPayment : null;

    $statusLabels = [
        'pending_payment' => 'Paiement en attente',
        'paid' => 'Payée',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée',
        'partially_refunded' => 'Partiellement remboursée',
    ];

    $lastOrderTitle = $lastOrder && !empty($lastOrder['created_at'])
        ? date('d/m/Y à H:i', strtotime((string)$lastOrder['created_at']))
        : 'Aucune commande';
    $lastOrderMeta = 'Aucun achat enregistré pour ce compte.';
    if ($lastOrder) {
        $lastOrderMeta = '#'
            . (int)($lastOrder['id'] ?? 0)
            . ' · '
            . ($statusLabels[(string)($lastOrder['status'] ?? '')] ?? ucfirst((string)($lastOrder['status'] ?? '')))
            . ' · '
            . number_format((float)($lastOrder['total_price'] ?? 0), 2, ',', ' ')
            . ' €';
    }

    $lastPaymentTitle = $lastPayment && !empty($lastPayment['payment_date'])
        ? date('d/m/Y à H:i', strtotime((string)$lastPayment['payment_date']))
        : 'Aucun paiement';
    $lastPaymentMeta = 'Aucun encaissement enregistré pour ce compte.';
    if ($lastPayment) {
        $methodLabel = trim((string)($lastPayment['method'] ?? ''));
        $lastPaymentMeta = number_format((float)($lastPayment['amount_paid'] ?? 0), 2, ',', ' ')
            . ' €'
            . ($methodLabel !== '' ? ' · ' . strtoupper($methodLabel) : '');
    }

    $favoriteProductTitle = $favoriteProduct
        ? (string)($favoriteProduct['product_name'] ?? 'Produit non renseigné')
        : 'Aucun produit favori';
    $favoriteProductMeta = $favoriteProduct
        ? (int)($favoriteProduct['total_quantity'] ?? 0) . ' unité(s) commandée(s)'
        : 'Les habitudes apparaîtront après plusieurs commandes.';

    $canManageAccount = canManageUserAccount($role, $userId);
    $canLock = $canManageAccount;
    $canDelete = currentUserCan('users.delete') && $canManageAccount;
    $canCapture = currentUserCan('billing.manage');
    $canBill = $canCapture && $isActive;
    $permissionMatrix = $permissionMatrix ?? getUserPermissionMatrix($user);
    $permissionOverrideCount = count(array_filter(
        $permissionMatrix,
        static fn(array $permission): bool => $permission['override'] !== 'inherit'
    ));
    $allowedPermissionCount = count(array_filter(
        $permissionMatrix,
        static fn(array $permission): bool => (bool)$permission['effective_allowed']
    ));
    $deniedPermissionCount = count($permissionMatrix) - $allowedPermissionCount;
    $lockLabel = $isLocked ? 'Déverrouiller la boutique' : 'Verrouiller la boutique';
}
?>

<main class="main_part admin_dashboard_page user_profile_v2">
    <?php if (!empty($user)): ?>
        <section class="upv2_hero">
            <div class="upv2_hero_nav">
                <a href="index.php?controller=admin&action=showAllUsers">
                    <?= renderUiIcon('back') ?>
                    Annuaire
                </a>
                <span>Compte #<?= $userId ?></span>
            </div>

            <div class="upv2_hero_grid">
                <header class="upv2_identity">
                    <span class="upv2_avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                    <div class="upv2_identity_copy">
                        <span class="upv2_eyebrow">Fiche utilisateur</span>
                        <h1><?= htmlspecialchars($displayName) ?></h1>
                        <div class="upv2_identity_contact">
                            <span>@<?= htmlspecialchars($username !== '' ? $username : '-') ?></span>
                            <?php if ($email !== ''): ?>
                                <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="upv2_badges">
                            <span class="upv2_badge is_role"><?= htmlspecialchars(getRoleLabel($role, true)) ?></span>
                            <span class="upv2_badge <?= $isActive ? 'is_success' : 'is_warning' ?>">
                                <?= $isActive ? 'Compte actif' : 'Compte inactif' ?>
                            </span>
                            <?php if ($isLocked): ?>
                                <span class="upv2_badge is_danger">Boutique verrouillée</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <aside class="upv2_health" aria-label="État du compte">
                    <div class="upv2_health_head">
                        <span>État du compte</span>
                        <?= renderUiIcon('shield') ?>
                    </div>
                    <div class="upv2_health_grid">
                        <article class="<?= $isActive ? 'is_ok' : 'is_warning' ?>">
                            <span>Connexion</span>
                            <strong><?= $isActive ? 'Autorisée' : 'Suspendue' ?></strong>
                        </article>
                        <article class="<?= $isEmailVerified ? 'is_ok' : 'is_warning' ?>">
                            <span>Email</span>
                            <strong><?= $isEmailVerified ? 'Vérifié' : 'À vérifier' ?></strong>
                        </article>
                        <article class="<?= $isLocked ? 'is_danger' : 'is_ok' ?>">
                            <span>Boutique</span>
                            <strong><?= $isLocked ? 'Verrouillée' : 'Accessible' ?></strong>
                        </article>
                        <article class="<?= $permissionOverrideCount > 0 ? 'is_custom' : 'is_neutral' ?>">
                            <span>Permissions</span>
                            <strong><?= $permissionOverrideCount > 0 ? $permissionOverrideCount . ' exception' . ($permissionOverrideCount > 1 ? 's' : '') : 'Standard' ?></strong>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="upv2_actionbar">
                <div>
                    <strong>Actions du compte</strong>
                    <span>Les actions sensibles demandent toujours une confirmation.</span>
                </div>
                <div class="upv2_actionbar_links">
                    <?php if ($canManageAccount): ?>
                        <a class="upv2_button is_primary" href="index.php?controller=admin&action=editUser&id=<?= $userId ?>">
                            <?= renderUiIcon('edit') ?> Modifier le compte
                        </a>
                    <?php endif; ?>
                    <?php if ($canCapture): ?>
                        <a class="upv2_button" href="index.php?controller=admin&action=payments&user_id=<?= $userId ?>">
                            <?= renderUiIcon('payment') ?> Encaisser
                        </a>
                    <?php endif; ?>
                    <?php if ($canBill): ?>
                        <a class="upv2_button" href="index.php?controller=admin&action=billing&user_id=<?= $userId ?>">
                            <?= renderUiIcon('orders') ?> Facturer un produit
                        </a>
                    <?php endif; ?>
                    <?php if ($canManageAccount): ?>
                        <span class="upv2_action_separator" aria-hidden="true"></span>
                        <form method="POST" action="index.php?controller=admin&action=sendUserPasswordResetLink" data-confirm-message="Envoyer un lien de réinitialisation du mot de passe à <?= htmlspecialchars($email !== '' ? $email : $username, ENT_QUOTES, 'UTF-8') ?> ?">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= $userId ?>">
                            <button type="submit" class="upv2_button is_sensitive"><?= renderUiIcon('key') ?> Réinitialiser</button>
                        </form>
                        <?php if ($canLock): ?>
                            <form method="POST" action="index.php?controller=admin&action=toggleUserLock" data-confirm-message="<?= $isLocked ? 'Déverrouiller' : 'Verrouiller' ?> la boutique pour cet utilisateur ?">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= $userId ?>">
                                <button type="submit" class="upv2_button is_sensitive <?= $isLocked ? 'is_unlock' : '' ?>"><?= renderUiIcon($isLocked ? 'unlock' : 'lock') ?> <?= $isLocked ? 'Déverrouiller' : 'Verrouiller' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="POST" action="index.php?controller=admin&action=deleteUser" data-confirm-message="Confirmer la suppression définitive de <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?> ?">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= $userId ?>">
                                <button type="submit" class="upv2_button is_sensitive is_danger"><?= renderUiIcon('delete') ?> Supprimer</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$canManageAccount && !$canCapture): ?>
                        <span class="upv2_action_unavailable">Aucune action disponible sur ce compte.</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="upv2_metrics" aria-label="Indicateurs utilisateur">
            <article class="upv2_metric <?= $noteAmount < 0 ? 'is_credit' : ($noteAmount > 0 ? 'is_warning' : 'is_blue') ?>">
                <span class="upv2_metric_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                <div>
                    <span><?= $noteAmount < 0 ? 'Avoir disponible' : 'Solde utilisateur' ?></span>
                    <strong><?= number_format(abs($noteAmount), 2, ',', ' ') ?> €</strong>
                    <small><?= $noteAmount < 0 ? 'Utilisé automatiquement au prochain achat' : ($noteAmount > 0 ? 'Montant actuellement dû' : 'Aucun solde à régulariser') ?></small>
                </div>
            </article>
            <article class="upv2_metric is_green">
                <span class="upv2_metric_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                <div>
                    <span>Total encaissé</span>
                    <strong><?= number_format($totalSpent, 2, ',', ' ') ?> €</strong>
                    <small>Historique net des paiements</small>
                </div>
            </article>
            <article class="upv2_metric is_navy">
                <span class="upv2_metric_icon" aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                <div>
                    <span>Commandes</span>
                    <strong><?= $ordersTotal ?></strong>
                    <small><?= $ordersPaid ?> payée(s), <?= $ordersPending ?> en attente</small>
                </div>
            </article>
            <article class="upv2_metric <?= $pendingAmount > 0 ? 'is_warning' : 'is_blue' ?>">
                <span class="upv2_metric_icon" aria-hidden="true"><?= renderUiIcon('cart') ?></span>
                <div>
                    <span>À encaisser</span>
                    <strong><?= number_format($pendingAmount, 2, ',', ' ') ?> €</strong>
                    <small>Commandes encore ouvertes</small>
                </div>
            </article>
        </section>

        <section class="upv2_workspace">
            <article class="upv2_card upv2_account_card">
                <header class="upv2_card_head">
                    <span aria-hidden="true"><?= renderUiIcon('user') ?></span>
                    <div>
                        <span class="upv2_card_kicker">Profil</span>
                        <h2>Informations du compte</h2>
                    </div>
                </header>
                <dl class="upv2_definition_list">
                    <div><dt>Pseudo</dt><dd>@<?= htmlspecialchars($username !== '' ? $username : '-') ?></dd></div>
                    <div><dt>Email</dt><dd><?= htmlspecialchars($email !== '' ? $email : '-') ?></dd></div>
                    <div><dt>Service</dt><dd><?= htmlspecialchars($unit !== '' ? ucfirst($unit) : 'Non renseigné') ?></dd></div>
                    <div><dt>Rôle</dt><dd><?= htmlspecialchars(getRoleLabel($role)) ?></dd></div>
                    <div><dt>Membre depuis</dt><dd><?= htmlspecialchars($createdAtFormatted) ?></dd></div>
                    <div><dt>Email vérifié</dt><dd><?= htmlspecialchars($emailVerifiedFormatted) ?></dd></div>
                </dl>
            </article>

            <article class="upv2_card upv2_activity_card">
                <header class="upv2_card_head">
                    <span aria-hidden="true"><?= renderUiIcon('logs') ?></span>
                    <div>
                        <span class="upv2_card_kicker">Activité</span>
                        <h2>Derniers repères</h2>
                    </div>
                </header>
                <div class="upv2_activity_list">
                    <article>
                        <span class="upv2_activity_icon" aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                        <div><small>Dernière commande</small><strong><?= htmlspecialchars($lastOrderTitle) ?></strong><p><?= htmlspecialchars($lastOrderMeta) ?></p></div>
                    </article>
                    <article>
                        <span class="upv2_activity_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                        <div><small>Dernier paiement</small><strong><?= htmlspecialchars($lastPaymentTitle) ?></strong><p><?= htmlspecialchars($lastPaymentMeta) ?></p></div>
                    </article>
                    <article>
                        <span class="upv2_activity_icon" aria-hidden="true"><?= renderUiIcon('shop') ?></span>
                        <div><small>Produit favori</small><strong><?= htmlspecialchars($favoriteProductTitle) ?></strong><p><?= htmlspecialchars($favoriteProductMeta) ?></p></div>
                    </article>
                </div>
            </article>
        </section>

        <section class="upv2_card upv2_permissions">
            <header class="upv2_permissions_head">
                <div class="upv2_card_head">
                    <span aria-hidden="true"><?= renderUiIcon('shield') ?></span>
                    <div>
                        <span class="upv2_card_kicker">Accès</span>
                        <h2>Rôle et permissions</h2>
                        <p><?= htmlspecialchars(getRoleDescription($role)) ?></p>
                    </div>
                </div>
                <?php if ($canManageAccount): ?>
                    <a class="upv2_button" href="index.php?controller=admin&action=editUser&id=<?= $userId ?>#permissions">
                        <?= renderUiIcon('key') ?> Modifier les droits
                    </a>
                <?php endif; ?>
            </header>

            <div class="upv2_permission_stats">
                <article><span>Autorisées</span><strong><?= $allowedPermissionCount ?></strong></article>
                <article><span>Non accordées</span><strong><?= $deniedPermissionCount ?></strong></article>
                <article class="<?= $permissionOverrideCount > 0 ? 'has_override' : '' ?>"><span>Exceptions</span><strong><?= $permissionOverrideCount ?></strong></article>
                <article><span>Rôle de base</span><strong><?= htmlspecialchars(getRoleLabel($role, true)) ?></strong></article>
            </div>

            <details class="upv2_permission_details" <?= $permissionOverrideCount > 0 ? 'open' : '' ?>>
                <summary>
                    <span>Afficher le détail des <?= count($permissionMatrix) ?> autorisations</span>
                    <small>Permissions héritées et personnalisées</small>
                </summary>
                <div class="upv2_permission_grid">
                    <?php foreach ($permissionMatrix as $permissionData): ?>
                        <?php
                        if ($permissionData['override'] === 'allow') {
                            $permissionSource = 'Autorisée pour ce compte';
                        } elseif ($permissionData['override'] === 'deny') {
                            $permissionSource = 'Refusée pour ce compte';
                        } elseif ($permissionData['base_allowed']) {
                            $permissionSource = 'Incluse dans le rôle';
                        } else {
                            $permissionSource = 'Non incluse dans le rôle';
                        }
                        ?>
                        <article class="upv2_permission_item <?= $permissionData['effective_allowed'] ? 'is_allowed' : 'is_denied' ?> <?= $permissionData['override'] !== 'inherit' ? 'is_override' : '' ?>">
                            <span aria-hidden="true"><?= renderUiIcon($permissionData['effective_allowed'] ? 'shield' : 'lock') ?></span>
                            <div>
                                <strong><?= htmlspecialchars((string)$permissionData['label']) ?></strong>
                                <small><?= htmlspecialchars($permissionSource) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        </section>

    <?php else: ?>
        <section class="upv2_empty">
            <span aria-hidden="true"><?= renderUiIcon('users') ?></span>
            <h1>Utilisateur introuvable</h1>
            <p>La fiche demandée n’existe pas ou n’est plus disponible.</p>
            <a class="upv2_button is_primary" href="index.php?controller=admin&action=showAllUsers">Retour à l’annuaire</a>
        </section>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
