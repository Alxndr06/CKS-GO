<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../models/Cart.php';

checkSession();

$cssVersion = filemtime(__DIR__ . '/../../public/css/styles.css');
$scriptVersion = filemtime(__DIR__ . '/../../public/js/script.js');
$pageStylesheets = array_values(array_filter(array_map(
    static fn(mixed $filename): string => basename(trim((string)$filename)),
    is_array($pageStylesheets ?? null) ? $pageStylesheets : []
)));
$pageScripts = array_values(array_filter(array_map(
    static fn(mixed $filename): string => basename(trim((string)$filename)),
    is_array($pageScripts ?? null) ? $pageScripts : []
)));
$cartCount = 0;
$csrfToken = getCsrfToken();
$currentController = strtolower((string)($_GET['controller'] ?? 'home'));
$currentAction = (string)($_GET['action'] ?? 'index');
$currentRole = getCurrentUserRole();
$roleLabel = getRoleLabel($currentRole, true);
$isStaffMember = isStaff();
$staffShopActions = array_keys([
    'manageShop' => true,
    'allProducts' => true,
    'inventory' => true,
    'inventoryIssues' => true,
    'categories' => true,
    'addProduct' => true,
    'storeProduct' => true,
    'showAdminProduct' => true,
    'addVariant' => true,
    'storeVariant' => true,
    'editProduct' => true,
    'updateProduct' => true,
    'editVariant' => true,
    'updateVariant' => true,
]);
$isStaffContext = $isStaffMember && (
    $currentController === 'admin'
    || ($currentController === 'shop' && in_array($currentAction, $staffShopActions, true))
);

$displayName = '';
$initials = 'CG';

if (isUserLoggedIn()) {
    $cartCount = Cart::getItemCount((int)($_SESSION['user']['id'] ?? 0));
    $displayName = trim((string)($_SESSION['user']['firstname'] ?? '') . ' ' . (string)($_SESSION['user']['lastname'] ?? ''));

    if ($displayName === '') {
        $displayName = (string)($_SESSION['user']['username'] ?? 'Mon compte');
    }

    $firstInitial = mb_substr((string)($_SESSION['user']['firstname'] ?? ''), 0, 1);
    $lastInitial = mb_substr((string)($_SESSION['user']['lastname'] ?? ''), 0, 1);
    $initials = mb_strtoupper($firstInitial . $lastInitial);

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($displayName, 0, 2));
    }
}

$isRouteActive = static function (string $controller, array $actions = []) use ($currentController, $currentAction): bool {
    if ($currentController !== $controller) {
        return false;
    }

    return $actions === [] || in_array($currentAction, $actions, true);
};

