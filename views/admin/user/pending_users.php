<?php
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/../../../helpers/functions.php';

$users = $users ?? [];
$q = trim((string) ($q ?? ''));
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$totalUsers = (int) ($totalUsers ?? count($users));
$isSearchOpen = $q !== '';

$toolbarConfig = [
        'title' => 'Recherche / filtres',
        'count_label' => $totalUsers . ' demande(s)',
        'search_open' => $isSearchOpen,
        'action' => BASE_URL . '/index.php',
        'fields' => [
                [
                        'type' => 'hidden',
                        'name' => 'controller',
                        'value' => 'admin',
                ],
                [
                        'type' => 'hidden',
                        'name' => 'action',
                        'value' => 'pendingUsers',
                ],
                [
                        'type' => 'text',
                        'name' => 'q',
                        'value' => $q,
                        'placeholder' => 'Nom, prénom, pseudo, email, service...',
                ],
        ],
        'back_href' => BASE_URL . '/index.php?controller=admin&action=showAllUsers',
        'back_label' => 'Tous les comptes',
];
?>

<main class="main_part admin_dashboard_page admin_page_pro pending_users_page_pro">
    <section class="admin_dashboard_intro admin_dashboard_intro_compact">
        <span class="section_kicker">Utilisateurs</span>
        <h2>Utilisateurs en attente</h2>
        <p>
            Traite rapidement les demandes d’inscription avec une vue compacte dédiée à la validation des comptes.
        </p>
    </section>

    <section class="admin_dashboard_section">
        <?php require __DIR__ . '/../../partials/admin_list_toolbar.php'; ?>

        <?php if (empty($users)): ?>
            <div class="empty_state_card">
                <h3>Aucune inscription en attente</h3>
                <p>Tout est à jour pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="aup_table" role="table" aria-label="Liste des utilisateurs en attente">
                <div class="aup_table_head" role="rowgroup">
                    <div role="row">
                        <div role="columnheader">Utilisateur</div>
                        <div role="columnheader">Service</div>
                        <div role="columnheader">Demande</div>
                        <div role="columnheader">Actions</div>
                    </div>
                </div>

                <div class="aup_table_body" role="rowgroup">
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int) ($user['id'] ?? 0);
                        $firstname = trim((string) ($user['firstname'] ?? ''));
                        $lastname = trim((string) ($user['lastname'] ?? ''));
                        $username = trim((string) ($user['username'] ?? 'utilisateur'));
                        $fullName = trim($firstname . ' ' . $lastname);
                        $displayName = $fullName !== '' ? $fullName : $username;
                        $role = normalizeUserRole($user['role'] ?? 'user');
                        $roleLabel = getRoleLabel($role, true);
                        $unit = trim((string) ($user['unit'] ?? '—'));
                        $email = trim((string) ($user['email'] ?? '—'));
                        $createdAtRaw = (string) ($user['created_at'] ?? '');
                        $createdAt = $createdAtRaw !== '' ? date('d/m/Y', strtotime($createdAtRaw)) : '—';

                        $initials = strtoupper(substr($firstname !== '' ? $firstname : $username, 0, 1) . substr($lastname, 0, 1));
                        if (trim($initials) === '') {
                            $initials = strtoupper(substr($username, 0, 2));
                        }
                        ?>

                        <article class="aup_row" role="row">
                            <div class="aup_cell aup_cell_user" role="cell">
                                <div class="aup_user_main">
                                    <div class="aup_user_avatar" aria-hidden="true">
                                        <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                    <div class="aup_user_identity">
                                        <h4>
                                            <a
                                                    class="aup_user_link"
                                                    href="<?= htmlspecialchars(BASE_URL . '/index.php?controller=admin&action=showUser&id=' . $userId, ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </h4>

                                        <div class="aup_user_subline">
                                            <span class="aup_user_username">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="aup_role_badge"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>

                                        <p class="aup_user_email"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>

                                        <div class="aup_mobile_meta">
                                            <span><strong>Service :</strong> <?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span><strong>Demande :</strong> <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="aup_cell aup_cell_service" role="cell" data-label="Service">
                                <span class="aup_service_chip"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>

                            <div class="aup_cell aup_cell_request" role="cell" data-label="Demande">
                                <span class="aup_pending_badge">En attente</span>
                                <small><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>

                            <div class="aup_cell aup_cell_actions" role="cell" data-label="Actions">
                                <a
                                        href="<?= htmlspecialchars(BASE_URL . '/index.php?controller=admin&action=showUser&id=' . $userId, ENT_QUOTES, 'UTF-8') ?>"
                                        class="apl_btn apl_btn_small apl_btn_light aup_action_btn"
                                >
                                    Voir
                                </a>

                                <form method="POST" action="<?= htmlspecialchars(BASE_URL . '/index.php?controller=admin&action=approveUser', ENT_QUOTES, 'UTF-8') ?>" class="aul_inline_form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= $userId ?>">
                                    <button type="submit" class="apl_btn apl_btn_small apl_btn_primary aup_action_btn">
                                        Valider
                                    </button>
                                </form>

                                <form
                                        method="POST"
                                        action="<?= htmlspecialchars(BASE_URL . '/index.php?controller=admin&action=rejectUser', ENT_QUOTES, 'UTF-8') ?>"
                                        class="aul_inline_form"
                                        data-confirm-message="Refuser cette inscription et supprimer le compte ?"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= $userId ?>">
                                    <button type="submit" class="aup_icon_btn" title="Refuser" aria-label="Refuser cette inscription">
                                        <?= renderUiIcon('delete') ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $paginationCurrentPage = $page;
                $paginationTotalPages = $totalPages;
                $paginationLabel = 'Pagination des utilisateurs en attente';
                $paginationPageTemplate = BASE_URL . '/index.php?' . http_build_query([
                                'controller' => 'admin',
                                'action' => 'pendingUsers',
                                'q' => $q,
                                'page' => '__PAGE__',
                        ]);
                require __DIR__ . '/../../partials/admin_pagination.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
