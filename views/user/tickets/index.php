<?php
require_once __DIR__ . '/../../partials/header.php';
$tickets=is_array($tickets??null)?$tickets:[]; $status=(string)($status??''); $q=(string)($q??'');
$page=(int)($page??1);$totalPages=(int)($totalPages??1);$totalTickets=(int)($totalTickets??0);
$statusLabels=['open'=>'Ouvert','in_progress'=>'En cours','closed'=>'Fermé'];
$categoryLabels=['account'=>'Mon compte','order'=>'Commande','payment'=>'Paiement','shop'=>'Boutique','technical'=>'Problème technique','other'=>'Autre demande'];
$paginationTemplate='index.php?'.http_build_query(['controller'=>'user','action'=>'tickets','page'=>'__PAGE__','status'=>$status,'q'=>$q]);
?>
<main class="main_part comms_page user_support_page">
    <header class="user_support_header">
        <div><span class="section_kicker">Centre d’aide</span><h1>Mes demandes</h1><p>Retrouve toutes tes conversations avec l’équipe CKS GO.</p></div>
        <a class="comms_primary_action" href="index.php?controller=user&action=createTicket"><?= renderUiIcon('support') ?> Nouvelle demande</a>
    </header>

    <section class="comms_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form" data-auto-filter-form>
            <input type="hidden" name="controller" value="user"><input type="hidden" name="action" value="tickets">
            <label class="comms_search_field"><span>Rechercher</span><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" data-auto-filter></label>
            <label><span>Statut</span><select name="status" data-auto-filter><option value="">Tous</option><option value="open" <?= $status==='open'?'selected':'' ?>>Ouverts</option><option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>En cours</option><option value="closed" <?= $status==='closed'?'selected':'' ?>>Fermés</option></select></label>
            <button type="submit">Filtrer</button><?php if($q||$status):?><a href="index.php?controller=user&action=tickets">Effacer</a><?php endif;?>
        </form><span class="comms_result_count"><?= $totalTickets ?> demande<?= $totalTickets>1?'s':'' ?></span>
    </section>

    <?php if(empty($tickets)):?>
        <section class="comms_empty_state"><?= renderUiIcon('support') ?><h2>Aucune demande</h2><p>Si tu rencontres un problème, l’équipe peut t’aider depuis ici.</p><a class="comms_primary_action" href="index.php?controller=user&action=createTicket">Contacter le support</a></section>
    <?php else:?>
        <section class="user_ticket_list">
            <?php foreach($tickets as $ticket):?>
                <?php $closed=($ticket['status']??'')==='closed';$waitingUser=!empty($ticket['last_message_admin_id'])&&!$closed;$assignee=trim((string)($ticket['assigned_admin_firstname']??'').' '.(string)($ticket['assigned_admin_lastname']??''));?>
                <a class="user_ticket_card <?= $waitingUser?'awaiting_user':'' ?>" href="index.php?controller=user&action=showTicket&id=<?= (int)$ticket['id'] ?>">
                    <div class="user_ticket_card_icon"><?= renderUiIcon('ticket') ?></div>
                    <div class="user_ticket_card_body"><div><span>#<?= (int)$ticket['id'] ?> · <?= htmlspecialchars($categoryLabels[$ticket['category']]??'Autre demande') ?></span><span class="comms_badge <?= $waitingUser?'is_attention':($closed?'is_neutral':'is_info') ?>"><?= $waitingUser?'Votre réponse est attendue':($closed?'Terminé':'Pris en charge') ?></span></div><h2><?= htmlspecialchars((string)$ticket['subject']) ?></h2><p><?= htmlspecialchars((string)($ticket['last_message_preview']??'')) ?></p><small><?= $assignee!==''?'Suivi par '.htmlspecialchars($assignee):'En attente d’attribution' ?> · <?= !empty($ticket['last_message_at'])?date('d/m/Y à H:i',strtotime((string)$ticket['last_message_at'])):'-' ?></small></div>
                    <span class="user_ticket_card_arrow">→</span>
                </a>
            <?php endforeach;?>
        </section>
        <?php if($totalPages>1):?><?php $paginationCurrentPage=$page;$paginationTotalPages=$totalPages;$paginationLabel='Pagination des demandes';$paginationPageTemplate=$paginationTemplate;require __DIR__.'/../../partials/admin_pagination.php';?><?php endif;?>
    <?php endif;?>
</main>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
