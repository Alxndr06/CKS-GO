<?php require_once __DIR__ . '/../../partials/header.php'; ?>
<?php
$statusLabels = [
        'open' => 'Ouverte',
        'in_progress' => 'En cours',
        'resolved' => 'Résolue',
        'dismissed' => 'Écartée',
];

$priorityLabels = [
        'low' => 'Basse',
        'medium' => 'Moyenne',
        'high' => 'Haute',
];

$typeLabels = [
        'missing_product' => 'Produit absent',
        'stock_mismatch' => 'Écart stock',
        'wrong_variant' => 'Mauvaise variante',
        'damaged_product' => 'Produit abîmé',
        'manual_check_required' => 'Vérification manuelle',
];

$sourceLabels = [
        'shop_product' => 'Boutique',
        'cart' => 'Panier',
        'order_success' => 'Commande validée',
        'user_order' => 'Commande utilisateur',
        'admin_manual' => 'Saisie admin',
];

$eventLabels = [
        'report_created' => 'Signalement initial',
        'duplicate_report' => 'Nouveau signalement',
        'assigned_to_admin' => 'Prise en charge',
        'priority_updated' => 'Priorité modifiée',
        'status_updated' => 'Statut mis à jour',
        'reopened' => 'Alerte rouverte',
        'admin_note' => 'Note admin',
        'report_refunded' => 'Signalant remboursé',
];

$resolutionLabels = [
        'restocked' => 'Produit remis au stock',
        'consumed' => 'Produit consommé / détruit',
        'false_positive' => 'Faux positif',
        'manual_fix' => 'Correction manuelle',
        'refunded' => 'Produit remboursé',
        'other' => 'Autre',
];

$statusKey = (string) ($alert['status'] ?? 'open');
$priorityKey = (string) ($alert['priority'] ?? 'medium');
$typeKey = (string) ($alert['type'] ?? 'manual_check_required');
$sourceKey = (string) ($alert['source_context'] ?? 'shop_product');

$reporterName = trim((string) ((($alert['reporter_firstname'] ?? '') . ' ' . ($alert['reporter_lastname'] ?? ''))));
$assignedAdminName = trim((string) ((($alert['assigned_admin_firstname'] ?? '') . ' ' . ($alert['assigned_admin_lastname'] ?? ''))));
$productName = trim((string) ($alert['product_name'] ?? ''));
$variantName = trim((string) ($alert['variant_name'] ?? ''));
$title = trim((string) ($alert['title'] ?? 'Alerte boutique'));
$message = trim((string) ($alert['message'] ?? ''));
$resolutionNote = trim((string) ($alert['resolution_note'] ?? ''));
$resolutionCode = trim((string) ($alert['resolution_code'] ?? ''));
$refundContext = is_array($refundContext ?? null) ? $refundContext : [];
$alertRefunds = is_array($refundContext['refunds'] ?? null) ? $refundContext['refunds'] : [];
$refundItems = is_array($refundContext['items'] ?? null) ? $refundContext['items'] : [];
$refundableItems = is_array($refundContext['refundable_items'] ?? null) ? $refundContext['refundable_items'] : [];
?>

