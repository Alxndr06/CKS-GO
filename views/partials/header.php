<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/functions.php';

checkSession();

// Oblige à recharger le JS à chaque nouvelle version pour éviter les prvlm de cache
$script_version = filemtime(__DIR__ . '/../../public/js/script.js');
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/styles.css">
    <script src="<?= BASE_URL ?>/public/js/script.js?v=<?= $script_version ?>" defer></script>
    <title>CKS GO</title>
</head>
<body>
<div class="page_wrapper">
    <header>
        <div class="header_top">
            <button id="burger" class="toggle_menu" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <h1><a title="Accueil du site" href="index.php?controller=home&action=index">CKS GO</a></h1>

            <div class="auth_button">
                <?php if(isUserLoggedIn()): ?>
                    <a class="btn_logout" href="index.php?controller=user&action=logout">Déconnexion</a>
                <?php else: ?>
                    <a class="btn_login" href="index.php?controller=user&action=login">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>

        <nav class="navbar hide" id="main_navbar">
        <!-- NAV BAR -->
            <ul>
                <li><a href="index.php?controller=home&action=index">Accueil</a></li>
                <li><a href="index.php?controller=shop&action=index">Boutique</a></li>
                <?php if(isUserLoggedIn()): ?>
                <li><a href="index.php?controller=user&action=dashboard">Tableau de bord</a></li>
                <?php endif; ?>
                <?php if(isAdmin()): ?>
                <li class="admin_dashboard_link"><a href="index.php?controller=admin&action=dashboard">Administration</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?= displayErrorOrSuccessMessage(); ?>
    </header>