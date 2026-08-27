<?php
require_once __DIR__ . '/../../partials/header.php';
$allowedPriorities=is_array($allowedPriorities??null)?$allowedPriorities:[];$allowedCategories=is_array($allowedCategories??null)?$allowedCategories:[];
$categoryLabels=['account'=>'Mon compte','order'=>'Une commande','payment'=>'Un paiement','shop'=>'La boutique','technical'=>'Un problème technique','other'=>'Autre chose'];
$categoryHints=['account'=>'Connexion, profil ou accès','order'=>'Suivi, contenu ou retrait','payment'=>'Encaissement ou remboursement','shop'=>'Produit, stock ou disponibilité','technical'=>'Affichage ou fonctionnement','other'=>'Toute autre question'];
?>
<main class="main_part comms_page user_ticket_create_page">
    <header class="comms_page_header is_compact"><div><a class="comms_back_link" href="index.php?controller=user&action=tickets">← Mes demandes</a><h1>Comment pouvons-nous aider ?</h1><p>Choisis le sujet puis décris précisément la situation. L’équipe retrouvera tout dans une seule conversation.</p></div></header>
    <form method="POST" action="index.php?controller=user&action=storeTicket" class="user_ticket_create_form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token??'') ?>">
        <section><div class="news_editor_section_head"><span>01</span><div><h2>Sujet de la demande</h2><p>La catégorie aide l’équipe à orienter plus vite la demande.</p></div></div><div class="ticket_category_choices"><?php foreach($allowedCategories as $item):?><label><input type="radio" name="category" value="<?= $item ?>" <?= $item==='other'?'checked':'' ?>><span><?= renderUiIcon($item==='order'?'orders':($item==='payment'?'payment':($item==='shop'?'shop':'support'))) ?><strong><?= htmlspecialchars($categoryLabels[$item]??$item) ?></strong><small><?= htmlspecialchars($categoryHints[$item]??'') ?></small></span></label><?php endforeach;?></div></section>
        <section><div class="news_editor_section_head"><span>02</span><div><h2>Votre message</h2><p>Ajoute les références utiles, les dates et ce que tu attends de l’équipe.</p></div></div><label class="news_editor_field"><span>Objet</span><input type="text" name="subject" maxlength="150" required><em>Exemple : commande non disponible au retrait.</em></label><label class="news_editor_field"><span>Description</span><textarea name="message" rows="10" maxlength="10000" required></textarea><em>Évite les données sensibles comme un mot de passe ou un numéro de carte.</em></label><label class="news_editor_field ticket_priority_field"><span>Niveau d’urgence</span><select name="priority"><?php foreach($allowedPriorities as $item):?><option value="<?= $item ?>" <?= $item==='medium'?'selected':'' ?>><?= ['low'=>'Peut attendre','medium'=>'Normale','high'=>'Urgente'][$item]??$item ?></option><?php endforeach;?></select><em>Réserve « urgente » à un blocage réel.</em></label></section>
        <div class="user_ticket_create_actions"><a href="index.php?controller=user&action=tickets">Annuler</a><button type="submit">Envoyer ma demande</button></div>
    </form>
</main>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
