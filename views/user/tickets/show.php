<?php
require_once __DIR__ . '/../../partials/header.php';
$ticket=is_array($ticket??null)?$ticket:[];$messages=is_array($messages??null)?$messages:[];$ticketId=(int)($ticket['id']??0);$closed=($ticket['status']??'')==='closed';$waitingUser=!empty($ticket['last_message_admin_id'])&&!$closed;
$categoryLabels=['account'=>'Mon compte','order'=>'Commande','payment'=>'Paiement','shop'=>'Boutique','technical'=>'Problème technique','other'=>'Autre demande'];
?>
<main class="main_part comms_page user_ticket_thread_page">
    <header class="user_ticket_thread_header"><div><a class="comms_back_link" href="index.php?controller=user&action=tickets">← Mes demandes</a><span>#<?= $ticketId ?> · <?= htmlspecialchars($categoryLabels[$ticket['category']]??'Autre demande') ?></span><h1><?= htmlspecialchars((string)($ticket['subject']??'Demande')) ?></h1></div><span class="comms_badge <?= $waitingUser?'is_attention':($closed?'is_neutral':'is_info') ?>"><?= $waitingUser?'Votre réponse est attendue':($closed?'Demande terminée':'L’équipe vous répond') ?></span></header>
    <section class="user_support_conversation">
        <div class="support_messages">
            <?php foreach($messages as $message):?>
                <?php $fromStaff=!empty($message['admin_id']);$author=$fromStaff?trim((string)($message['admin_firstname']??'').' '.(string)($message['admin_lastname']??'')):'Vous';if($author==='')$author='Équipe CKS GO';?>
                <article class="support_message <?= $fromStaff?'from_staff':'from_user' ?>"><header><span><?= $fromStaff?'CG':'VO' ?></span><div><strong><?= htmlspecialchars($author) ?></strong><small><?= $fromStaff?'Équipe CKS GO':'Utilisateur' ?></small></div><time><?= date('d/m/Y à H:i',strtotime((string)$message['created_at'])) ?></time></header><div><?= nl2br(htmlspecialchars((string)$message['message'])) ?></div></article>
            <?php endforeach;?>
        </div>
    </section>
    <section class="user_ticket_reply_box">
        <?php if(!$closed):?><form method="POST" action="index.php?controller=user&action=replyTicket"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token??'') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><label><span>Ajouter une réponse</span><textarea name="message" rows="6" maxlength="10000" required></textarea></label><div><small>Votre message relancera automatiquement l’équipe.</small><button type="submit">Envoyer</button></div></form><?php else:?><div><h2>Cette demande est terminée</h2><p>Tu peux la rouvrir si le problème revient ou si une réponse manque.</p><form method="POST" action="index.php?controller=user&action=reopenTicket" data-confirm-message="Rouvrir cette demande ?"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token??'') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><button type="submit">Rouvrir la demande</button></form></div><?php endif;?>
        <?php if(!$closed):?><form method="POST" action="index.php?controller=user&action=closeTicket" class="user_ticket_close" data-confirm-message="Marquer cette demande comme terminée ?"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token??'') ?>"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><button type="submit">Marquer comme résolue</button></form><?php endif;?>
    </section>
</main>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
