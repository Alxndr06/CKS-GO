<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/functions.php';

checkSession();

// Oblige à recharger le JS à chaque nouvelle version
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
    <script src="<?= BASE_URL ?>/public/js/script.js?=<?= $script_version ?>" defer></script>
    <title>CKS GO</title>
</head>
<body>
<div class="page_wrapper">
    <header>
        <button id="burger" class="toggle_menu" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1><a title="Accueil du site" href="index.php?controller=home&action=index">CKS GO</a></h1>
        <nav class="navbar">
            <!-- NAV BAR -->
            <ul>
                <li><a href="index.php?controller=home&action=index">Accueil</a></li>
                <li><a href="#">Boutique</a></li>
                <li><a href="index.php?controller=user&action=login">Se connecter</a></li>
            </ul>
        </nav>
    </header>