<main class="main_part admin_dashboard_page admin_alert_show_page">
    <section class="admin_dashboard_intro admin_alert_show_intro">
        <span class="section_kicker">Alerte</span>
        <h2>Alerte #<?= (int) ($alert['id'] ?? 0) ?></h2>
        <p>Consulte le signalement détaillé, son historique, puis traite-le depuis cette fiche.</p>
    </section>

    <section class="admin_dashboard_section admin_alert_show_navbar">
        <div class="aushow_topbar">
            <div class="aushow_topbar_meta">
                <span class="aushow_topbar_label">Navigation / actions rapides</span>
                <span class="aushow_topbar_count">Alerte #<?= (int) ($alert['id'] ?? 0) ?></span>
            </div>

            <div class="aushow_topbar_actions">
                <a class="aushow_toolbar_link aushow_toolbar_link_light" href="index.php?controller=admin&amp;action=alerts">
                    Retour aux alertes
                </a>

                <?php if ((int) ($alert['order_id'] ?? 0) > 0): ?>
                    <a class="aushow_toolbar_link aushow_toolbar_link_primary" href="index.php?controller=admin&amp;action=showOrder&amp;id=<?= (int) ($alert['order_id'] ?? 0) ?>">
                        Voir la commande liée
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="admin_dashboard_section admin_alert_show_grid">
        <div class="admin_alert_show_main">
            <article class="admin_alert_show_card admin_alert_show_card_primary">
                <div class="admin_alert_show_head">
                    <div>
                        <span class="admin_alert_show_overline">Signalement</span>
                        <h3><?= htmlspecialchars($title) ?></h3>

                        <?php if ($message !== ''): ?>
                            <p class="admin_alert_show_message"><?= nl2br(htmlspecialchars($message)) ?></p>
                        <?php else: ?>
                            <p class="admin_alert_show_message">Aucun message complémentaire n’a été laissé avec ce signalement.</p>
                        <?php endif; ?>
                    </div>

                    <div class="admin_alert_show_badges">
                        <span class="admin_alert_badge admin_alert_badge_status_<?= htmlspecialchars($statusKey) ?>">
                            <?= htmlspecialchars($statusLabels[$statusKey] ?? $statusKey) ?>
                        </span>
                        <span class="admin_alert_badge admin_alert_badge_priority_<?= htmlspecialchars($priorityKey) ?>">
                            <?= htmlspecialchars($priorityLabels[$priorityKey] ?? $priorityKey) ?>
                        </span>
                        <span class="admin_alert_badge admin_alert_badge_type">
                            <?= htmlspecialchars($typeLabels[$typeKey] ?? $typeKey) ?>
                        </span>
                    </div>
                </div>

                <div class="admin_alert_show_meta_grid">
                    <div class="admin_alert_show_meta_item">
                        <span>Produit</span>
                        <strong>
                            <?= count($refundItems) > 1
                                    ? count($refundItems) . ' produits sélectionnés'
                                    : htmlspecialchars($productName !== '' ? $productName : 'Non renseigné') ?>
                        </strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Variante</span>
                        <strong><?= htmlspecialchars($variantName !== '' ? $variantName : 'Aucune') ?></strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Source</span>
                        <strong><?= htmlspecialchars($sourceLabels[$sourceKey] ?? $sourceKey) ?></strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Commande liée</span>
                        <strong><?= (int) ($alert['order_id'] ?? 0) > 0 ? '#' . (int) ($alert['order_id'] ?? 0) : 'Aucune' ?></strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Ligne(s) produit</span>
                        <strong>
                            <?= count($refundItems) > 1
                                    ? count($refundItems) . ' lignes'
                                    : ((int)($alert['order_item_id'] ?? 0) > 0
                                    ? '#' . (int)$alert['order_item_id']
                                    : ((int)($alert['order_id'] ?? 0) > 0 ? 'À sélectionner' : 'Aucune')) ?>
                        </strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Signalé par</span>
                        <strong><?= htmlspecialchars($reporterName !== '' ? $reporterName : 'Utilisateur inconnu') ?></strong>
                        <?php if (!empty($alert['reporter_username'])): ?>
                            <small>@<?= htmlspecialchars((string) ($alert['reporter_username'] ?? '')) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Attribuée à</span>
                        <strong><?= htmlspecialchars($assignedAdminName !== '' ? $assignedAdminName : 'Personne pour le moment') ?></strong>
                        <?php if (!empty($alert['assigned_admin_username'])): ?>
                            <small>@<?= htmlspecialchars((string) ($alert['assigned_admin_username'] ?? '')) ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Dernier signalement</span>
                        <strong>
                            <?= !empty($alert['last_reported_at'])
                                    ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($alert['last_reported_at'] ?? ''))))
                                    : '—' ?>
                        </strong>
                    </div>

                    <div class="admin_alert_show_meta_item">
                        <span>Résolue le</span>
                        <strong>
                            <?= !empty($alert['resolved_at'])
                                    ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($alert['resolved_at'] ?? ''))))
                                    : 'Pas encore' ?>
                        </strong>
                    </div>
                </div>

                <div class="admin_alert_show_kpis">
                    <article class="admin_alert_show_kpi">
                        <span>Occurrences</span>
                        <strong><?= (int) ($alert['occurrence_count'] ?? 1) ?></strong>
                    </article>

                    <article class="admin_alert_show_kpi">
                        <span>Type</span>
                        <strong><?= htmlspecialchars($typeLabels[$typeKey] ?? $typeKey) ?></strong>
                    </article>

                    <article class="admin_alert_show_kpi">
                        <span>Priorité</span>
                        <strong><?= htmlspecialchars($priorityLabels[$priorityKey] ?? $priorityKey) ?></strong>
                    </article>

                    <article class="admin_alert_show_kpi">
                        <span>Statut</span>
                        <strong><?= htmlspecialchars($statusLabels[$statusKey] ?? $statusKey) ?></strong>
                    </article>
                </div>
            </article>

            <?php if ($resolutionNote !== '' || $resolutionCode !== ''): ?>
                <article class="admin_alert_show_card">
                    <div class="section_heading compact">
                        <h3>Traitement enregistré</h3>
                        <p>Dernière opération de résolution connue pour cette alerte.</p>
                    </div>

                    <div class="admin_alert_show_resolution_grid">
                        <div class="admin_alert_show_meta_item">
                            <span>Code de résolution</span>
                            <strong><?= htmlspecialchars($resolutionLabels[$resolutionCode] ?? ($resolutionCode !== '' ? $resolutionCode : '—')) ?></strong>
                        </div>

                        <div class="admin_alert_show_meta_item admin_alert_show_resolution_note">
                            <span>Note de résolution</span>
                            <strong><?= $resolutionNote !== '' ? nl2br(htmlspecialchars($resolutionNote)) : 'Aucune note enregistrée.' ?></strong>
                        </div>
                    </div>
                </article>
            <?php endif; ?>

            <article class="admin_alert_show_card">
                <div class="section_heading compact">
                    <h3>Historique</h3>
                    <p>Suivi chronologique de l’alerte et des actions administratives.</p>
                </div>

                <?php if (empty($events)): ?>
                    <div class="empty_state_card admin_alerts_empty_state">
                        <h3>Aucun événement</h3>
                        <p>Cette alerte n’a pas encore d’historique exploitable.</p>
                    </div>
                <?php else: ?>
                    <div class="admin_alert_timeline">
                        <?php foreach ((array) $events as $event): ?>
                            <?php
                            $eventMeta = [];
                            if (!empty($event['meta_json'])) {
                                $decodedMeta = json_decode((string) $event['meta_json'], true);
                                if (is_array($decodedMeta)) {
                                    $eventMeta = $decodedMeta;
                                }
                            }

                            $eventAuthor = trim((string) ((($event['admin_firstname'] ?? '') . ' ' . ($event['admin_lastname'] ?? ''))));
                            $userAuthor = trim((string) ((($event['user_firstname'] ?? '') . ' ' . ($event['user_lastname'] ?? ''))));
                            ?>
                            <article class="admin_alert_timeline_item">
                                <div class="admin_alert_timeline_marker"></div>

                                <div class="admin_alert_timeline_body">
                                    <div class="admin_alert_timeline_head">
                                        <div>
                                            <h4><?= htmlspecialchars($eventLabels[(string) ($event['event_type'] ?? '')] ?? ((string) ($event['event_type'] ?? 'Événement'))) ?></h4>
                                            <p>
                                                <?php if ($eventAuthor !== ''): ?>
                                                    Par <strong><?= htmlspecialchars($eventAuthor) ?></strong>
                                                <?php elseif (!empty($event['admin_username'])): ?>
                                                    Par <strong>@<?= htmlspecialchars((string) ($event['admin_username'] ?? '')) ?></strong>
                                                <?php elseif ($userAuthor !== ''): ?>
                                                    Par <strong><?= htmlspecialchars($userAuthor) ?></strong>
                                                <?php elseif (!empty($event['user_username'])): ?>
                                                    Par <strong>@<?= htmlspecialchars((string) ($event['user_username'] ?? '')) ?></strong>
                                                <?php else: ?>
                                                    Événement système
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <time datetime="<?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?>">
                                            <?= !empty($event['created_at'])
                                                    ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($event['created_at'] ?? ''))))
                                                    : '—' ?>
                                        </time>
                                    </div>

                                    <?php if (!empty($event['message'])): ?>
                                        <div class="admin_alert_timeline_message">
                                            <?= nl2br(htmlspecialchars((string) ($event['message'] ?? ''))) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($eventMeta)): ?>
                                        <div class="admin_alert_timeline_meta">
                                            <?php foreach ($eventMeta as $metaKey => $metaValue): ?>
                                                <?php if ($metaValue === null || $metaValue === '') {
                                                    continue;
                                                } ?>
                                                <div>
                                                    <span><?= htmlspecialchars(str_replace('_', ' ', (string) $metaKey)) ?></span>
                                                    <strong>
                                                        <?php
                                                        if (is_scalar($metaValue)) {
                                                            echo htmlspecialchars((string) $metaValue);
                                                        } else {
                                                            echo htmlspecialchars(json_encode($metaValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                                                        }
                                                        ?>
                                                    </strong>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>

        <aside class="admin_alert_show_side">
            <?php if (!empty($refundContext['supported']) || $alertRefunds !== []): ?>
                <article class="admin_alert_show_card admin_alert_show_side_card alert_refund_card <?= empty($refundContext['can_refund']) && $alertRefunds !== [] ? 'is_completed' : '' ?>">
                    <div class="section_heading compact">
                        <h3>Remboursement du signalant</h3>
                        <p>Choisis une ou plusieurs lignes. L’ensemble est traité dans une seule opération sécurisée.</p>
                    </div>

                    <?php if ($alertRefunds !== []): ?>
                        <div class="alert_refund_receipts">
                            <?php foreach ($alertRefunds as $alertRefund): ?>
                                <?php
                                $refundAdminName = trim((string)(
                                    (($alertRefund['admin_firstname'] ?? '') . ' ' . ($alertRefund['admin_lastname'] ?? ''))
                                ));
                                $receiptProductName = trim((string)($alertRefund['product_name'] ?? 'Produit'));
                                ?>
                                <div class="alert_refund_receipt">
                                    <span class="alert_refund_receipt_icon" aria-hidden="true"><?= renderUiIcon('payment') ?></span>
                                    <div>
                                        <strong><?= number_format((float)($alertRefund['amount'] ?? 0), 2, ',', ' ') ?> € remboursés</strong>
                                        <p><?= htmlspecialchars($receiptProductName) ?> · <?= (int)($alertRefund['quantity_refunded'] ?? 0) ?> unité(s)</p>
                                        <small>
                                            Ligne #<?= (int)($alertRefund['order_item_id'] ?? 0) ?>
                                            <?= !empty($alertRefund['refund_created_at'])
                                                ? ' · ' . htmlspecialchars(date('d/m/Y H:i', strtotime((string)$alertRefund['refund_created_at'])))
                                                : '' ?>
                                            <?= $refundAdminName !== '' ? ' · ' . htmlspecialchars($refundAdminName) : '' ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($refundContext['can_refund']) && currentUserCan('orders.refund')): ?>
                        <form
                                method="POST"
                                action="index.php?controller=admin&amp;action=refundAlertReporter"
                                class="admin_alert_form_stack alert_refund_form"
                                data-confirm-message="Confirmer le remboursement des produits sélectionnés au signalant ?"
                                data-alert-refund-picker
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                            <input type="hidden" name="alert_id" value="<?= (int)($alert['id'] ?? 0) ?>">

                            <label class="alert_refund_select_all">
                                <input type="checkbox" checked data-alert-refund-select-all>
                                <span>Tout sélectionner</span>
                            </label>

                            <div class="alert_refund_candidates">
                            <?php foreach ($refundableItems as $refundItem): ?>
                                <?php
                                    $maxRefundQuantity = max(1, (int)($refundItem['max_refundable_quantity'] ?? 1));
                                    $refundItemName = trim((string)($refundItem['product_name'] ?? 'Produit'));
                                    $refundVariantName = trim((string)($refundItem['variant_name'] ?? ''));
                                    $refundItemId = (int)($refundItem['order_item_id'] ?? 0);
                                ?>
                                <div class="alert_refund_candidate">
                                    <div class="alert_refund_product">
                                        <label class="alert_refund_product_choice">
                                            <input type="checkbox" name="refund_item_ids[]" value="<?= $refundItemId ?>" checked data-alert-refund-item>
                                            <span>Inclure ce produit</span>
                                        </label>
                                        <strong><?= htmlspecialchars($refundItemName) ?></strong>
                                        <?php if ($refundVariantName !== '' && strcasecmp($refundVariantName, 'Standard') !== 0): ?>
                                            <small><?= htmlspecialchars($refundVariantName) ?></small>
                                        <?php endif; ?>
                                        <small>
                                            <?= number_format((float)($refundItem['unit_price'] ?? 0), 2, ',', ' ') ?> € l’unité ·
                                            <?= $maxRefundQuantity ?> remboursable(s)
                                        </small>
                                    </div>

                                    <label>
                                        Quantité à rembourser
                                        <input type="number" name="refund_quantities[<?= $refundItemId ?>]" min="1" max="<?= $maxRefundQuantity ?>" value="1" required data-alert-refund-quantity>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            </div>

                            <label>
                                Traitement du stock
                                <select name="stock_action" required>
                                    <option value="consumed">Ne pas remettre en stock</option>
                                    <option value="restock">Produits récupérés : remettre en stock</option>
                                </select>
                            </label>

                            <p class="alert_refund_selection_hint" data-alert-refund-hint></p>
                            <button type="submit" class="admin_alert_btn admin_alert_btn_refund">
                                Rembourser la sélection
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert_refund_unavailable">
                            <?= renderUiIcon('info') ?>
                            <p><?= htmlspecialchars((string)($refundContext['message'] ?? 'Remboursement indisponible.')) ?></p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <article class="admin_alert_show_card admin_alert_show_side_card">
                <div class="section_heading compact">
                    <h3>Actions rapides</h3>
                    <p>Pilote le traitement sans quitter la fiche.</p>
                </div>

                <?php if ((int) ($alert['assigned_admin_id'] ?? 0) <= 0 && in_array($statusKey, ['open', 'in_progress'], true)): ?>
                    <form method="POST" action="index.php?controller=admin&amp;action=assignAlert" class="admin_alert_form_stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                        <input type="hidden" name="alert_id" value="<?= (int) ($alert['id'] ?? 0) ?>">
                        <button type="submit" class="admin_alert_btn admin_alert_btn_primary">Prendre en charge</button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="index.php?controller=admin&amp;action=updateAlertStatus" class="admin_alert_form_stack">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                    <input type="hidden" name="alert_id" value="<?= (int) ($alert['id'] ?? 0) ?>">

                    <label>
                        Statut
                        <select name="status" required>
                            <?php foreach ((array) ($allowedStatuses ?? []) as $allowedStatus): ?>
                                <option value="<?= htmlspecialchars((string) $allowedStatus) ?>" <?= $statusKey === $allowedStatus ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statusLabels[$allowedStatus] ?? (string) $allowedStatus) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Code de résolution
                        <select name="resolution_code">
                            <option value="">Aucun</option>
                            <?php foreach ($resolutionLabels as $resolutionValue => $resolutionLabel): ?>
                                <option value="<?= htmlspecialchars($resolutionValue) ?>" <?= $resolutionCode === $resolutionValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($resolutionLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Note de résolution
                        <textarea name="resolution_note" rows="4" placeholder="Explique le traitement effectué..."><?= htmlspecialchars($resolutionNote) ?></textarea>
                    </label>

                    <button type="submit" class="admin_alert_btn admin_alert_btn_primary">Mettre à jour le statut</button>
                </form>

                <form method="POST" action="index.php?controller=admin&amp;action=updateAlertPriority" class="admin_alert_form_stack">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                    <input type="hidden" name="alert_id" value="<?= (int) ($alert['id'] ?? 0) ?>">

                    <label>
                        Priorité
                        <select name="priority" required>
                            <?php foreach ((array) ($allowedPriorities ?? []) as $allowedPriority): ?>
                                <option value="<?= htmlspecialchars((string) $allowedPriority) ?>" <?= $priorityKey === $allowedPriority ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($priorityLabels[$allowedPriority] ?? (string) $allowedPriority) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="admin_alert_btn admin_alert_btn_secondary">Mettre à jour la priorité</button>
                </form>

                <?php if (in_array($statusKey, ['resolved', 'dismissed'], true)): ?>
                    <form method="POST" action="index.php?controller=admin&amp;action=reopenAlert" class="admin_alert_form_stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                        <input type="hidden" name="alert_id" value="<?= (int) ($alert['id'] ?? 0) ?>">

                        <label>
                            Motif de réouverture
                            <textarea name="message" rows="3" placeholder="Explique pourquoi l’alerte est rouverte..."></textarea>
                        </label>

                        <button type="submit" class="admin_alert_btn admin_alert_btn_secondary">Rouvrir l’alerte</button>
                    </form>
                <?php endif; ?>
            </article>

            <article class="admin_alert_show_card admin_alert_show_side_card">
                <div class="section_heading compact">
                    <h3>Note interne</h3>
                    <p>Ajoute une note admin à l’historique.</p>
                </div>

                <form method="POST" action="index.php?controller=admin&amp;action=addAlertNote" class="admin_alert_form_stack">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                    <input type="hidden" name="alert_id" value="<?= (int) ($alert['id'] ?? 0) ?>">

                    <label>
                        Message
                        <textarea name="message" rows="5" required placeholder="Note admin, constat terrain, décision prise..."></textarea>
                    </label>

                    <button type="submit" class="admin_alert_btn admin_alert_btn_primary">Ajouter la note</button>
                </form>
            </article>
        </aside>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
