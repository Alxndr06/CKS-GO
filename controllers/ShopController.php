<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/../helpers/functions.php';

class ShopController extends Controller
{
    private function logAdminAction(string $action, string $details = ''): void
    {
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($adminId > 0) {
            Log::admin($adminId, $action, $details);
        }
    }

    public function index(): void
    {
        $categorySlug = isset($_GET['cat']) ? trim($_GET['cat']) : null;
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;

        $cats = Category::allActive();
        $products = Product::search($categorySlug, $q);

        $productIds = array_map(
            static fn(array $product): int => (int)$product['id'],
            $products
        );

        $productVariants = Product::variantsByProductIds($productIds);

        self::render('shop/index', compact(
            'cats',
            'products',
            'productVariants',
            'categorySlug',
            'q'
        ));
    }

    public function addToCart(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour ajouter au panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

        if (!$userId || !$productId || !$variantId) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=shop&action=index');
            exit;
        }

        try {
            Cart::addItem($userId, $productId, $variantId, $quantity);
            $_SESSION['success_message'] = "Produit ajouté au panier.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=index');
        exit;
    }

    public function cart(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour accéder au panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $cart = Cart::getDetailedCart($userId);

        self::render('shop/cart', compact('cart'));
    }

    public function updateCartItem(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if (!$userId || !$cartItemId) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=shop&action=cart');
            exit;
        }

        try {
            Cart::updateItemQuantity($userId, $cartItemId, $quantity);
            $_SESSION['success_message'] = "Panier mis à jour.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=cart');
        exit;
    }

    public function removeCartItem(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;

        if (!$userId || !$cartItemId) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=shop&action=cart');
            exit;
        }

        Cart::removeItem($userId, $cartItemId);
        $_SESSION['success_message'] = "Produit retiré du panier.";

