<?php
require_once __DIR__ . '/../../partials/header.php';

$ticket = is_array($ticket ?? null) ? $ticket : [];
$messages = is_array($messages ?? null) ? $messages : [];
$staffMembers = is_array($staffMembers ?? null) ? $staffMembers : [];
$allowedStatuses = is_array($allowedStatuses ?? null) ? $allowedStatuses : [];
$allowedPriorities = is_array($allowedPriorities ?? null) ? $allowedPriorities : [];
$ticketId = (int)($ticket['id'] ?? 0);
$closed = ($ticket['status'] ?? '') === 'closed';
$waitingStaff = empty($ticket['last_message_admin_id']) && !$closed;
$userName = trim((string)($ticket['firstname'] ?? '') . ' ' . (string)($ticket['lastname'] ?? '')) ?: ((string)($ticket['username'] ?? 'Utilisateur'));
$assigneeName = trim((string)($ticket['assigned_admin_firstname'] ?? '') . ' ' . (string)($ticket['assigned_admin_lastname'] ?? '')) ?: 'Non attribué';
$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'closed' => 'Fermé'];
$priorityLabels = ['low' => 'Faible', 'medium' => 'Normale', 'high' => 'Haute'];
$categoryLabels = ['account' => 'Compte', 'order' => 'Commande', 'payment' => 'Paiement', 'shop' => 'Boutique', 'technical' => 'Technique', 'other' => 'Autre'];
?>

