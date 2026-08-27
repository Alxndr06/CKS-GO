<?php
require_once __DIR__ . '/../../partials/header.php';
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Boutique</span>
            <h2>Incidents de stock</h2>
            <p>
                Déclare les pertes et vols, retire automatiquement les quantités concernées du stock
                et garde une trace claire des mouvements exceptionnels.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Navigation</span>
                <h3>Accès rapide</h3>
                <p>Revenir facilement à la gestion générale de la boutique.</p>
            </div>

            <div class="admin_management_grid admin_management_grid_compact">
                <a class="dashboard_action_card" href="index.php?controller=shop&action=manageShop">
                    <span class="dashboard_action_icon" aria-hidden="true"><?= renderUiIcon('back') ?></span>
                    <div>
                        <h3>Retour boutique</h3>
                        <p>Revenir à la gestion principale du catalogue.</p>
                    </div>
                </a>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Déclaration</span>
                <h3>Nouvel incident</h3>
                <p>Enregistre une perte ou un vol et mets à jour le stock immédiatement.</p>
            </div>

            <div class="inventory_issue_layout">
                <article class="admin_panel_card inventory_issue_form_card">
                    <h4>Déclarer une sortie de stock</h4>

                    <form
                        method="post"
                        action="index.php?controller=shop&action=declareInventoryIssue"
                        class="inventory_issue_form"
                        data-confirm-message="Confirmer cette déclaration de sortie de stock ?"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                        <label for="inventory_issue_reason">Type</label>
                        <select name="reason" id="inventory_issue_reason" required>
                            <option value="loss">Perte</option>
                            <option value="theft">Vol</option>
                        </select>

                        <label for="inventory_issue_variant">Variante concernée</label>
                        <select name="variant_id" id="inventory_issue_variant" required>
                            <option value="">Sélectionner une variante</option>
                            <?php foreach ($products as $product): ?>
                                <?php if (empty($product['variants'])): ?>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <optgroup label="<?= htmlspecialchars($product['name']) ?>">
                                    <?php foreach ($product['variants'] as $variant): ?>
                                        <option value="<?= (int)$variant['id'] ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                            — <?= htmlspecialchars($variant['display_name']) ?>
                                            — stock actuel : <?= (int)$variant['stock_quantity'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>

                        <label for="inventory_issue_quantity">Quantité</label>
                        <input
                            type="number"
                            name="quantity"
                            id="inventory_issue_quantity"
                            min="1"
                            step="1"
                            required
                        >

                        <label for="inventory_issue_note">Commentaire</label>
                        <textarea
                            name="note"
                            id="inventory_issue_note"
                            placeholder="Exemple : casse au stockage, article manquant à l’inventaire, produit volé, etc."
                        ></textarea>

                        <button type="submit">Enregistrer la déclaration</button>
                    </form>
                </article>

                <div class="inventory_issue_stats">
                    <article class="dashboard_stat_card inventory_issue_stat_card">
                        <span class="dashboard_stat_label">7 jours</span>
                        <strong class="dashboard_stat_value"><?= (int)($stats7['total_events'] ?? 0) ?></strong>
                        <p>
                            <?= (int)($stats7['total_loss_qty'] ?? 0) ?> perte(s)
                            · <?= (int)($stats7['total_theft_qty'] ?? 0) ?> vol(s)
                        </p>
                    </article>

                    <article class="dashboard_stat_card inventory_issue_stat_card">
                        <span class="dashboard_stat_label">30 jours</span>
                        <strong class="dashboard_stat_value"><?= (int)($stats30['total_events'] ?? 0) ?></strong>
                        <p>
                            <?= (int)($stats30['total_loss_qty'] ?? 0) ?> perte(s)
                            · <?= (int)($stats30['total_theft_qty'] ?? 0) ?> vol(s)
                        </p>
                    </article>

                    <article class="dashboard_stat_card inventory_issue_stat_card inventory_issue_stat_card_warning">
                        <span class="dashboard_stat_label">Valeur estimée</span>
                        <strong class="dashboard_stat_value">
                            <?= number_format((float)($stats30['estimated_amount'] ?? 0), 2, ',', ' ') ?> €
                        </strong>
                        <p>Estimation sur les 30 derniers jours.</p>
                    </article>

                    <article class="dashboard_stat_card inventory_issue_stat_card">
                        <span class="dashboard_stat_label">Variante la plus touchée</span>
                        <?php if (!empty($stats30['top_variants'][0])): ?>
                            <strong class="dashboard_stat_value"><?= (int)$stats30['top_variants'][0]['total_qty'] ?></strong>
                            <p>
                                <?= htmlspecialchars($stats30['top_variants'][0]['product_name']) ?>
                                — <?= htmlspecialchars(!empty($stats30['top_variants'][0]['flavor']) ? $stats30['top_variants'][0]['flavor'] : $stats30['top_variants'][0]['variant_name']) ?>
                            </p>
                        <?php else: ?>
                            <strong class="dashboard_stat_value">0</strong>
                            <p>Aucun incident enregistré récemment.</p>
                        <?php endif; ?>
                    </article>
                </div>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <span class="section_kicker">Historique</span>
                <h3>Dernières déclarations</h3>
                <p>Suivi rapide des pertes et vols enregistrés récemment.</p>
            </div>

            <?php if (empty($recentInventoryIssues)): ?>
                <div class="empty_state">
                    <h3>Aucune déclaration</h3>
                    <p>Aucune perte ou aucun vol n’a encore été enregistré.</p>
                </div>
            <?php else: ?>
                <div class="inventory_issue_history">
                    <?php foreach ($recentInventoryIssues as $issue): ?>
                        <article class="inventory_issue_history_card">
                            <div class="inventory_issue_history_head">
                                <div>
                                    <h4>
                                        <?= htmlspecialchars($issue['product_name']) ?>
                                        — <?= htmlspecialchars(!empty($issue['flavor']) ? $issue['flavor'] : $issue['variant_name']) ?>
                                    </h4>
                                    <p>
                                        Quantité sortie :
                                        <strong><?= abs((int)$issue['qty']) ?></strong>
                                        · Valeur estimée :
                                        <strong><?= number_format(abs((int)$issue['qty']) * (float)$issue['price'], 2, ',', ' ') ?> €</strong>
                                    </p>
                                </div>

                                <span class="inventory_issue_badge <?= $issue['reason'] === 'theft' ? 'is_theft' : 'is_loss' ?>">
                                <?= $issue['reason'] === 'theft' ? 'Vol' : 'Perte' ?>
                            </span>
                            </div>

                            <div class="inventory_issue_history_meta">
                                <p>Variante #<?= (int)$issue['variant_id'] ?></p>
                                <p><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$issue['created_at']))) ?></p>
                            </div>

                            <?php if (!empty($issue['meta'])): ?>
                                <p class="inventory_issue_history_note"><?= htmlspecialchars($issue['meta']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
