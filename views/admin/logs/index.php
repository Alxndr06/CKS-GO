<?php
require_once __DIR__ . '/../../partials/header.php';

function getLogBadgeClass(string $action): string
{
    if (str_contains($action, '_failed')) {
        return 'log_badge log_badge_error';
    }

    if (
            str_contains($action, 'create') ||
            str_contains($action, 'capture') ||
            str_contains($action, 'update')
    ) {
        return 'log_badge log_badge_success';
    }

    return 'log_badge log_badge_neutral';
}

function getLogActionLabel(string $action): string
{
    $labels = [
        'admin_user_create' => 'Utilisateur créé',
        'admin_user_update' => 'Utilisateur modifié',
        'admin_user_lock' => 'Accès boutique verrouillé',
        'admin_user_unlock' => 'Accès boutique déverrouillé',
        'admin_settings_updated' => 'Réglages mis à jour',
        'admin_product_create' => 'Produit créé',
        'admin_product_update' => 'Produit modifié',
        'admin_product_disabled' => 'Produit désactivé',
        'admin_product_enabled' => 'Produit réactivé',
        'security_access_ban_created' => 'Bannissement activé',
        'security_access_ban_removed' => 'Bannissement retiré',
    ];

    if (isset($labels[$action])) {
        return $labels[$action];
    }

    $label = preg_replace('/^(admin|system)_/', '', $action) ?? $action;
    $label = str_replace('_', ' ', $label);

    return ucfirst($label);
}

function getLogCategory(string $action): array
{
    $categories = [
        'security' => ['security', 'login', 'lock', 'ban', 'access'],
        'billing' => ['payment', 'order', 'invoice', 'refund', 'billing', 'balance', 'charge'],
        'catalog' => ['product', 'variant', 'category', 'inventory', 'stock', 'shop'],
        'support' => ['ticket', 'alert', 'support'],
        'users' => ['user', 'registration', 'permission', 'password'],
        'settings' => ['setting', 'maintenance', 'news'],
    ];

    foreach ($categories as $category => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($action, $needle)) {
                return [$category, match ($category) {
                    'security' => 'Sécurité',
                    'billing' => 'Finance',
                    'catalog' => 'Catalogue',
                    'support' => 'Support',
                    'users' => 'Utilisateurs',
                    default => 'Système',
                }];
            }
        }
    }

    return ['settings', 'Système'];
}

function buildAdminEntityLink(string $type, int $id, string $label): string
{
    if ($id <= 0) {
        return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }

    $href = '#';

    switch ($type) {
        case 'order':
            $href = 'index.php?controller=admin&action=showOrder&id=' . $id;
            break;
        case 'user':
            $href = 'index.php?controller=admin&action=showUser&id=' . $id;
            break;
        case 'payment':
            $href = 'index.php?controller=admin&action=showPayment&id=' . $id;
            break;
        case 'alert':
            $href = 'index.php?controller=admin&action=showAlert&id=' . $id;
            break;
    }

    return '<a class="admin_logs_link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
}

function formatAdminEntityList(string $safe, string $pattern, string $type): string
{
    return (string) preg_replace_callback(
            $pattern,
            static function (array $matches) use ($type): string {
                $ids = preg_split('/\s*,\s*/', trim((string) $matches[2]));
                $links = [];

                foreach ($ids as $id) {
                    $intId = (int) $id;
                    if ($intId > 0) {
                        $links[] = buildAdminEntityLink($type, $intId, '#' . $intId);
                    }
                }

                if (empty($links)) {
                    return htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8');
                }

                return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '=' . implode(', ', $links);
            },
            $safe
    );
}

