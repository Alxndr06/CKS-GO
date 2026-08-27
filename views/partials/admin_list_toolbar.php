<?php
$toolbarConfig = $toolbarConfig ?? [];

$toolbarTitle = trim((string) ($toolbarConfig['title'] ?? 'Recherche / filtres'));
$toolbarCountLabel = trim((string) ($toolbarConfig['count_label'] ?? ''));
$toolbarAction = trim((string) ($toolbarConfig['action'] ?? 'index.php'));
$toolbarMethod = strtolower(trim((string) ($toolbarConfig['method'] ?? 'get'))) === 'post' ? 'post' : 'get';
$toolbarSearchOpen = !empty($toolbarConfig['search_open']);
$toolbarSubmitLabel = trim((string) ($toolbarConfig['submit_label'] ?? 'Rechercher'));
$toolbarFields = is_array($toolbarConfig['fields'] ?? null) ? $toolbarConfig['fields'] : [];

$toolbarBackHref = trim((string) ($toolbarConfig['back_href'] ?? '#'));
$toolbarBackLabel = trim((string) ($toolbarConfig['back_label'] ?? 'Retour'));
$toolbarAddHref = trim((string) ($toolbarConfig['add_href'] ?? ''));
$toolbarAddLabel = trim((string) ($toolbarConfig['add_label'] ?? 'Ajouter'));

$toolbarWrapperClass = trim((string) ($toolbarConfig['wrapper_class'] ?? 'admin_toolbar_pro admin_list_toolbar'));
$toolbarSearchPanelClass = trim((string) ($toolbarConfig['search_panel_class'] ?? 'admin_search_panel_pro admin_list_toolbar_search_panel'));
$toolbarToggleClass = trim((string) ($toolbarConfig['toggle_class'] ?? 'admin_search_toggle_pro admin_list_toolbar_toggle'));
$toolbarTitleClass = trim((string) ($toolbarConfig['title_class'] ?? 'admin_search_title_pro admin_list_toolbar_title'));
$toolbarCountClass = trim((string) ($toolbarConfig['count_class'] ?? 'admin_count_badge_pro admin_list_toolbar_count'));
$toolbarFormClass = trim((string) ($toolbarConfig['form_class'] ?? 'admin_search_form_pro admin_list_toolbar_form'));
$toolbarRowClass = trim((string) ($toolbarConfig['row_class'] ?? 'admin_search_row_pro admin_list_toolbar_row'));
$toolbarActionsClass = trim((string) ($toolbarConfig['actions_class'] ?? 'admin_toolbar_actions_pro admin_list_toolbar_actions'));
$toolbarBackClass = trim((string) ($toolbarConfig['back_class'] ?? 'admin_toolbar_link_pro admin_toolbar_link_soft_pro admin_list_toolbar_link admin_list_toolbar_link_soft'));
$toolbarAddClass = trim((string) ($toolbarConfig['add_class'] ?? 'admin_toolbar_link_pro admin_toolbar_link_primary_pro admin_list_toolbar_link admin_list_toolbar_link_primary'));
$toolbarSubmitClass = trim((string) ($toolbarConfig['submit_class'] ?? 'apl_btn apl_btn_light btn_link btn_link_secondary btn_link_small admin_list_toolbar_submit'));

$renderToolbarAttributes = static function (array $attributes): string {
    $parts = [];

    foreach ($attributes as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        $escapedName = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');

        if ($value === true) {
            $parts[] = $escapedName;
            continue;
        }

        $parts[] = sprintf(
            '%s="%s"',
            $escapedName,
            htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
        );
    }

    return $parts ? ' ' . implode(' ', $parts) : '';
};
?>

<div class="<?= htmlspecialchars($toolbarWrapperClass, ENT_QUOTES, 'UTF-8') ?>">
    <details class="<?= htmlspecialchars($toolbarSearchPanelClass, ENT_QUOTES, 'UTF-8') ?>"<?= $toolbarSearchOpen ? ' open' : '' ?>>
        <summary class="<?= htmlspecialchars($toolbarToggleClass, ENT_QUOTES, 'UTF-8') ?>">
            <span class="<?= htmlspecialchars($toolbarTitleClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($toolbarTitle, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <?php if ($toolbarCountLabel !== ''): ?>
                <span class="admin_list_toolbar_toggle_meta">
                    <span class="<?= htmlspecialchars($toolbarCountClass, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($toolbarCountLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>
            <?php endif; ?>
        </summary>

        <form method="<?= htmlspecialchars($toolbarMethod, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars($toolbarAction, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($toolbarFormClass, ENT_QUOTES, 'UTF-8') ?>" <?= $toolbarMethod === 'get' ? 'data-auto-filter-form' : '' ?>>
            <?php foreach ($toolbarFields as $field): ?>
                <?php if (($field['type'] ?? 'text') === 'hidden'): ?>
                    <input
                        type="hidden"
                        name="<?= htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        value="<?= htmlspecialchars((string) ($field['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="<?= htmlspecialchars($toolbarRowClass, ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($toolbarFields as $field): ?>
                    <?php
                    $fieldType = (string) ($field['type'] ?? 'text');
                    if ($fieldType === 'hidden') {
                        continue;
                    }

                    $fieldName = (string) ($field['name'] ?? '');
                    $fieldValue = (string) ($field['value'] ?? '');
                    $fieldClass = trim('admin_list_toolbar_control ' . (string) ($field['class'] ?? ''));
                    $fieldAttributes = is_array($field['attributes'] ?? null) ? $field['attributes'] : [];
                    if ($toolbarMethod === 'get') {
                        $fieldAttributes['data-auto-filter'] = true;
                    }
                    ?>

                    <?php if ($fieldType === 'select'): ?>
                        <select
                            name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                            class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $renderToolbarAttributes($fieldAttributes) ?>
                        >
                            <?php foreach ((array) ($field['options'] ?? []) as $option): ?>
                                <?php
                                $optionValue = '';
                                $optionLabel = '';

                                if (is_array($option)) {
                                    $optionValue = (string) ($option['value'] ?? '');
                                    $optionLabel = (string) ($option['label'] ?? $optionValue);
                                } else {
                                    $optionValue = (string) $option;
                                    $optionLabel = (string) $option;
                                }
                                ?>
                                <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>"<?= $optionValue === $fieldValue ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input
                            type="<?= htmlspecialchars($fieldType, ENT_QUOTES, 'UTF-8') ?>"
                            name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                            value="<?= htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') ?>"
                            class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="<?= htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            <?= $renderToolbarAttributes($fieldAttributes) ?>
                        >
                    <?php endif; ?>
                <?php endforeach; ?>

                <button type="submit" class="<?= htmlspecialchars($toolbarSubmitClass, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($toolbarSubmitLabel, ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </details>

    <div class="<?= htmlspecialchars($toolbarActionsClass, ENT_QUOTES, 'UTF-8') ?>">
        <a class="<?= htmlspecialchars($toolbarBackClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($toolbarBackHref, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($toolbarBackLabel, ENT_QUOTES, 'UTF-8') ?>
        </a>

        <?php if ($toolbarAddHref !== ''): ?>
            <a class="<?= htmlspecialchars($toolbarAddClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($toolbarAddHref, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($toolbarAddLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endif; ?>
    </div>
</div>
