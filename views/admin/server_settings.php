<?php
require_once __DIR__ . '/../partials/header.php';

$settings = $settings ?? [
        'maintenance_mode' => false,
        'shop_locked' => false,
        'registration_mode' => 'open',
];
?>

    <main class="main_part admin_dashboard_page">
        <section class="admin_dashboard_intro">
            <span class="section_kicker">Administration</span>
            <h2>Paramètres de l'application</h2>
            <p>
                Configure ici le comportement global de CKS GO : maintenance, boutique et mode d’inscription.
            </p>
        </section>

        <section class="admin_dashboard_section">
            <div class="section_heading">
                <h3>Réglages système</h3>
                <p>Ces paramètres impactent l’ensemble de l’application.</p>
            </div>

            <form method="POST" action="index.php?controller=admin&action=updateServerSettings" class="admin_form_card">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                <div class="admin_settings_grid">
                    <article class="admin_setting_card">
                        <div class="admin_setting_head">
                            <h3>Mode maintenance</h3>
                            <label class="switch">
                                <input type="checkbox" name="maintenance_mode" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
                                <span class="switch_slider"></span>
                            </label>
                        </div>
                        <p>
                            Lorsque ce mode est activé, seuls les administrateurs connectés peuvent continuer à utiliser l’application.
                        </p>
                        <small class="admin_setting_safety_note">
                            Sécurité anti-blocage : le mode se coupe automatiquement après 20 minutes sans activité d’un administrateur.
                        </small>
                    </article>

                    <article class="admin_setting_card">
                        <div class="admin_setting_head">
                            <h3>Verrouillage boutique</h3>
                            <label class="switch">
                                <input type="checkbox" name="shop_locked" <?= !empty($settings['shop_locked']) ? 'checked' : '' ?>>
                                <span class="switch_slider"></span>
                            </label>
                        </div>
                        <p>
                            Empêche les utilisateurs d’accéder à la boutique, au panier et à la validation de commande.
                        </p>
                    </article>

                    <article class="admin_setting_card">
                        <div class="admin_setting_head">
                            <h3>Mode d’inscription</h3>
                        </div>

                        <div class="admin_setting_radios">
                            <label class="radio_line">
                                <input
                                        type="radio"
                                        name="registration_mode"
                                        value="open"
                                        <?= ($settings['registration_mode'] ?? 'open') === 'open' ? 'checked' : '' ?>
                                >
                                <span>Inscription ouverte</span>
                            </label>

                            <label class="radio_line">
                                <input
                                        type="radio"
                                        name="registration_mode"
                                        value="approval_required"
                                        <?= ($settings['registration_mode'] ?? 'open') === 'approval_required' ? 'checked' : '' ?>
                                >
                                <span>Validation admin requise</span>
                            </label>
                        </div>

                        <p>
                            En mode validation admin, les utilisateurs peuvent s’inscrire mais leur compte reste inactif tant qu’un administrateur ne l’active pas.
                        </p>
                    </article>
                </div>

                <div class="form_actions_inline">
                    <button type="submit">Enregistrer les paramètres</button>
                    <a class="btn_link btn_link_secondary" href="index.php?controller=admin&action=dashboard">Retour au dashboard</a>
                </div>
            </form>
        </section>

        <section class="admin_dashboard_section access_bans_section">
            <div class="section_heading">
                <span class="section_kicker">Sécurité d’accès</span>
                <h3>Bannissements e-mail et IP</h3>
                <p>Bloquez immédiatement une adresse précise. Les bannissements sont journalisés et restent réversibles.</p>
            </div>

            <div class="access_bans_layout">
                <form method="POST" action="index.php?controller=admin&action=addAccessBan" class="access_ban_form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                    <label>
                        <span>Type</span>
                        <select name="ban_type" required data-ban-type>
                            <option value="email">Adresse e-mail</option>
                            <option value="ip">Adresse IP</option>
                        </select>
                    </label>

                    <label>
                        <span>Adresse à bannir</span>
                        <input type="text" name="ban_value" required autocomplete="off" placeholder="utilisateur@exemple.fr" data-ban-value>
                    </label>

                    <label class="access_ban_reason">
                        <span>Motif interne</span>
                        <input type="text" name="reason" maxlength="255" placeholder="Ex. abus répétés, tentative frauduleuse…">
                    </label>

                    <button type="submit">Activer le bannissement</button>
                    <?php if (!empty($currentIpAddress)): ?>
                        <small>Votre IP actuelle, protégée contre l’auto-bannissement : <?= htmlspecialchars((string)$currentIpAddress) ?></small>
                    <?php endif; ?>
                </form>

                <div class="access_bans_list">
                    <?php if (empty($accessBans)): ?>
                        <div class="access_bans_empty">
                            <?= renderUiIcon('shield') ?>
                            <strong>Aucun bannissement actif</strong>
                            <span>Les accès ne comportent actuellement aucune restriction manuelle.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($accessBans as $ban): ?>
                            <?php
                            $actor = trim((string)($ban['created_by_firstname'] ?? '') . ' ' . (string)($ban['created_by_lastname'] ?? ''));
                            if ($actor === '') {
                                $actor = (string)($ban['created_by_username'] ?? 'Compte supprimé');
                            }
                            ?>
                            <article class="access_ban_row is_<?= htmlspecialchars((string)$ban['ban_type']) ?>">
                                <span class="access_ban_icon" aria-hidden="true"><?= renderUiIcon($ban['ban_type'] === 'ip' ? 'shield' : 'mail') ?></span>
                                <div>
                                    <span><?= $ban['ban_type'] === 'ip' ? 'Adresse IP' : 'Adresse e-mail' ?></span>
                                    <strong><?= htmlspecialchars((string)$ban['ban_value']) ?></strong>
                                    <small>
                                        <?= htmlspecialchars((string)($ban['reason'] ?: 'Aucun motif renseigné')) ?>
                                        · par <?= htmlspecialchars($actor) ?>
                                        · <?= date('d/m/Y à H:i', strtotime((string)$ban['created_at'])) ?>
                                    </small>
                                </div>
                                <form method="POST" action="index.php?controller=admin&action=removeAccessBan">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="ban_id" value="<?= (int)$ban['id'] ?>">
                                    <button type="submit" data-confirm-message="Retirer ce bannissement ?">Débannir</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
