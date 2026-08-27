<?php
require_once __DIR__ . '/../../partials/header.php';

$alerts = is_array($alerts ?? null) ? $alerts : [];
$stats = is_array($alert_stats ?? null) ? $alert_stats : [];
$allowedStatuses = is_array($allowedStatuses ?? null) ? $allowedStatuses : [];
$allowedPriorities = is_array($allowedPriorities ?? null) ? $allowedPriorities : [];
$allowedTypes = is_array($allowedTypes ?? null) ? $allowedTypes : [];
$status = (string)($status ?? ''); $priority = (string)($priority ?? ''); $type = (string)($type ?? '');
$owner = (string)($owner ?? ''); $age = (string)($age ?? ''); $q = (string)($q ?? '');
$page = (int)($page ?? 1); $totalPages = (int)($totalPages ?? 1); $totalAlerts = (int)($totalAlerts ?? 0);
$currentAdminId = (int)($_SESSION['user']['id'] ?? 0);

$statusLabels = ['open'=>'Ouverte','in_progress'=>'En cours','resolved'=>'Résolue','dismissed'=>'Classée sans suite'];
$priorityLabels = ['low'=>'Faible','medium'=>'Normale','high'=>'Haute'];
$typeLabels = ['missing_product'=>'Produit manquant','stock_mismatch'=>'Écart de stock','wrong_variant'=>'Mauvaise variante','damaged_product'=>'Produit endommagé','manual_check_required'=>'Vérification nécessaire'];
$paginationTemplate='index.php?'.http_build_query(['controller'=>'admin','action'=>'alerts','page'=>'__PAGE__','q'=>$q,'status'=>$status,'priority'=>$priority,'type'=>$type,'owner'=>$owner,'age'=>$age]);
?>

