<?php
require_once __DIR__ . '/../../partials/header.php';

$fullName = trim((string) (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')));
$displayName = $fullName !== '' ? $fullName : (string) ($user['username'] ?? 'Utilisateur');

$initials = strtoupper(
        substr((string) ($user['firstname'] ?? 'U'), 0, 1)
        . substr((string) ($user['lastname'] ?? ''), 0, 1)
);

if (trim($initials) === '') {
    $initials = strtoupper(substr((string) ($user['username'] ?? 'U'), 0, 2));
}

$permissionMatrix = $permissionMatrix ?? getUserPermissionMatrix($user);
$rolePermissionMap = [];
foreach (getAssignableRoles() as $roleValue => $roleDefinition) {
    $permissions = getRolePermissions($roleValue);
    $hasAllPermissions = in_array('*', $permissions, true);
    foreach (getPermissionDefinitions() as $permission => $definition) {
        $rolePermissionMap[$roleValue][$permission] = $hasAllPermissions || in_array($permission, $permissions, true);
    }
}
?>

    <main class="main_part admin_dashboard_page admin_shop_form_page user_management_form_page">
        <section class="admin_dashboard_intro admin_dashboard_intro_compact">
            <span class="section_kicker">Utilisateurs</span>
            <h2>Modifier l’utilisateur</h2>
            <p>
                Mets à jour les informations du compte dans une vue admin compacte
                et uniforme avec le reste de l’application.
            </p>
        </section>

        <section class="showp_toolbar" aria-label="Navigation modification utilisateur">
            <div class="showp_toolbar_row">
                <div class="showp_toolbar_left">
                    <span class="showp_toolbar_label">Compte en cours d’édition</span>
                    <span class="showp_toolbar_count">#<?= (int) ($user['id'] ?? 0) ?></span>
                </div>

                <div class="showp_toolbar_actions">
                    <a
                            class="showp_action_link showp_action_link_soft"
                            href="index.php?controller=admin&action=showUser&id=<?= (int) ($user['id'] ?? 0) ?>"
                    >
                        Retour
                    </a>

                    <a
                            class="showp_action_link showp_action_link_primary"
                            href="index.php?controller=admin&action=showAllUsers"
                    >
                        Liste
                    </a>
                </div>
            </div>
        </section>

        <section class="admin_dashboard_section">
            <article class="showp_summary_card shopf_summary_card aushow_summary_card">
                <div class="showp_summary_media aushow_summary_media">
                    <div class="aushow_avatar">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                </div>

                <div class="showp_summary_body">
                    <div class="showp_summary_top">
                        <div class="showp_summary_title_wrap">
                            <h3><?= htmlspecialchars($displayName) ?></h3>

                            <div class="showp_summary_badges">
                            <span class="showp_badge">
                                <?= htmlspecialchars(getRoleLabel($user['role'] ?? 'user', true), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                                <?php if ((int) ($user['is_active'] ?? 0) === 1): ?>
                                    <span class="showp_badge showp_badge_success">Actif</span>
                                <?php else: ?>
                                    <span class="showp_badge showp_badge_warning">Inactif</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <p class="aushow_username">@<?= htmlspecialchars((string) ($user['username'] ?? '-')) ?></p>

                    <p class="shopf_summary_meta">
                        <span><strong>Email :</strong> <?= htmlspecialchars((string) ($user['email'] ?? '-')) ?></span>
                        <span><strong>Service :</strong> <?= htmlspecialchars((string) ($user['unit'] ?? '-')) ?></span>
                        <span><strong>ID :</strong> #<?= (int) ($user['id'] ?? 0) ?></span>
                    </p>
                </div>
            </article>
        </section>

        <section class="admin_dashboard_section">
            <form
                    method="post"
                    action="index.php?controller=admin&action=updateUser"
                    class="shopf_form"
                    data-confirm-message="Confirmer la modification de cet utilisateur ?"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0) ?>">

                <div class="shopf_grid">
                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Identité</span>
                            <h3>Compte</h3>
                            <p>Pseudo, email et nom affiché.</p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="username">Pseudo *</label>
                            <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    value="<?= htmlspecialchars((string) ($user['username'] ?? '')) ?>"
                                    required
                            >
                        </div>

                        <div class="shopf_subgrid">
                            <div class="form_group shopf_field">
                                <label for="lastname">Nom *</label>
                                <input
                                        type="text"
                                        name="lastname"
                                        id="lastname"
                                        value="<?= htmlspecialchars((string) ($user['lastname'] ?? '')) ?>"
                                        required
                                >
                            </div>

                            <div class="form_group shopf_field">
                                <label for="firstname">Prénom *</label>
                                <input
                                        type="text"
                                        name="firstname"
                                        id="firstname"
                                        value="<?= htmlspecialchars((string) ($user['firstname'] ?? '')) ?>"
                                        required
                                >
                            </div>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="email">Email *</label>
                            <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>"
                                    required
                            >
                        </div>
                    </article>

                    <article class="shopf_card">
                        <div class="shopf_card_head">
                            <span class="section_kicker">Profil</span>
                            <h3>Rôle et sécurité</h3>
                            <p>Service, rôle, mot de passe et état général du compte.</p>
                        </div>

                        <div class="shopf_subgrid">
                            <div class="form_group shopf_field">
                                <label for="unit">Service *</label>
                                <select name="unit" id="unit" required>
                                    <option value="mineurs" <?= (($user['unit'] ?? '') === 'mineurs') ? 'selected' : '' ?>>mineurs</option>
                                    <option value="vif" <?= (($user['unit'] ?? '') === 'vif') ? 'selected' : '' ?>>vif</option>
                                    <option value="syndicat" <?= (($user['unit'] ?? '') === 'syndicat') ? 'selected' : '' ?>>syndicat</option>
                                </select>
                            </div>

                            <div class="form_group shopf_field">
                                <label for="role">Rôle *</label>
                                <select name="role" id="role" required>
                                    <?php $selectedRole = normalizeUserRole($user['role'] ?? 'user'); ?>
                                    <?php foreach (getAssignableRoles() as $roleValue => $roleDefinition): ?>
                                        <option
                                                value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"
                                                data-role-label="<?= htmlspecialchars((string)$roleDefinition['label'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-role-description="<?= htmlspecialchars((string)$roleDefinition['description'], ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $selectedRole === $roleValue ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars((string)$roleDefinition['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="role_help_card" data-role-help>
                            <strong data-role-help-title><?= htmlspecialchars(getRoleLabel($selectedRole), ENT_QUOTES, 'UTF-8') ?></strong>
                            <p data-role-help-description><?= htmlspecialchars(getRoleDescription($selectedRole), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div class="shopf_note">
                            <p>
                                La note n’est plus modifiable manuellement ici.
                                Pour ajouter une dette, utilise la facturation admin via la boutique.
                            </p>
                        </div>

                        <div class="form_group shopf_field">
                            <label for="password">Nouveau mot de passe</label>
                            <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    minlength="15"
                                    maxlength="128"
                                    autocomplete="new-password"
                            >
                            <small class="form_field_help">Laissez ce champ vide pour conserver le mot de passe actuel.</small>
                        </div>

                        <label class="shopf_checkbox" for="user-active">
                            <input
                                    type="checkbox"
                                    id="user-active"
                                    name="is_active"
                                    value="1"
                                    <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'checked' : '' ?>
                            >
                            <span>
                            Compte actif
                            <small>Le compte restera utilisable après enregistrement.</small>
                        </span>
                        </label>
                    </article>

                    <article
                            id="permissions"
                            class="shopf_card user_permissions_editor"
                            data-permission-editor
                            data-role-permission-map="<?= htmlspecialchars(json_encode($rolePermissionMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <div class="shopf_card_head user_permissions_editor_head">
                            <span class="user_permissions_editor_icon" aria-hidden="true"><?= renderUiIcon('shield') ?></span>
                            <div>
                                <span class="section_kicker">Autorisations</span>
                                <h3>Permissions effectives</h3>
                                <p>Le rôle fournit les droits par défaut. Les exceptions ci-dessous ne modifient que ce compte.</p>
                            </div>
                        </div>

                        <div class="permission_editor_legend" aria-label="Légende">
                            <span><i class="is_inherited"></i> Héritée du rôle</span>
                            <span><i class="is_allowed"></i> Autorisée</span>
                            <span><i class="is_denied"></i> Refusée</span>
                        </div>

                        <div class="permission_editor_groups">
                            <?php
                            $currentPermissionGroup = null;
                            foreach ($permissionMatrix as $permission => $permissionData):
                                $group = (string)$permissionData['group'];
                                if ($group !== $currentPermissionGroup):
                                    if ($currentPermissionGroup !== null): ?>
                                        </div></section>
                                    <?php endif; ?>
                                    <section class="permission_editor_group">
                                        <h4><?= htmlspecialchars($group) ?></h4>
                                        <div class="permission_editor_rows">
                                    <?php $currentPermissionGroup = $group;
                                endif;
                                $canAdminister = canAdministerPermission($permission);
                                ?>
                                <div class="permission_editor_row" data-permission-row="<?= htmlspecialchars($permission) ?>">
                                    <div class="permission_editor_copy">
                                        <strong><?= htmlspecialchars((string)$permissionData['label']) ?></strong>
                                        <small><?= htmlspecialchars((string)$permissionData['description']) ?></small>
                                    </div>
                                    <span class="permission_role_state <?= $permissionData['base_allowed'] ? 'is_allowed' : 'is_denied' ?>" data-role-state>
                                        <?= $permissionData['base_allowed'] ? 'Inclus dans le rôle' : 'Non inclus' ?>
                                    </span>
                                    <select
                                            name="permission_overrides[<?= htmlspecialchars($permission) ?>]"
                                            data-permission-select
                                            data-can-administer="<?= $canAdminister ? '1' : '0' ?>"
                                            <?= !$canAdminister || normalizeUserRole($user['role'] ?? 'user') === 'admin' ? 'disabled' : '' ?>
                                            aria-label="Exception pour <?= htmlspecialchars((string)$permissionData['label']) ?>"
                                    >
                                        <option value="inherit" <?= $permissionData['override'] === 'inherit' ? 'selected' : '' ?>>Hériter du rôle</option>
                                        <option value="allow" <?= $permissionData['override'] === 'allow' ? 'selected' : '' ?>>Autoriser</option>
                                        <option value="deny" <?= $permissionData['override'] === 'deny' ? 'selected' : '' ?>>Refuser</option>
                                    </select>
                                    <span class="permission_effective_state <?= $permissionData['effective_allowed'] ? 'is_allowed' : 'is_denied' ?>" data-effective-state>
                                        <?= $permissionData['effective_allowed'] ? 'Accès actif' : 'Accès refusé' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($currentPermissionGroup !== null): ?>
                                </div></section>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="shopf_actions">
                    <a
                            class="showp_btn showp_btn_soft"
                            href="index.php?controller=admin&action=showUser&id=<?= (int) ($user['id'] ?? 0) ?>"
                    >
                        Annuler
                    </a>

                    <button type="submit" class="showp_btn showp_btn_primary">
                        Enregistrer
                    </button>
                </div>
            </form>
        </section>
    </main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
