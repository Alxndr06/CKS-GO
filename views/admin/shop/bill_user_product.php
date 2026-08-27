<?php
require_once __DIR__ . '/../../partials/header.php';

$users = is_array($users ?? null) ? $users : [];
$products = is_array($products ?? null) ? $products : [];
$selectedUser = is_array($selectedUser ?? null) ? $selectedUser : [];
$preselectedUserId = (int)($preselectedUserId ?? 0);
$selectedUserId = (int)($selectedUser['id'] ?? 0);
$selectedUserName = trim(
    (string)($selectedUser['firstname'] ?? '') . ' ' . (string)($selectedUser['lastname'] ?? '')
);
$selectedUserName = $selectedUserName !== ''
    ? $selectedUserName
    : trim((string)($selectedUser['username'] ?? 'Utilisateur'));

$userCount = count($users);
$availableVariantCount = 0;

foreach ($products as $product) {
    foreach (($product['variants'] ?? []) as $variant) {
        if ((int)($variant['stock_quantity'] ?? 0) > 0) {
            $availableVariantCount++;
        }
    }
}

?>

<main class="main_part admin_dashboard_page admin_shop_form_page">
    <section class="admin_dashboard_intro">
        <span class="section_kicker">Facturation admin</span>
        <h2>Facturer <?= htmlspecialchars($selectedUserName, ENT_QUOTES, 'UTF-8') ?></h2>
        <p>
            Choisis le mode de facturation, puis renseigne les lignes nécessaires.
        </p>
    </section>

    <section class="showp_toolbar" aria-label="Navigation facturation utilisateur">
        <div class="showp_toolbar_row">
            <div class="showp_toolbar_left">
                <span class="showp_toolbar_label">Nouvelle facturation</span>
                <span class="showp_toolbar_count">
                    @<?= htmlspecialchars((string)($selectedUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="showp_toolbar_actions">
                <a
                        class="showp_action_link showp_action_link_soft"
                        href="index.php?controller=admin&action=billing"
                >
                    Retour
                </a>

                <a
                        class="showp_action_link showp_action_link_primary"
                        href="index.php?controller=admin&action=payments"
                >
                    Voir les paiements
                </a>
            </div>
        </div>
    </section>

    <section class="admin_dashboard_section">
        <form
                method="post"
                action="index.php?controller=admin&action=createUserProductCharge"
                id="multi_charge_form"
                class="shopf_form multi_charge_form"
                data-billing-composer
                data-confirm-message="Confirmer cette facturation pour cet utilisateur ?"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">

            <div class="shopf_grid">
                <?php if ($selectedUserId <= 0): ?>
                <article class="shopf_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Utilisateur</span>
                        <h3>Sélectionner un utilisateur</h3>
                        <p>Recherche un compte, puis coche-le dans la liste.</p>
                    </div>

                    <div class="billing_form_user_picker" data-billing-directory>
                        <div class="billing_directory_search_row">
                            <label class="user_directory_search billing_directory_search">
                                <span>Rechercher</span>
                                <span class="user_directory_search_control">
                                    <?= renderUiIcon('search') ?>
                                    <input
                                        type="search"
                                        placeholder="Nom, prénom, pseudo ou e-mail"
                                        autocomplete="off"
                                        data-billing-user-search
                                    >
                                </span>
                            </label>
                            <button type="button" class="billing_directory_reset" data-billing-search-reset hidden>
                                Réinitialiser
                            </button>
                        </div>

                        <p class="billing_form_user_count">
                            <span data-billing-visible-count><?= count($users) ?></span> utilisateur(s)
                        </p>

                        <div class="billing_form_user_list">
                            <?php foreach ($users as $user): ?>
                                <?php
                                $userId = (int)($user['id'] ?? 0);
                                $firstname = trim((string)($user['firstname'] ?? ''));
                                $lastname = trim((string)($user['lastname'] ?? ''));
                                $username = trim((string)($user['username'] ?? 'utilisateur'));
                                $email = trim((string)($user['email'] ?? ''));
                                $unit = trim((string)($user['unit'] ?? ''));
                                $displayName = trim($firstname . ' ' . $lastname);
                                $displayName = $displayName !== '' ? $displayName : $username;
                                $initials = mb_strtoupper(mb_substr($firstname, 0, 1) . mb_substr($lastname, 0, 1));
                                $initials = $initials !== '' ? $initials : mb_strtoupper(mb_substr($username, 0, 2));
                                $searchValue = mb_strtolower(trim(implode(' ', [
                                    $firstname,
                                    $lastname,
                                    $username,
                                    $email,
                                    $unit,
                                ])));
                                ?>
                                <label
                                    class="billing_form_user_row"
                                    data-billing-user-card
                                    data-billing-search="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <input
                                        type="radio"
                                        name="user_id"
                                        value="<?= $userId ?>"
                                        <?= $userId === $preselectedUserId ? 'checked' : '' ?>
                                        required
                                    >
                                    <span class="user_directory_avatar" aria-hidden="true">
                                        <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="billing_form_user_identity">
                                        <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small>
                                            @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($email !== ''): ?> · <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </small>
                                    </span>
                                    <span class="billing_form_user_unit">
                                        <?= htmlspecialchars($unit !== '' ? ucfirst($unit) : 'Service non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="billing_form_user_check" aria-hidden="true">✓</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="user_directory_empty billing_form_user_empty" data-billing-directory-empty <?= $users === [] ? '' : 'hidden' ?>>
                            <h3>Aucun utilisateur trouvé</h3>
                            <p>Essaie une autre recherche.</p>
                        </div>
                    </div>
                </article>
                <?php endif; ?>

                <article class="shopf_card billing_method_card">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Mode de facturation</span>
                        <h3>Comment veux-tu le facturer&nbsp;?</h3>
                    </div>

                    <fieldset class="billing_method_choices">
                        <legend class="sr_only">Choisir le mode de facturation</legend>
                        <label>
                            <input type="radio" name="billing_mode" value="custom" checked data-billing-mode-choice>
                            <span>
                                <strong>Libre</strong>
                                <small>Saisir un libellé et un montant personnalisé.</small>
                            </span>
                        </label>
                        <label>
                            <input type="radio" name="billing_mode" value="product" data-billing-mode-choice>
                            <span>
                                <strong>Par produit</strong>
                                <small>Facturer une ou plusieurs variantes du catalogue.</small>
                            </span>
                        </label>
                        <label>
                            <input type="radio" name="billing_mode" value="mixed" data-billing-mode-choice>
                            <span>
                                <strong>Les deux</strong>
                                <small>Combiner produits et montant libre.</small>
                            </span>
                        </label>
                    </fieldset>
                </article>

                <article class="shopf_card" data-billing-panel="product" hidden>
                    <div class="shopf_card_head">
                        <span class="section_kicker">Facturation</span>
                        <h3>Produits à facturer <small>(facultatif)</small></h3>
                        <p>Ajoute une ou plusieurs lignes du catalogue si nécessaire.</p>
                    </div>

                    <div id="charge_lines_wrapper" class="charge_lines_wrapper">
                        <div class="charge_line" data-charge-line>
                            <div class="charge_line_fields">
                                <div class="form_group shopf_field">
                                    <label>Produit / variante</label>
                                    <select name="variant_id[]">
                                        <option value="">Sélectionner une variante</option>
                                        <?php foreach ($products as $product): ?>
                                            <?php foreach (($product['variants'] ?? []) as $variant): ?>
                                                <option
                                                        value="<?= (int)($variant['id'] ?? 0) ?>"
                                                        data-price="<?= htmlspecialchars(number_format((float)($variant['price'] ?? 0), 2, '.', '')) ?>"
                                                        <?= ((int)($variant['stock_quantity'] ?? 0) <= 0) ? 'disabled' : '' ?>
                                                >
                                                    <?= htmlspecialchars((string)($product['name'] ?? 'Produit')) ?>
                                                    —
                                                    <?= htmlspecialchars((string)(!empty($variant['flavor']) ? $variant['flavor'] : ($variant['name'] ?? 'Variante'))) ?>
                                                    —
                                                    <?= number_format((float)($variant['price'] ?? 0), 2, ',', ' ') ?> €
                                                    —
                                                    Stock : <?= (int)($variant['stock_quantity'] ?? 0) ?>
                                                    <?= ((int)($variant['stock_quantity'] ?? 0) <= 0) ? ' (Rupture)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form_group shopf_field">
                                    <label>Quantité</label>
                                    <input type="number" name="quantity[]" min="1" value="1">
                                </div>
                            </div>

                            <div class="charge_line_actions">
                                <button
                                        type="button"
                                        class="showp_btn showp_btn_danger remove_charge_line_btn"
                                        data-remove-charge-line
                                >
                                    Retirer cette ligne
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="add_charge_line_btn"
                        class="showp_btn showp_btn_soft"
                    >
                        Ajouter un autre produit
                    </button>
                </article>

                <article class="shopf_card custom_charge_card" data-billing-panel="custom">
                    <div class="shopf_card_head">
                        <span class="section_kicker">Montant libre</span>
                        <h3>Lignes personnalisées <small>(facultatif)</small></h3>
                        <p>
                            Saisis un libellé clair, visible sur la commande et la facture, puis le montant à facturer.
                        </p>
                    </div>

                    <div id="custom_charge_lines_wrapper" class="charge_lines_wrapper">
                        <div class="charge_line custom_charge_line" data-custom-charge-line>
                            <div class="charge_line_fields custom_charge_line_fields">
                                <div class="form_group shopf_field">
                                    <label>Libellé</label>
                                    <input
                                            type="text"
                                            name="custom_label[]"
                                            maxlength="150"
                                            placeholder="Ex. Participation événement, frais exceptionnels…"
                                            data-custom-charge-label
                                        >
                                </div>

                                <div class="form_group shopf_field">
                                    <label>Montant</label>
                                    <div class="custom_charge_amount_input">
                                        <input
                                            type="number"
                                            name="custom_amount[]"
                                            min="0.01"
                                            max="99999999.99"
                                            step="0.01"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            data-custom-charge-amount
                                        >
                                        <span aria-hidden="true">€</span>
                                    </div>
                                </div>
                            </div>

                            <div class="charge_line_actions">
                                <button
                                    type="button"
                                    class="showp_btn showp_btn_danger remove_charge_line_btn"
                                    data-remove-custom-charge-line
                                >
                                    Retirer cette ligne
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="add_custom_charge_line_btn"
                        class="showp_btn showp_btn_soft custom_charge_add_btn"
                    >
                        Ajouter un autre montant libre
                    </button>
                </article>
            </div>

            <div class="custom_charge_summary" aria-live="polite">
                <span>Total estimé</span>
                <strong data-charge-estimated-total>0,00 €</strong>
                <small>Le total définitif est recalculé et validé côté serveur.</small>
            </div>

            <div class="shopf_actions">
                <a
                        class="showp_btn showp_btn_light"
                        href="<?= $selectedUser ? 'index.php?controller=admin&action=payments&user_id=' . (int)$selectedUser['id'] : 'index.php?controller=admin&action=payments' ?>"
                >
                    Voir les paiements
                </a>

                <button type="submit" class="showp_btn showp_btn_primary">
                    Facturer l’utilisateur
                </button>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
