<?php
require_once __DIR__ . '/../../partials/header.php';

$users = $users ?? [];
$directoryStats = $directoryStats ?? ['total' => 0, 'active' => 0, 'pending' => 0, 'locked' => 0, 'staff' => 0];
$q = trim((string)($q ?? ''));
$roleFilter = trim((string)($roleFilter ?? ''));
$statusFilter = trim((string)($statusFilter ?? ''));
$unitFilter = trim((string)($unitFilter ?? ''));
$activeFilterCount = ($q !== '' ? 1 : 0)
    + ($roleFilter !== '' ? 1 : 0)
    + ($statusFilter !== '' ? 1 : 0)
    + ($unitFilter !== '' ? 1 : 0);
?>

<main class="main_part admin_dashboard_page user_management_page">
    <section class="user_management_header">
        <div class="user_management_title">
            <span class="user_management_title_icon" aria-hidden="true"><?= renderUiIcon('users') ?></span>
            <div>
                <span class="section_kicker">Gestion des accès</span>
                <h1>Utilisateurs</h1>
                <p>Retrouvez un compte, contrôlez son état et attribuez les droits adaptés.</p>
            </div>
        </div>

        <div class="user_management_header_actions">
            <?php if (currentUserCan('users.approve')): ?>
                <a class="user_management_button is_secondary" href="index.php?controller=admin&action=pendingUsers">
                    <?= renderUiIcon('support') ?>
                    Inscriptions
                    <?php if ((int)$directoryStats['pending'] > 0): ?>
                        <span><?= (int)$directoryStats['pending'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (currentUserCan('users.manage')): ?>
                <a class="user_management_button is_primary" href="index.php?controller=admin&action=addUser">
                    <?= renderUiIcon('add') ?>
                    Nouvel utilisateur
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="user_directory_stats" aria-label="Résumé des utilisateurs">
        <?php
        $summaryCards = [
            ['label' => 'Tous les comptes', 'value' => (int)$directoryStats['total'], 'tone' => 'blue'],
            ['label' => 'Actifs', 'value' => (int)$directoryStats['active'], 'tone' => 'mint'],
            ['label' => 'À valider', 'value' => (int)$directoryStats['pending'], 'tone' => 'gold'],
            ['label' => 'Verrouillés', 'value' => (int)$directoryStats['locked'], 'tone' => 'coral'],
            ['label' => 'Équipe', 'value' => (int)$directoryStats['staff'], 'tone' => 'navy'],
        ];
        ?>
        <?php foreach ($summaryCards as $card): ?>
            <article class="user_directory_stat is_<?= htmlspecialchars($card['tone']) ?>">
                <span><?= htmlspecialchars($card['label']) ?></span>
                <strong><?= (int)$card['value'] ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="user_directory_panel">
        <form class="user_directory_filters" method="get" action="index.php">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="showAllUsers">

            <label class="user_directory_search">
                <span>Rechercher</span>
                <span class="user_directory_search_control">
                    <?= renderUiIcon('search') ?>
                    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" aria-label="Nom, pseudo ou adresse e-mail">
                </span>
            </label>

            <label>
                <span>Rôle</span>
                <select name="role">
                    <option value="">Tous les rôles</option>
                    <?php foreach (getRoleDefinitions() as $roleValue => $definition): ?>
                        <option value="<?= htmlspecialchars($roleValue) ?>" <?= $roleFilter === $roleValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$definition['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>État</span>
                <select name="status">
                    <option value="">Tous les états</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actifs</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>À valider / inactifs</option>
                    <option value="locked" <?= $statusFilter === 'locked' ? 'selected' : '' ?>>Verrouillés</option>
                </select>
            </label>

            <label>
                <span>Service</span>
                <select name="unit">
                    <option value="">Tous les services</option>
                    <?php foreach (['mineurs', 'vif', 'syndicat'] as $unit): ?>
                        <option value="<?= $unit ?>" <?= $unitFilter === $unit ? 'selected' : '' ?>><?= ucfirst($unit) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="user_directory_filter_actions">
                <?php if ($activeFilterCount > 0): ?>
                    <a href="index.php?controller=admin&action=showAllUsers">Réinitialiser</a>
                <?php endif; ?>
                <button type="submit">Filtrer</button>
            </div>
        </form>

        <div class="user_directory_results_head">
            <div>
                <h2>Annuaire</h2>
                <p><?= (int)($totalUsers ?? count($users)) ?> résultat<?= (int)($totalUsers ?? count($users)) > 1 ? 's' : '' ?></p>
            </div>
            <?php if ($activeFilterCount > 0): ?>
                <span><?= $activeFilterCount ?> filtre<?= $activeFilterCount > 1 ? 's' : '' ?> actif<?= $activeFilterCount > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($users)): ?>
            <div class="user_directory_empty">
                <span aria-hidden="true"><?= renderUiIcon('users') ?></span>
                <h3>Aucun compte ne correspond</h3>
                <p>Modifiez les filtres ou réinitialisez la recherche.</p>
            </div>
        <?php else: ?>
            <div class="user_directory_list">
                <?php foreach ($users as $user): ?>
                    <?php
                    $userId = (int)($user['id'] ?? 0);
                    $firstname = trim((string)($user['firstname'] ?? ''));
                    $lastname = trim((string)($user['lastname'] ?? ''));
                    $username = trim((string)($user['username'] ?? 'utilisateur'));
                    $fullName = trim($firstname . ' ' . $lastname);
                    $displayName = $fullName !== '' ? $fullName : $username;
                    $initials = mb_strtoupper(mb_substr($firstname !== '' ? $firstname : $username, 0, 1) . mb_substr($lastname, 0, 1));
                    $role = normalizeUserRole($user['role'] ?? 'user');
                    $isActive = (int)($user['is_active'] ?? 0) === 1;
                    $isLocked = (int)($user['is_locked'] ?? 0) === 1;
                    $overrideCount = count(getUserPermissionOverrides($userId));
                    $canEditUser = canManageUserAccount($role, $userId);
                    $note = (float)($user['note'] ?? 0);
                    ?>
                    <article class="user_directory_row">
                        <a class="user_directory_identity" href="index.php?controller=admin&action=showUser&id=<?= $userId ?>">
                            <span class="user_directory_avatar" aria-hidden="true"><?= htmlspecialchars($initials !== '' ? $initials : 'U') ?></span>
                            <span>
                                <strong><?= htmlspecialchars($displayName) ?></strong>
                                <small>@<?= htmlspecialchars($username) ?> · <?= htmlspecialchars((string)($user['email'] ?? '')) ?></small>
                            </span>
                        </a>

                        <div class="user_directory_access">
                            <span class="user_role_chip role_<?= htmlspecialchars($role) ?>"><?= htmlspecialchars(getRoleLabel($role, true)) ?></span>
                            <small><?= htmlspecialchars(ucfirst((string)($user['unit'] ?? 'Non renseigné'))) ?></small>
                            <?php if ($overrideCount > 0): ?>
                                <span class="user_permission_override_chip"><?= $overrideCount ?> exception<?= $overrideCount > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="user_directory_balance">
                            <span><?= $note < 0 ? 'Avoir' : 'Solde' ?></span>
                            <strong class="<?= $note < 0 ? 'has_credit' : ($note > 0 ? 'has_balance' : '') ?>"><?= number_format(abs($note), 2, ',', ' ') ?> €</strong>
                        </div>

                        <div class="user_directory_status">
                            <span class="user_status_chip <?= $isActive ? 'is_active' : 'is_inactive' ?>">
                                <?= $isActive ? 'Actif' : 'À valider' ?>
                            </span>
                            <?php if ($isLocked): ?>
                                <span class="user_status_chip is_locked"><?= renderUiIcon('lock') ?> Verrouillé</span>
                            <?php endif; ?>
                        </div>

                        <div class="user_directory_actions">
                            <a href="index.php?controller=admin&action=showUser&id=<?= $userId ?>" aria-label="Ouvrir la fiche de <?= htmlspecialchars($displayName) ?>">
                                <?= renderUiIcon('eye') ?>
                            </a>
                            <?php if ($canEditUser): ?>
                                <a href="index.php?controller=admin&action=editUser&id=<?= $userId ?>" aria-label="Modifier <?= htmlspecialchars($displayName) ?>">
                                    <?= renderUiIcon('edit') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <?php
                $paginationCurrentPage = (int)($page ?? 1);
                $paginationTotalPages = (int)($totalPages ?? 1);
                $paginationLabel = 'Pagination des utilisateurs';
                $paginationPageTemplate = 'index.php?' . http_build_query([
                    'controller' => 'admin',
                    'action' => 'showAllUsers',
                    'q' => $q,
                    'role' => $roleFilter,
                    'status' => $statusFilter,
                    'unit' => $unitFilter,
                    'page' => '__PAGE__',
                ]);
                require __DIR__ . '/../../partials/admin_pagination.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
