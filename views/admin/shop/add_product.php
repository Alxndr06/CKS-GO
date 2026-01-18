<?php
require_once __DIR__ . '/../../partials/header.php';
?>

<main class="main_part">

    <h2>Ajouter un produit à la boutique</h2>

<form method="POST" action="" class="register_form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <label for="product_name">Nom du produit</label>
    <input type="text" name="product_name" id="product_name" placeholder="Nom">

    <label for="product_price">Prix du produit</label>
    <input type="number" name="product_price" id="product_price" placeholder="Prix">
</form>
</main>

<?php
require_once __DIR__ . '/../../partials/footer.php';
?>