function formatAdminLogDetails(string $details): string
{
    $details = trim($details);

    if ($details === '') {
        return '<span class="muted_text">—</span>';
    }

    $safe = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');

    $safe = formatAdminEntityList($safe, '/\b(commandes)\s*=\s*([0-9,\s]+)/iu', 'order');
    $safe = formatAdminEntityList($safe, '/\b(paiements)\s*=\s*([0-9,\s]+)/iu', 'payment');
    $safe = formatAdminEntityList($safe, '/\b(alertes)\s*=\s*([0-9,\s]+)/iu', 'alert');

    $patterns = [
            '/\b(commande|order)\s+#(\d+)\b/iu' => 'order',
            '/\b(utilisateur|user)\s+#(\d+)\b/iu' => 'user',
            '/\b(paiement|payment)\s+#(\d+)\b/iu' => 'payment',
            '/\b(alerte|alert)\s+#(\d+)\b/iu' => 'alert',
    ];

    foreach ($patterns as $pattern => $type) {
        $safe = (string) preg_replace_callback(
                $pattern,
                static function (array $matches) use ($type): string {
                    $label = $matches[1] . ' #' . (int) $matches[2];
                    return buildAdminEntityLink($type, (int) $matches[2], $label);
                },
                $safe
        );
    }

    return nl2br($safe);
}

$q = trim((string) ($q ?? ''));
$category = trim((string)($category ?? ''));
$outcome = trim((string)($outcome ?? ''));
$actorId = (int)($actorId ?? 0);
$dateFrom = trim((string)($dateFrom ?? ''));
$dateTo = trim((string)($dateTo ?? ''));
$activeFilterCount = ($q !== '' ? 1 : 0) + ($category !== '' ? 1 : 0) + ($outcome !== '' ? 1 : 0)
    + ($actorId > 0 ? 1 : 0) + ($dateFrom !== '' ? 1 : 0) + ($dateTo !== '' ? 1 : 0);

$toolbarConfig = [
        'title' => 'Recherche / filtres',
        'count_label' => (int) ($totalLogs ?? 0) . ' événement(s)',
        'search_open' => true,
        'action' => 'index.php',
        'fields' => [
                [
                        'type' => 'hidden',
                        'name' => 'controller',
                        'value' => 'admin',
                ],
                [
                        'type' => 'hidden',
                        'name' => 'action',
                        'value' => 'logs',
                ],
                [
                        'type' => 'search',
                        'name' => 'q',
                        'value' => $q,
                        'placeholder' => 'Action, personne ou détail…',
                ],
                [
                        'type' => 'select',
                        'name' => 'category',
                        'value' => $category,
                        'options' => [
                            ['value' => '', 'label' => 'Tous les domaines'],
                            ['value' => 'users', 'label' => 'Utilisateurs'],
                            ['value' => 'catalog', 'label' => 'Catalogue'],
                            ['value' => 'billing', 'label' => 'Finance'],
                            ['value' => 'support', 'label' => 'Support'],
                            ['value' => 'security', 'label' => 'Sécurité'],
                            ['value' => 'settings', 'label' => 'Système'],
                        ],
                ],
                [
                        'type' => 'select',
                        'name' => 'outcome',
                        'value' => $outcome,
                        'options' => [
                            ['value' => '', 'label' => 'Tous les résultats'],
                            ['value' => 'success', 'label' => 'Réussites'],
                            ['value' => 'failure', 'label' => 'Échecs'],
                        ],
                ],
                [
                        'type' => 'select',
                        'name' => 'actor_id',
                        'value' => (string)$actorId,
                        'options' => array_merge(
                            [['value' => '', 'label' => 'Tous les membres']],
                            array_map(static function (array $actor): array {
                                $name = trim((string)($actor['firstname'] ?? '') . ' ' . (string)($actor['lastname'] ?? ''));
                                return ['value' => (string)$actor['id'], 'label' => $name !== '' ? $name : (string)$actor['username']];
                            }, $logActors ?? [])
                        ),
                ],
                ['type' => 'date', 'name' => 'date_from', 'value' => $dateFrom],
                ['type' => 'date', 'name' => 'date_to', 'value' => $dateTo],
        ],
        'back_href' => 'index.php?controller=admin&action=dashboard',
];

$logsPaginationTemplate = 'index.php?' . http_build_query([
                'controller' => 'admin',
                'action' => 'logs',
                'page' => '__PAGE__',
                'q' => $q,
                'category' => $category,
                'outcome' => $outcome,
                'actor_id' => $actorId ?: '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
        ]);
?>