        header('Location: index.php?controller=shop&action=cart');
        exit;
    }

    public function clearCart(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        Cart::clear($userId);
        $_SESSION['success_message'] = "Panier vidé.";

        header('Location: index.php?controller=shop&action=cart');
        exit;
    }

    public function checkout(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour valider le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            $orderId = Order::createFromCart($userId);
            $_SESSION['success_message'] = "Commande #{$orderId} créée. La note utilisateur a été mise à jour.";
            header('Location: index.php?controller=shop&action=orderSuccess&id=' . $orderId);
            exit;
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=cart');
            exit;
        }
    }

    public function orderSuccess(): void
    {
        checkSession();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour accéder à cette page.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$orderId) {
            $_SESSION['error_message'] = "Commande introuvable.";
            header('Location: index.php?controller=shop&action=index');
            exit;
        }

        $order = Order::getOrderSummary($orderId, $userId);

        if (!$order) {
            $_SESSION['error_message'] = "Commande introuvable.";
            header('Location: index.php?controller=shop&action=index');
            exit;
        }

        self::render('shop/order_success', compact('order'));
    }

    public function manageShop(): void
    {
        checkRole('admin');

        $q = isset($_GET['q']) ? trim($_GET['q']) : null;
        $products = Product::getAdminCatalog($q);

        self::render('admin/shop/index', [
            'products' => $products,
            'q' => $q
        ]);
    }

    public function showAdminProduct(): void
    {
        checkRole('admin');

        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($productId <= 0) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $csrf_token = getCsrfToken();
        $product = Product::getAdminProductById($productId);

        if (!$product) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        self::render('admin/shop/show_product', [
            'product' => $product,
            'csrf_token' => $csrf_token
        ]);
    }

    public function editProduct(): void
    {
        checkRole('admin');

        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($productId <= 0) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $product = Product::getAdminProductById($productId);
        if (!$product) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/shop/edit_product', [
            'product' => $product,
            'csrf_token' => $csrf_token
        ]);
    }

    public function updateProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $existingImage = trim($_POST['existing_image'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($productId <= 0 || $name === '' || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête de modification invalide.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        try {
            $imageName = $this->handleProductImageUpload($existingImage);

            Product::updateProduct($productId, [
                'name' => $name,
                'description' => $description,
                'image' => $imageName,
                'is_active' => $isActive
            ], $adminId);

            $this->logAdminAction('admin_product_update', 'Produit #' . $productId . ' mis à jour');

            $_SESSION['success_message'] = "Produit mis à jour avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_update_failed', 'Échec modification produit #' . $productId . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    public function editVariant(): void
    {
        checkRole('admin');

        $variantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($variantId <= 0) {
            $_SESSION['error_message'] = "Variante introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $variant = Product::getAdminVariantById($variantId);
        if (!$variant) {
            $_SESSION['error_message'] = "Variante introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/shop/edit_variant', [
            'variant' => $variant,
            'csrf_token' => $csrf_token
        ]);
    }

    public function updateVariant(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : -1;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($variantId <= 0 || $productId <= 0 || $name === '' || $price < 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête de modification invalide.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        try {
            Product::updateVariant($variantId, [
                'name' => $name,
                'price' => $price,
                'is_active' => $isActive
            ], $adminId);

            $this->logAdminAction('admin_variant_update', 'Variante #' . $variantId . ' mise à jour');

            $_SESSION['success_message'] = "Variante mise à jour avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_variant_update_failed', 'Échec modification variante #' . $variantId . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    public function updateVariantStock(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $newStock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($productId <= 0 || $variantId <= 0 || $newStock < 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête d'ajustement invalide.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        try {
            Product::updateVariantStock($variantId, $newStock, $adminId);

            $this->logAdminAction(
                'admin_variant_stock_update',
                'Stock variante #' . $variantId . ' mis à ' . $newStock . ' / produit #' . $productId
            );

            $_SESSION['success_message'] = "Stock mis à jour avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_variant_stock_update_failed',
                'Échec maj stock variante #' . $variantId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    public function addProductToShop(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $categories = Category::allActive();

        self::render('admin/shop/add_product', [
            'csrf_token' => $csrf_token,
            'categories' => $categories
        ]);
    }

    public function createProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=addProductToShop');
            exit;
        }

        checkCsrfToken();

        $name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['product_description'] ?? '');
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== ''
            ? (int)$_POST['category_id']
            : null;
        $productIsActive = isset($_POST['product_is_active']) ? 1 : 0;

        $variantName = trim($_POST['variant_name'] ?? '');
        $variantFlavor = trim($_POST['variant_flavor'] ?? '');
        $variantPrice = isset($_POST['variant_price']) ? (float)$_POST['variant_price'] : -1;
        $variantStock = isset($_POST['variant_stock']) ? (int)$_POST['variant_stock'] : -1;
        $variantIsActive = isset($_POST['variant_is_active']) ? 1 : 0;

        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($name === '' || $variantName === '' || $variantPrice < 0 || $variantStock < 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Merci de remplir correctement les champs obligatoires.";
            header('Location: index.php?controller=shop&action=addProductToShop');
            exit;
        }

        try {
            $imageName = $this->handleProductImageUpload('');

            $productId = Product::createProductWithVariant(
                [
                    'name' => $name,
                    'description' => $description,
                    'category_id' => $categoryId,
                    'image' => $imageName,
                    'is_active' => $productIsActive
                ],
                [
                    'name' => $variantName,
                    'flavor' => $variantFlavor,
                    'price' => $variantPrice,
                    'stock_quantity' => $variantStock,
                    'is_active' => $variantIsActive
                ],
                $adminId
            );

            $this->logAdminAction('admin_product_create', 'Produit #' . $productId . ' créé');

            $_SESSION['success_message'] = "Produit créé avec succès.";
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_create_failed', 'Échec création produit / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=addProductToShop');
            exit;
        }
    }

    public function addVariant(): void
    {
        checkRole('admin');

        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($productId <= 0) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $product = Product::getAdminProductById($productId);
        if (!$product) {
            $_SESSION['error_message'] = "Produit introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/shop/add_variant', [
            'product' => $product,
            'csrf_token' => $csrf_token
        ]);
    }

    public function createVariant(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $flavor = trim($_POST['flavor'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : -1;
        $stockQuantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($productId <= 0 || $name === '' || $price < 0 || $stockQuantity < 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Merci de remplir correctement les champs obligatoires.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        try {
            $variantId = Product::createVariant($productId, [
                'name' => $name,
                'flavor' => $flavor,
                'price' => $price,
                'stock_quantity' => $stockQuantity,
                'is_active' => $isActive
            ], $adminId);

            $this->logAdminAction('admin_variant_create', 'Variante #' . $variantId . ' créée pour produit #' . $productId);

            $_SESSION['success_message'] = "Variante ajoutée avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_variant_create_failed', 'Échec création variante pour produit #' . $productId . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    private function handleProductImageUpload(string $existingImage = ''): string
    {
        if (
            !isset($_FILES['image_file']) ||
            !is_array($_FILES['image_file']) ||
            ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return $existingImage;
        }

        $file = $_FILES['image_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("L'upload de l'image a échoué.");
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException("Format d'image non autorisé. Utilise jpg, jpeg, png, webp ou gif.");
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ];

        if ($mimeType === false || !in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException("Le fichier envoyé n'est pas une image valide.");
        }

        $uploadDir = __DIR__ . '/../public/img/';
        if (!is_dir($uploadDir)) {
            throw new RuntimeException("Le dossier d'upload est introuvable.");
        }

        $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBaseName = trim($safeBaseName, '_');
        if ($safeBaseName === '') {
            $safeBaseName = 'product';
        }

        $newFileName = $safeBaseName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException("Impossible d'enregistrer l'image uploadée.");
        }

        return $newFileName;
    }
}