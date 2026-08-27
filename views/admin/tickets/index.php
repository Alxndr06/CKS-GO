<?php
require_once __DIR__ . '/../../partials/header.php';

$tickets = is_array($tickets ?? null) ? $tickets : [];
$stats = is_array($ticket_stats ?? null) ? $ticket_stats : [];
$allowedStatuses = is_array($allowedStatuses ?? null) ? $allowedStatuses : [];
$allowedPriorities = is_array($allowedPriorities ?? null) ? $allowedPriorities : [];
$allowedCategories = is_array($allowedCategories ?? null) ? $allowedCategories : [];
$status = (string)($status ?? '');
$priority = (string)($priority ?? '');
$category = (string)($category ?? '');
$assignment = (string)($assignment ?? '');
$waiting = (string)($waiting ?? '');
$q = (string)($q ?? '');
$page = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$totalTickets = (int)($totalTickets ?? 0);
$currentAdminId = (int)($_SESSION['user']['id'] ?? 0);

$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'closed' => 'Fermé'];
$priorityLabels = ['low' => 'Faible', 'medium' => 'Normale', 'high' => 'Haute'];
$categoryLabels = ['account' => 'Compte', 'order' => 'Commande', 'payment' => 'Paiement', 'shop' => 'Boutique', 'technical' => 'Technique', 'other' => 'Autre'];
$paginationTemplate = 'index.php?' . http_build_query([
    'controller' => 'admin', 'action' => 'tickets', 'page' => '__PAGE__', 'q' => $q,
    'status' => $status, 'priority' => $priority, 'category' => $category,
    'assignment' => $assignment, 'waiting' => $waiting,
]);
?>

