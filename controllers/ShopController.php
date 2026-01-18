<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../helpers/functions.php';

class ShopController extends Controller
{
    public function index(): void
    {
        // filtres GET
        $categorySlug = isset($_GET['cat']) ? trim($_GET['cat']) : null;
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;

        $cats = Category::allActive();
        $products = Product::search($categorySlug, $q);

        // variants par produit (goûts ect)
        $productVariants = [];
        foreach ($products as $p) {
            $productVariants[$p['id']] = Product::variants((int)$p['id']);
        }

        self::render('shop/index', compact('cats', 'products', 'productVariants', 'categorySlug', 'q'));
    }

    public function addToCart(): void
    {
        checkSession();
        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour ajouter au panier.";
            header('Location: index.php?controller=user&action=login'); exit;
        }

        $userId    = $_SESSION['user']['id'] ?? null;
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $quantity  = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

        if (!$userId || !$productId || !$variantId) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=shop&action=index'); exit;
        }

        try {
            Cart::addItem($userId, $productId, $variantId, $quantity); // s’appuie sur stock variante
            $_SESSION['success_message'] = "Produit ajouté au panier.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=index'); exit;
    }

    public function manageShop() : void {
        checkRole('admin');
        self::render('admin/shop/index');
    }

    public function addProductToShop() : void {
        checkRole('admin');
        self::render('admin/shop/add_product');
    }
}