<main class="main_part comms_page support_thread_page">
    <header class="support_thread_header">
        <div>
            <a class="comms_back_link" href="index.php?controller=admin&action=tickets">← Boîte de réception</a>
            <div class="support_thread_titleline">
                <span>#<?= $ticketId ?></span>
                <h1><?= htmlspecialchars((string)($ticket['subject'] ?? 'Ticket')) ?></h1>
            </div>
            <div class="support_thread_badges">
                <span class="comms_badge <?= $waitingStaff ? 'is_attention' : 'is_info' ?>"><?= $waitingStaff ? 'Réponse du support attendue' : ($closed ? 'Conversation terminée' : 'Réponse utilisateur attendue') ?></span>
                <span class="comms_priority is_<?= htmlspecialchars((string)($ticket['priority'] ?? 'medium')) ?>"><?= htmlspecialchars($priorityLabels[$ticket['priority']] ?? 'Normale') ?></span>
                <span class="comms_badge is_category"><?= htmlspecialchars($categoryLabels[$ticket['category']] ?? 'Autre') ?></span>
            </div>
        </div>
        <div class="support_thread_user">
            <span><?= htmlspecialchars(initialsForSupportName($userName)) ?></span>
            <div><strong><?= htmlspecialchars($userName) ?></strong><a href="mailto:<?= htmlspecialchars((string)($ticket['email'] ?? '')) ?>"><?= htmlspecialchars((string)($ticket['email'] ?? '')) ?></a></div>
        </div>
    </header>

    <div class="support_thread_layout">
        <div class="support_thread_main">
            <section class="support_conversation">
                <div class="support_conversation_head"><div><h2>Conversation</h2><p><?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?></p></div><span>Dernière activité <?= !empty($ticket['last_message_at']) ? date('d/m/Y à H:i', strtotime((string)$ticket['last_message_at'])) : 'inconnue' ?></span></div>

                <div class="support_messages">
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $fromStaff = !empty($message['admin_id']);
                        $author = $fromStaff
                            ? trim((string)($message['admin_firstname'] ?? '') . ' ' . (string)($message['admin_lastname'] ?? ''))
                            : trim((string)($message['user_firstname'] ?? '') . ' ' . (string)($message['user_lastname'] ?? ''));
                        if ($author === '') $author = $fromStaff ? 'Équipe CKS GO' : $userName;
                        ?>
                        <article class="support_message <?= $fromStaff ? 'from_staff' : 'from_user' ?>">
                            <header><span><?= htmlspecialchars(initialsForSupportName($author)) ?></span><div><strong><?= htmlspecialchars($author) ?></strong><small><?= $fromStaff ? 'Support' : 'Utilisateur' ?></small></div><time><?= date('d/m/Y à H:i', strtotime((string)$message['created_at'])) ?></time></header>
                            <div><?= nl2br(htmlspecialchars((string)$message['message'])) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="support_reply_box <?= $closed ? 'is_closed' : '' ?>">
                <?php if ($closed): ?>
                    <div><h2>Ce ticket est fermé</h2><p>Rouvre la conversation depuis le panneau de droite pour envoyer une nouvelle réponse.</p></div>
                <?php else: ?>
                    <div class="support_reply_head"><div><span class="section_kicker">Réponse</span><h2>Écrire à <?= htmlspecialchars($userName) ?></h2></div><small>La réponse sera visible immédiatement.</small></div>
                    <form method="POST" action="index.php?controller=admin&action=replyTicket">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><input type="hidden" name="set_in_progress" value="1">
                        <label><span>Message</span><textarea name="message" rows="7" maxlength="10000" required></textarea></label>
                        <div><span>Le ticket passera en cours et te sera attribué s’il ne l’est pas encore.</span><button type="submit">Envoyer la réponse</button></div>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <aside class="support_thread_sidebar">
            <section>
                <div class="support_sidebar_head"><span>Responsable</span><strong><?= htmlspecialchars($assigneeName) ?></strong></div>
                <form method="POST" action="index.php?controller=admin&action=assignTicket" class="support_sidebar_form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                    <label><span>Attribuer à</span><select name="assigned_admin_id"><option value="">Personne</option><?php foreach ($staffMembers as $member): ?><?php $name=trim((string)$member['firstname'].' '.(string)$member['lastname']) ?: (string)$member['username']; ?><option value="<?= (int)$member['id'] ?>" <?= (int)($ticket['assigned_admin_id'] ?? 0)===(int)$member['id']?'selected':'' ?>><?= htmlspecialchars($name) ?> · <?= htmlspecialchars(getRoleLabel($member['role'], true)) ?></option><?php endforeach; ?></select></label>
                    <button type="submit">Enregistrer</button>
                </form>
            </section>

            <section>
                <div class="support_sidebar_head"><span>Traitement</span><strong><?= htmlspecialchars($statusLabels[$ticket['status']] ?? 'Ouvert') ?></strong></div>
                <form method="POST" action="index.php?controller=admin&action=updateTicketStatus" class="support_sidebar_form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                    <label><span>Statut</span><select name="status"><?php foreach ($allowedStatuses as $item): ?><option value="<?= $item ?>" <?= ($ticket['status']??'')===$item?'selected':'' ?>><?= $statusLabels[$item]??$item ?></option><?php endforeach; ?></select></label>
                    <button type="submit">Mettre à jour</button>
                </form>
                <form method="POST" action="index.php?controller=admin&action=updateTicketPriority" class="support_sidebar_form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                    <label><span>Priorité</span><select name="priority"><?php foreach ($allowedPriorities as $item): ?><option value="<?= $item ?>" <?= ($ticket['priority']??'')===$item?'selected':'' ?>><?= $priorityLabels[$item]??$item ?></option><?php endforeach; ?></select></label>
                    <button type="submit">Mettre à jour</button>
                </form>
            </section>

            <section class="support_ticket_facts">
                <div class="support_sidebar_head"><span>Repères</span></div>
                <dl>
                    <div><dt>Créé le</dt><dd><?= date('d/m/Y à H:i', strtotime((string)$ticket['created_at'])) ?></dd></div>
                    <div><dt>Première réponse</dt><dd><?= !empty($ticket['first_response_at']) ? date('d/m/Y à H:i', strtotime((string)$ticket['first_response_at'])) : 'En attente' ?></dd></div>
                    <div><dt>Messages</dt><dd><?= (int)($ticket['message_count'] ?? 0) ?></dd></div>
                </dl>
                <?php if ($closed): ?>
                    <form method="POST" action="index.php?controller=admin&action=reopenTicket" data-confirm-message="Rouvrir ce ticket ?"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><button type="submit">Rouvrir le ticket</button></form>
                <?php else: ?>
                    <form method="POST" action="index.php?controller=admin&action=closeTicket" data-confirm-message="Fermer ce ticket ? L’utilisateur pourra le rouvrir."><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><button type="submit" class="is_danger">Fermer le ticket</button></form>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>

<?php
function initialsForSupportName(string $name): string { $parts=preg_split('/\s+/',trim($name))?:[]; return mb_strtoupper(mb_substr($parts[0]??'U',0,1).mb_substr($parts[1]??'',0,1)); }
require_once __DIR__ . '/../../partials/footer.php';
?>