<main class="main_part admin_dashboard_page admin_logs_page admin_page_pro">
    <section class="admin_dashboard_intro admin_dashboard_intro_compact">
        <div class="admin_dashboard_intro_top">
            <span class="section_kicker">Audit</span>
            <h2>Journal d’activité</h2>
            <p>Une trace lisible et exploitable des opérations réalisées par l’équipe.</p>
        </div>
    </section>

    <section class="admin_logs_kpis" aria-label="Résumé du journal">
        <article><span>Total filtré</span><strong><?= (int)($logStats['total'] ?? 0) ?></strong><small>événement(s)</small></article>
        <article class="is_recent"><span>Dernières 24 h</span><strong><?= (int)($logStats['last_24h'] ?? 0) ?></strong><small>activité récente</small></article>
        <article class="is_failure"><span>Échecs</span><strong><?= (int)($logStats['failures'] ?? 0) ?></strong><small>à contrôler</small></article>
        <article class="is_actor"><span>Intervenants</span><strong><?= (int)($logStats['actors'] ?? 0) ?></strong><small>membre(s)</small></article>
    </section>

    <section class="admin_dashboard_section admin_logs_filters">
        <?php require __DIR__ . '/../../partials/admin_list_toolbar.php'; ?>
        <?php if ($activeFilterCount > 0): ?>
            <a class="admin_logs_reset" href="index.php?controller=admin&action=logs">Réinitialiser les <?= $activeFilterCount ?> filtre<?= $activeFilterCount > 1 ? 's' : '' ?></a>
        <?php endif; ?>
    </section>

    <section class="admin_dashboard_section">
        <div class="admin_logs_summary">
            <p>
                <strong><?= (int) ($totalLogs ?? 0) ?></strong> événement(s)
                <?php if ($q !== ''): ?>
                    pour la recherche <strong><?= htmlspecialchars($q) ?></strong>
                <?php endif; ?>
            </p>
            <p>Page <strong><?= (int) ($page ?? 1) ?></strong> / <strong><?= (int) ($totalPages ?? 1) ?></strong></p>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty_state">
                <h3>Aucun événement</h3>
                <p>Aucune activité ne correspond aux filtres sélectionnés.</p>
            </div>
        <?php else: ?>
            <div class="table_wrapper">
                <table class="admin_logs_table">
                    <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Date et heure</th>
                        <th>Intervenant</th>
                        <th>Contexte</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $adminLabel = trim(((string) ($log['firstname'] ?? '')) . ' ' . ((string) ($log['lastname'] ?? '')));
                        if ($adminLabel === '') {
                            $adminLabel = (string) ($log['username'] ?? ('Admin #' . (int) ($log['admin_id'] ?? 0)));
                        }
                        [$logCategory, $logCategoryLabel] = getLogCategory((string)($log['action'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <div class="admin_log_event">
                                    <span class="admin_log_category is_<?= htmlspecialchars($logCategory) ?>"><?= htmlspecialchars($logCategoryLabel) ?></span>
                                    <div>
                                        <strong><?= htmlspecialchars(getLogActionLabel((string)($log['action'] ?? ''))) ?></strong>
                                        <small>#<?= (int)($log['id'] ?? 0) ?> · <?= htmlspecialchars((string)($log['action'] ?? '')) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="admin_log_date">
                                <?php if (!empty($log['created_at'])): ?>
                                    <strong><?= htmlspecialchars(date('d/m/Y', strtotime((string)$log['created_at']))) ?></strong>
                                    <small><?= htmlspecialchars(date('H:i:s', strtotime((string)$log['created_at']))) ?></small>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="admin_log_actor"><?= buildAdminEntityLink('user', (int) ($log['admin_id'] ?? 0), $adminLabel) ?></td>
                            <td class="admin_logs_details">
                                <div><?= formatAdminLogDetails((string) ($log['details'] ?? '')) ?></div>
                                <?php if (!empty($log['ip_address']) || !empty($log['request_id'])): ?>
                                    <small class="admin_log_trace" title="<?= htmlspecialchars((string)($log['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($log['ip_address'])): ?>IP <?= htmlspecialchars((string)$log['ip_address'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        <?php if (!empty($log['request_id'])): ?> · Requête <?= htmlspecialchars(substr((string)$log['request_id'], 0, 12), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $paginationCurrentPage = (int) ($page ?? 1);
            $paginationTotalPages = (int) ($totalPages ?? 1);
            $paginationLabel = 'Pagination des logs';
            $paginationPageTemplate = $logsPaginationTemplate;
            require __DIR__ . '/../../partials/admin_pagination.php';
            ?>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