<main class="main_part comms_page alerts_queue_page">
    <header class="comms_page_header">
        <div><span class="section_kicker">Signalements boutique</span><h1>Centre d’alertes</h1><p>Qualifie les incidents, repère les récurrences et garde une file propre.</p></div>
        <div class="alerts_health"><span>Alertes actives</span><strong><?= (int)($stats['open_alerts']??0)+(int)($stats['in_progress_alerts']??0) ?></strong><small><?= (int)($stats['stale_active_alerts']??0) ?> ancienne<?= (int)($stats['stale_active_alerts']??0)>1?'s':'' ?> de plus de 48 h</small></div>
    </header>

    <nav class="comms_stat_nav" aria-label="Files d’alertes">
        <a class="<?= $status==='open'?'is_active':'' ?>" href="index.php?controller=admin&action=alerts&status=open"><span>À traiter</span><strong><?= (int)($stats['open_alerts']??0) ?></strong></a>
        <a class="<?= $owner==='unassigned'?'is_active':'' ?>" href="index.php?controller=admin&action=alerts&owner=unassigned"><span>Non attribuées</span><strong><?= (int)($stats['unassigned_active_alerts']??0) ?></strong></a>
        <a class="<?= $owner==='mine'?'is_active':'' ?>" href="index.php?controller=admin&action=alerts&owner=mine"><span>Mes alertes</span><strong>→</strong></a>
        <a class="<?= $priority==='high'?'is_active':'' ?>" href="index.php?controller=admin&action=alerts&priority=high"><span>Priorité haute</span><strong><?= (int)($stats['high_priority_active_alerts']??0) ?></strong></a>
        <a class="<?= $age==='stale'?'is_active':'' ?>" href="index.php?controller=admin&action=alerts&age=stale"><span>Plus de 48 h</span><strong><?= (int)($stats['stale_active_alerts']??0) ?></strong></a>
    </nav>

    <section class="comms_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form is_dense" data-auto-filter-form>
            <input type="hidden" name="controller" value="admin"><input type="hidden" name="action" value="alerts">
            <label class="comms_search_field"><span>Rechercher</span><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" data-auto-filter></label>
            <label><span>Statut</span><select name="status" data-auto-filter><option value="">Tous</option><?php foreach($allowedStatuses as $item):?><option value="<?= $item ?>" <?= $status===$item?'selected':'' ?>><?= htmlspecialchars($statusLabels[$item]??$item) ?></option><?php endforeach;?></select></label>
            <label><span>Priorité</span><select name="priority" data-auto-filter><option value="">Toutes</option><?php foreach($allowedPriorities as $item):?><option value="<?= $item ?>" <?= $priority===$item?'selected':'' ?>><?= htmlspecialchars($priorityLabels[$item]??$item) ?></option><?php endforeach;?></select></label>
            <label><span>Type</span><select name="type" data-auto-filter><option value="">Tous</option><?php foreach($allowedTypes as $item):?><option value="<?= $item ?>" <?= $type===$item?'selected':'' ?>><?= htmlspecialchars($typeLabels[$item]??$item) ?></option><?php endforeach;?></select></label>
            <label><span>Responsable</span><select name="owner" data-auto-filter><option value="">Tous</option><option value="mine" <?= $owner==='mine'?'selected':'' ?>>Mes alertes</option><option value="unassigned" <?= $owner==='unassigned'?'selected':'' ?>>Non attribuées</option></select></label>
            <label><span>Ancienneté</span><select name="age" data-auto-filter><option value="">Toutes</option><option value="recent" <?= $age==='recent'?'selected':'' ?>>Moins de 48 h</option><option value="stale" <?= $age==='stale'?'selected':'' ?>>Plus de 48 h</option></select></label>
            <button type="submit">Filtrer</button><?php if($q||$status||$priority||$type||$owner||$age):?><a href="index.php?controller=admin&action=alerts">Effacer</a><?php endif;?>
        </form>
        <span class="comms_result_count"><?= $totalAlerts ?> alerte<?= $totalAlerts>1?'s':'' ?></span>
    </section>

    <?php if(empty($alerts)):?>
        <section class="comms_empty_state"><?= renderUiIcon('alert') ?><h2>Aucune alerte dans cette file</h2><p>Tout est à jour pour les filtres sélectionnés.</p></section>
    <?php else:?>
        <section class="alerts_queue" aria-label="Alertes">
            <?php foreach($alerts as $alert):?>
                <?php
                $id=(int)$alert['id']; $active=in_array($alert['status'],['open','in_progress'],true);
                $reported=strtotime((string)($alert['last_reported_at']??''))?:time(); $stale=$active&&$reported<time()-172800;
                $product=trim((string)($alert['product_name']??''))?:'Produit non identifié'; $variant=trim((string)($alert['variant_name']??''));
                $assignee=trim((string)($alert['assigned_admin_firstname']??'').' '.(string)($alert['assigned_admin_lastname']??''));
                $reporter=trim((string)($alert['reporter_firstname']??'').' '.(string)($alert['reporter_lastname']??''))?:'Utilisateur';
                ?>
                <article class="alert_queue_item <?= $stale?'is_stale':'' ?> <?= $alert['priority']==='high'?'is_high':'' ?>">
                    <div class="alert_queue_signal"><span><?= (int)($alert['occurrence_count']??1) ?></span><small>signalement<?= (int)($alert['occurrence_count']??1)>1?'s':'' ?></small></div>
                    <a class="alert_queue_content" href="index.php?controller=admin&action=showAlert&id=<?= $id ?>">
                        <div class="alert_queue_title"><span class="comms_badge is_category"><?= htmlspecialchars($typeLabels[$alert['type']]??$alert['type']) ?></span><?php if($stale):?><span class="comms_badge is_attention">À relancer</span><?php endif;?></div>
                        <h2><?= htmlspecialchars($product) ?><?= $variant!==''?' · '.htmlspecialchars($variant):'' ?></h2>
                        <p><?= htmlspecialchars(mb_strimwidth(trim((string)($alert['message']??'')),0,180,'…')) ?></p>
                    </a>
                    <dl class="alert_queue_meta"><div><dt>Signalé par</dt><dd><?= htmlspecialchars($reporter) ?></dd></div><div><dt>Responsable</dt><dd><?= htmlspecialchars($assignee!==''?$assignee:'Non attribuée') ?></dd></div><div><dt>Dernier signalement</dt><dd><?= date('d/m/Y à H:i',$reported) ?></dd></div></dl>
                    <div class="alert_queue_actions"><span class="comms_priority is_<?= htmlspecialchars($alert['priority']) ?>"><?= htmlspecialchars($priorityLabels[$alert['priority']]??'Normale') ?></span><?php if(empty($alert['assigned_admin_id'])&&$active):?><form method="POST" action="index.php?controller=admin&action=assignAlert"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token??'') ?>"><input type="hidden" name="alert_id" value="<?= $id ?>"><button type="submit">Prendre</button></form><?php endif;?><a href="index.php?controller=admin&action=showAlert&id=<?= $id ?>">Ouvrir →</a></div>
                </article>
            <?php endforeach;?>
        </section>
        <?php if($totalPages>1):?><?php $paginationCurrentPage=$page;$paginationTotalPages=$totalPages;$paginationLabel='Pagination des alertes';$paginationPageTemplate=$paginationTemplate;require __DIR__.'/../../partials/admin_pagination.php';?><?php endif;?>
    <?php endif;?>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