$staffNavigation = array_values(array_filter(
    getStaffNavigationItems(),
    static fn(array $item): bool => currentUserCan((string)$item['permission'])
));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="CKS GO - Gestion de caisse café">
    <meta name="keywords" content="CKS GO">
    <meta name="author" content="Alexander AULONG">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <?php if (!empty($page_referrer_policy ?? '')): ?>
        <meta name="referrer" content="<?= htmlspecialchars((string)$page_referrer_policy, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/styles.css?v=<?= $cssVersion ?>">
    <?php foreach ($pageStylesheets as $stylesheet): ?>
        <?php $stylesheetPath = __DIR__ . '/../../public/css/' . $stylesheet; ?>
        <?php if (is_file($stylesheetPath)): ?>
            <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/<?= rawurlencode($stylesheet) ?>?v=<?= filemtime($stylesheetPath) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <script src="<?= BASE_URL ?>/public/js/script.js?v=<?= $scriptVersion ?>" defer></script>
    <?php foreach ($pageScripts as $pageScript): ?>
        <?php $pageScriptPath = __DIR__ . '/../../public/js/' . $pageScript; ?>
        <?php if (is_file($pageScriptPath)): ?>
            <script src="<?= BASE_URL ?>/public/js/<?= rawurlencode($pageScript) ?>?v=<?= filemtime($pageScriptPath) ?>" defer></script>
        <?php endif; ?>
    <?php endforeach; ?>
    <title><?= htmlspecialchars((string)($pageTitle ?? 'CKS GO'), ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body
        class="<?= $isStaffContext ? 'staff_context' : 'public_context' ?>"
        data-base-url="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>"
>
<div class="page_wrapper">
    <header class="app_header">
        <div class="header_top app_header_bar">
            <a class="app_brand" title="Accueil CKS GO" href="index.php?controller=home&action=index">
                <span class="app_brand_mark" aria-hidden="true"><span>CG</span></span>
                <span class="app_brand_text">
                    <strong>CKS GO</strong>
                    <small>Simple. Clair. Ensemble.</small>
                </span>
            </a>

            <button
                    id="burger"
                    class="toggle_menu"
                    type="button"
                    aria-label="Ouvrir le menu de navigation"
                    aria-controls="main_navbar"
                    aria-expanded="false"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav id="main_navbar" class="navbar hide" aria-label="Navigation principale">
                <ul>
                    <li>
                        <a class="<?= $isRouteActive('home', ['index']) ? 'is_active' : '' ?>" href="index.php?controller=home&action=index" <?= $isRouteActive('home', ['index']) ? 'aria-current="page"' : '' ?>>Accueil</a>
                    </li>
                    <li>
                        <a class="<?= $isRouteActive('shop', ['index']) ? 'is_active' : '' ?>" href="index.php?controller=shop&action=index" <?= $isRouteActive('shop', ['index']) ? 'aria-current="page"' : '' ?>>Boutique</a>
                    </li>
                    <?php if (isUserLoggedIn()): ?>
                        <li>
                            <a class="<?= $isRouteActive('user') ? 'is_active' : '' ?>" href="index.php?controller=user&action=dashboard" <?= $isRouteActive('user') ? 'aria-current="page"' : '' ?>>Mon espace</a>
                        </li>
                    <?php else: ?>
                        <li class="mobile_nav_only">
                            <a href="index.php?controller=user&action=login">Se connecter</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($isStaffMember): ?>
                        <li class="admin_dashboard_link">
                            <a class="<?= $isStaffContext ? 'is_active' : '' ?>" href="index.php?controller=admin&action=dashboard" <?= $isStaffContext ? 'aria-current="page"' : '' ?>>Gestion</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="auth_button app_header_actions <?= isUserLoggedIn() ? 'is_authenticated' : 'is_guest' ?>">
                <?php if (isUserLoggedIn()): ?>
                    <a class="btn_cart app_icon_button" href="index.php?controller=shop&action=cart" aria-label="Panier, <?= (int)$cartCount ?> article<?= $cartCount > 1 ? 's' : '' ?>">
                        <span class="header_btn_icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M7 9V7a5 5 0 0 1 10 0v2M5 9h14l-1 11H6L5 9Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="cart_count_badge" <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int)$cartCount ?></span>
                    </a>

                    <a class="app_identity" href="index.php?controller=user&action=dashboard" aria-label="Ouvrir mon espace">
                        <span class="app_identity_avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="app_identity_text">
                            <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </a>

                    <form method="POST" action="index.php?controller=user&action=logout" class="auth_logout_form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn_logout app_logout_button" type="submit" aria-label="Se déconnecter" title="Se déconnecter">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="btn_login" href="index.php?controller=user&action=login">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isStaffContext): ?>
            <div class="staff_nav_shell">
                <div class="staff_nav_intro">
                    <span class="staff_nav_eyebrow">Gestion</span>
                    <strong><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <nav class="staff_nav" aria-label="Navigation de gestion">
                    <?php foreach ($staffNavigation as $item): ?>
                        <?php
                        $itemController = (string)$item['controller'];
                        $itemAction = (string)$item['action'];
                        $itemActive = $currentController === $itemController && (
                            $currentAction === $itemAction
                            || ($itemController === 'shop' && $itemAction === 'manageShop' && in_array($currentAction, $staffShopActions, true))
                        );
                        ?>
                        <a class="staff_nav_link <?= $itemActive ? 'is_active' : '' ?>" href="index.php?controller=<?= urlencode($itemController) ?>&action=<?= urlencode($itemAction) ?>" <?= $itemActive ? 'aria-current="page"' : '' ?>>
                            <span class="staff_nav_icon" aria-hidden="true"><?= renderUiIcon((string)$item['icon']) ?></span>
                            <span><?= htmlspecialchars((string)$item['short_label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endif; ?>

        <div class="app_flash_region" aria-live="polite">
            <?= displayErrorOrSuccessMessage(); ?>
        </div>
    </header>