<main class="main_part comms_page support_queue_page">
    <header class="comms_page_header">
        <div>
            <span class="section_kicker">Support utilisateurs</span>
            <h1>Boîte de réception</h1>
            <p>Priorise les demandes, attribue-les et réponds depuis une file unique.</p>
        </div>
        <div class="support_response_metric">
            <span>Première réponse moyenne</span>
            <strong><?= formatTicketDelay((int)($stats['average_first_response_minutes'] ?? 0)) ?></strong>
        </div>
    </header>

    <nav class="comms_stat_nav support_stat_nav" aria-label="Files de tickets">
        <a class="<?= $waiting === 'staff' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=tickets&waiting=staff">
            <span>Réponse attendue</span><strong><?= (int)($stats['awaiting_staff_tickets'] ?? 0) ?></strong>
        </a>
        <a class="<?= $assignment === 'unassigned' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=tickets&assignment=unassigned">
            <span>Non attribués</span><strong><?= (int)($stats['unassigned_tickets'] ?? 0) ?></strong>
        </a>
        <a class="<?= $assignment === 'mine' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=tickets&assignment=mine">
            <span>Mes tickets</span><strong>→</strong>
        </a>
        <a class="<?= $priority === 'high' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=tickets&priority=high">
            <span>Priorité haute</span><strong><?= (int)($stats['high_priority_active_tickets'] ?? 0) ?></strong>
        </a>
        <a class="<?= $status === 'closed' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=tickets&status=closed">
            <span>Fermés</span><strong><?= (int)($stats['closed_tickets'] ?? 0) ?></strong>
        </a>
    </nav>

    <section class="comms_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form is_dense" data-auto-filter-form>
            <input type="hidden" name="controller" value="admin"><input type="hidden" name="action" value="tickets">
            <label class="comms_search_field"><span>Rechercher</span><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" data-auto-filter></label>
            <label><span>Statut</span><select name="status" data-auto-filter><option value="">Tous</option><?php foreach ($allowedStatuses as $item): ?><option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= $statusLabels[$item] ?? $item ?></option><?php endforeach; ?></select></label>
            <label><span>Priorité</span><select name="priority" data-auto-filter><option value="">Toutes</option><?php foreach ($allowedPriorities as $item): ?><option value="<?= $item ?>" <?= $priority === $item ? 'selected' : '' ?>><?= $priorityLabels[$item] ?? $item ?></option><?php endforeach; ?></select></label>
            <label><span>Catégorie</span><select name="category" data-auto-filter><option value="">Toutes</option><?php foreach ($allowedCategories as $item): ?><option value="<?= $item ?>" <?= $category === $item ? 'selected' : '' ?>><?= $categoryLabels[$item] ?? $item ?></option><?php endforeach; ?></select></label>
            <label><span>Attribution</span><select name="assignment" data-auto-filter><option value="">Toutes</option><option value="mine" <?= $assignment === 'mine' ? 'selected' : '' ?>>Mes tickets</option><option value="unassigned" <?= $assignment === 'unassigned' ? 'selected' : '' ?>>Non attribués</option></select></label>
            <label><span>En attente de</span><select name="waiting" data-auto-filter><option value="">Tous</option><option value="staff" <?= $waiting === 'staff' ? 'selected' : '' ?>>Support</option><option value="user" <?= $waiting === 'user' ? 'selected' : '' ?>>Utilisateur</option></select></label>
            <button type="submit">Filtrer</button>
            <?php if ($q || $status || $priority || $category || $assignment || $waiting): ?><a href="index.php?controller=admin&action=tickets">Effacer</a><?php endif; ?>
        </form>
        <span class="comms_result_count"><?= $totalTickets ?> ticket<?= $totalTickets > 1 ? 's' : '' ?></span>
    </section>

    <?php if (empty($tickets)): ?>
        <section class="comms_empty_state"><?= renderUiIcon('support') ?><h2>File vide</h2><p>Aucun ticket ne correspond à cette vue.</p></section>
    <?php else: ?>
        <section class="support_queue" aria-label="Tickets">
            <?php foreach ($tickets as $ticket): ?>
                <?php
                $ticketId = (int)$ticket['id'];
                $displayName = trim((string)($ticket['firstname'] ?? '') . ' ' . (string)($ticket['lastname'] ?? '')) ?: ((string)($ticket['username'] ?? 'Utilisateur'));
                $assignedName = trim((string)($ticket['assigned_admin_firstname'] ?? '') . ' ' . (string)($ticket['assigned_admin_lastname'] ?? ''));
                $waitingStaff = empty($ticket['last_message_admin_id']) && ($ticket['status'] ?? '') !== 'closed';
                $age = !empty($ticket['last_message_at']) ? humanElapsedTime((string)$ticket['last_message_at']) : 'sans activité';
                ?>
                <article class="support_ticket_row <?= $waitingStaff ? 'awaiting_staff' : '' ?> <?= ($ticket['priority'] ?? '') === 'high' ? 'is_high' : '' ?>">
                    <a class="support_ticket_main" href="index.php?controller=admin&action=showTicket&id=<?= $ticketId ?>">
                        <div class="support_ticket_identity"><span><?= htmlspecialchars(initialsForName($displayName)) ?></span><div><strong><?= htmlspecialchars($displayName) ?></strong><small>#<?= $ticketId ?> · <?= htmlspecialchars($categoryLabels[$ticket['category']] ?? 'Autre') ?></small></div></div>
                        <div class="support_ticket_content"><div><h2><?= htmlspecialchars((string)$ticket['subject']) ?></h2><span class="comms_badge <?= $waitingStaff ? 'is_attention' : 'is_info' ?>"><?= $waitingStaff ? 'Support attendu' : (($ticket['status'] ?? '') === 'closed' ? 'Terminé' : 'Utilisateur attendu') ?></span></div><p><?= htmlspecialchars((string)($ticket['last_message_preview'] ?? '')) ?></p></div>
                        <div class="support_ticket_owner"><span>Responsable</span><strong><?= htmlspecialchars($assignedName !== '' ? $assignedName : 'Non attribué') ?></strong></div>
                        <div class="support_ticket_time"><span><?= htmlspecialchars($age) ?></span><small><?= (int)($ticket['message_count'] ?? 0) ?> message<?= (int)($ticket['message_count'] ?? 0) > 1 ? 's' : '' ?></small></div>
                    </a>
                    <div class="support_ticket_row_actions">
                        <span class="comms_priority is_<?= htmlspecialchars((string)$ticket['priority']) ?>"><?= htmlspecialchars($priorityLabels[$ticket['priority']] ?? 'Normale') ?></span>
                        <?php if (empty($ticket['assigned_admin_id']) && ($ticket['status'] ?? '') !== 'closed'): ?>
                            <form method="POST" action="index.php?controller=admin&action=assignTicket">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><input type="hidden" name="assigned_admin_id" value="<?= $currentAdminId ?>">
                                <button type="submit">Prendre</button>
                            </form>
                        <?php endif; ?>
                        <a href="index.php?controller=admin&action=showTicket&id=<?= $ticketId ?>" aria-label="Ouvrir le ticket #<?= $ticketId ?>">→</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <?php $paginationCurrentPage=$page; $paginationTotalPages=$totalPages; $paginationLabel='Pagination des tickets'; $paginationPageTemplate=$paginationTemplate; require __DIR__ . '/../../partials/admin_pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
function formatTicketDelay(int $minutes): string { if ($minutes <= 0) return 'Non mesurée'; if ($minutes < 60) return $minutes . ' min'; $hours=(int)round($minutes/60); return $hours < 24 ? $hours . ' h' : round($hours/24, 1) . ' j'; }
function humanElapsedTime(string $date): string { $seconds=max(0,time()-(strtotime($date)?:time())); if($seconds<3600)return 'il y a '.max(1,(int)floor($seconds/60)).' min'; if($seconds<86400)return 'il y a '.(int)floor($seconds/3600).' h'; return 'il y a '.(int)floor($seconds/86400).' j'; }
function initialsForName(string $name): string { $parts=preg_split('/\s+/',trim($name))?:[]; return mb_strtoupper(mb_substr($parts[0]??'U',0,1).mb_substr($parts[1]??'',0,1)); }
require_once __DIR__ . '/../../partials/footer.php';
?>
