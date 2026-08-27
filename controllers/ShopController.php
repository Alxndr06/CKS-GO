<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/Setting.php';

class ShopController extends Controller
{
    private function resolveProductVisibilityFromRequest(): string
    {
        if (isset($_POST['staff_only'])) {
            return 'admin_only';
        }

        return isset($_POST['visible_to_guests']) ? 'public' : 'authenticated';
    }

    private function logAdminAction(string $action, string $details = ''): void
    {
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($adminId > 0) {
            Log::admin($adminId, $action, $details);
        }
    }

    private function uploadProductImage(array $file, ?string $currentImage = null): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return (string)$currentImage;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("L'upload de l'image a échoué.");
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException("Fichier uploadé invalide.");
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException("L'image est trop lourde (max 5 Mo).");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException("Format d'image non autorisé. Utilise JPG, PNG, WEBP ou GIF.");
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false || (string)($imageInfo['mime'] ?? '') !== $mime) {
            throw new RuntimeException("Le contenu du fichier n'est pas une image valide.");
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0 || $width * $height > 20000000) {
            throw new RuntimeException("Les dimensions de l'image sont invalides ou trop grandes.");
        }

        $uploadDir = __DIR__ . '/../public/img/products';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException("Impossible de créer le dossier d'upload.");
        }

        $filename = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException("Impossible d'enregistrer l'image sur le serveur.");
        }

        if (!empty($currentImage)) {
            $oldPath = __DIR__ . '/../public/img/' . ltrim($currentImage, '/');

            $resolvedOldPath = realpath($oldPath);
            $resolvedUploadDir = realpath($uploadDir);
            $normalizedUploadDir = $resolvedUploadDir !== false
                ? rtrim(str_replace('\\', '/', $resolvedUploadDir), '/') . '/'
                : '';
            $normalizedOldPath = $resolvedOldPath !== false
                ? str_replace('\\', '/', $resolvedOldPath)
                : '';

            if (
                $resolvedOldPath !== false
                && is_file($resolvedOldPath)
                && $normalizedUploadDir !== ''
                && str_starts_with($normalizedOldPath, $normalizedUploadDir)
            ) {
                @unlink($oldPath);
            }
        }

        return 'products/' . $filename;
    }

    private function isAjaxRequest(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strtolower((string)$requestedWith) === 'xmlhttprequest'
            || str_contains((string)$accept, 'application/json');
    }

    private function sendJson(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function requirePostRequest(string $redirectUrl, bool $isAjax = false): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            return;
        }

        if ($isAjax) {
            $this->sendJson([
                'success' => false,
                'message' => 'Méthode non autorisée.',
            ], 405);
        }

        $_SESSION['error_message'] = 'Méthode non autorisée.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    private function validateCsrfOrFail(string $redirectUrl, bool $isAjax = false): void
    {
        checkSession();

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

        $isValid = $csrfToken !== ''
            && $sessionToken !== ''
            && hash_equals($sessionToken, $csrfToken);

        if ($isValid) {
            return;
        }

        if ($isAjax) {
            $this->sendJson([
                'success' => false,
                'message' => 'Le token CSRF est invalide.',
            ], 403);
        }

        $_SESSION['error_message'] = 'Le token CSRF est invalide.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    private function getUserOrderReportWindowHours(): int
    {
        return 10;
    }

    private function canStillReportOrderIssue(?string $createdAt): bool
    {
        $createdAt = trim((string)$createdAt);

        if ($createdAt === '') {
            return false;
        }

        try {
            $createdAtDate = new DateTimeImmutable($createdAt);
        } catch (Throwable) {
            return false;
        }

        $deadline = $createdAtDate->modify('+' . $this->getUserOrderReportWindowHours() . ' hours');
        $now = new DateTimeImmutable('now');

        return $now <= $deadline;
    }

    private function getCartSummaryPayload(int $userId): array
    {
        $cart = Cart::getDetailedCart($userId);
        $cartItems = (array)($cart['items'] ?? []);

        $items = array_values(array_map(
            static function (array $item): array {
                return [
                    'cart_item_id' => (int)($item['cart_item_id'] ?? 0),
                    'product_name' => (string)($item['product_name'] ?? ''),
                    'product_image' => resolvePublicImageFilename($item['product_image'] ?? null),
                    'display_variant' => (string)($item['display_variant'] ?? 'Variante'),
                    'quantity' => (int)($item['quantity'] ?? 0),
                    'line_total' => (float)($item['line_total'] ?? 0),
                    'line_total_formatted' => number_format((float)($item['line_total'] ?? 0), 2, ',', ' ') . ' €',
                ];
            },
            $cartItems
        ));

        return [
            'item_count' => (int)($cart['item_count'] ?? 0),
            'subtotal' => (float)($cart['subtotal'] ?? 0),
            'subtotal_formatted' => number_format((float)($cart['subtotal'] ?? 0), 2, ',', ' ') . ' €',
            'cart_url' => 'index.php?controller=shop&action=cart',
            'items' => $items,
            'total_lines' => count($cartItems),
        ];
    }

    public function index(): void
    {
        ensureShopIsAvailable();

        $categorySlug = isset($_GET['cat']) ? trim($_GET['cat']) : null;
        $q = isset($_GET['q']) ? normalizeSearchQuery($_GET['q']) : null;
        $shopAudience = isStaff() ? 'staff' : (isUserLoggedIn() ? 'authenticated' : 'guest');

        $cats = Category::allActive();
        $products = Product::search($categorySlug, $q, $shopAudience);

        $productIds = array_map(
            static fn(array $product): int => (int)$product['id'],
            $products
        );

        $productVariants = Product::variantsByProductIds($productIds);
        $quickCart = [
            'item_count' => 0,
            'subtotal' => 0.0,
        ];

        if (isUserLoggedIn()) {
            try {
                $quickCart = Cart::getDetailedCart((int)($_SESSION['user']['id'] ?? 0));
            } catch (Throwable) {
                $quickCart = [
                    'item_count' => 0,
                    'subtotal' => 0.0,
                ];
            }
        }

        self::render('shop/index', compact(
            'cats',
            'products',
            'productVariants',
            'categorySlug',
            'q',
            'quickCart'
        ));
    }

    public function addToCart(): void
    {
        checkSession();
        ensureShopIsAvailable();

        $isAjax = $this->isAjaxRequest();

        $this->requirePostRequest('index.php?controller=shop&action=index', $isAjax);
        $this->validateCsrfOrFail('index.php?controller=shop&action=index', $isAjax);

        if (!isUserLoggedIn()) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => "Connecte-toi pour ajouter au panier.",
                    'redirect_url' => 'index.php?controller=user&action=login',
                ], 401);
            }

            $_SESSION['error_message'] = "Connecte-toi pour ajouter au panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => "Votre compte est verrouillé. L'accès à la boutique est désactivé.",
                    'redirect_url' => 'index.php?controller=user&action=dashboard',
                ], 423);
            }

            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

        if (!$userId || !$productId || !$variantId) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => 'Requête invalide.',
                ], 400);
            }

            $_SESSION['error_message'] = 'Requête invalide.';
            header('Location: index.php?controller=shop&action=index');
            exit;
        }

        try {
            Cart::addItem($userId, $productId, $variantId, $quantity);

            if ($isAjax) {
                $this->sendJson([
                    'success' => true,
                    'message' => 'Produit ajouté au panier.',
                    'cart' => $this->getCartSummaryPayload($userId),
                ]);
            }

            $_SESSION['success_message'] = 'Produit ajouté au panier.';
        } catch (Throwable $e) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=index');
        exit;
    }

    public function cart(): void
    {
        checkSession();
        ensureShopIsAvailable();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour accéder au panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            $cart = Cart::getDetailedCart($userId);
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('shop/cart', [
            'cart' => $cart,
            'csrf_token' => $csrf_token
        ]);
    }

    public function updateCartItem(): void
    {
        checkSession();
        ensureShopIsAvailable();

        $this->requirePostRequest('index.php?controller=shop&action=cart');
        $this->validateCsrfOrFail('index.php?controller=shop&action=cart');

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
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
        ensureShopIsAvailable();

        $isAjax = $this->isAjaxRequest();

        $this->requirePostRequest('index.php?controller=shop&action=cart', $isAjax);
        $this->validateCsrfOrFail('index.php?controller=shop&action=cart', $isAjax);

        if (!isUserLoggedIn()) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => "Connecte-toi pour modifier le panier.",
                    'redirect_url' => 'index.php?controller=user&action=login',
                ], 401);
            }

            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => "Votre compte est verrouillé. L'accès à la boutique est désactivé.",
                    'redirect_url' => 'index.php?controller=user&action=dashboard',
                ], 423);
            }

            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;

        if (!$userId || !$cartItemId) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => 'Requête invalide.',
                ], 400);
            }

            $_SESSION['error_message'] = 'Requête invalide.';
            header('Location: index.php?controller=shop&action=cart');
            exit;
        }

        try {
            Cart::removeItem($userId, $cartItemId);

            if ($isAjax) {
                $this->sendJson([
                    'success' => true,
                    'message' => 'Produit retiré du panier.',
                    'cart' => $this->getCartSummaryPayload($userId),
                ]);
            }

            $_SESSION['success_message'] = 'Produit retiré du panier.';
        } catch (Throwable $e) {
            if ($isAjax) {
                $this->sendJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=cart');
        exit;
    }

    public function clearCart(): void
    {
        checkSession();
        ensureShopIsAvailable();

        $this->requirePostRequest('index.php?controller=shop&action=cart');
        $this->validateCsrfOrFail('index.php?controller=shop&action=cart');

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour modifier le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Cart::clear($userId);
            $_SESSION['success_message'] = "Panier vidé.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=cart');
        exit;
    }

    public function checkout(): void
    {
        checkSession();
        ensureShopIsAvailable();

        $this->requirePostRequest('index.php?controller=shop&action=cart');
        $this->validateCsrfOrFail('index.php?controller=shop&action=cart');

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour valider le panier.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
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

        $csrf_token = getCsrfToken();

        self::render('shop/order_success', [
            'order' => $order,
            'csrf_token' => $csrf_token
        ]);
    }


    public function reportAlert(): void
    {
        checkSession();
        ensureShopIsAvailable();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=index');
            exit;
        }

        checkCsrfToken();

        if (!isUserLoggedIn()) {
            $_SESSION['error_message'] = "Connecte-toi pour envoyer un signalement.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (isCurrentUserLocked()) {
            $_SESSION['error_message'] = "Votre compte est verrouillé. L'accès à la boutique est désactivé.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $type = trim((string)($_POST['type'] ?? 'manual_check_required'));
        $priority = trim((string)($_POST['priority'] ?? 'medium'));
        $sourceContext = trim((string)($_POST['source_context'] ?? 'shop_product'));
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $orderItemId = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
        $submittedOrderItemIds = $_POST['order_item_ids'] ?? [];
        if (!is_array($submittedOrderItemIds)) {
            $submittedOrderItemIds = [$submittedOrderItemIds];
        }
        $orderItemIds = array_values(array_unique(array_filter(
            array_map('intval', $submittedOrderItemIds),
            static fn(int $id): bool => $id > 0
        )));
        $allProducts = isset($_POST['all_products']) && (string)$_POST['all_products'] === '1';
        $message = trim((string)($_POST['message'] ?? ''));
        $redirect = sanitizeInternalRedirect(
            (string)($_POST['redirect'] ?? ''),
            'index.php?controller=shop&action=index'
        );

        if ($orderId > 0) {
            $order = Order::getOrderSummary($orderId, $userId);

            if (!$order) {
                $_SESSION['error_message'] = "Commande introuvable pour ce signalement.";
                header('Location: ' . $redirect);
                exit;
            }

            if (!$this->canStillReportOrderIssue((string)($order['created_at'] ?? ''))) {
                $_SESSION['error_message'] = 'Le délai de signalement de cette commande est expiré (10 heures maximum après sa création).';
                header('Location: ' . $redirect);
                exit;
            }
        }

        try {
            Alert::createUserReport([
                'type' => $type,
                'priority' => $priority,
                'source_context' => $sourceContext,
                'product_id' => $productId > 0 ? $productId : null,
                'variant_id' => $variantId > 0 ? $variantId : null,
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_item_id' => $orderItemId > 0 ? $orderItemId : null,
                'order_item_ids' => $orderItemIds,
                'all_products' => $allProducts,
                'reported_by_user_id' => $userId,
                'message' => $message,
            ]);

            $_SESSION['success_message'] = "Merci, ton signalement a bien été transmis à l'administration.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: ' . $redirect);
        exit;
    }

    public function manageShop(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();

        self::render('admin/shop/index', [
            'csrf_token' => $csrf_token
        ]);
    }

    public function allProducts(): void
    {
        checkRole('admin');

        $q = isset($_GET['q']) ? normalizeSearchQuery($_GET['q']) : null;
        $requestedArchiveState = (string)($_GET['archive'] ?? 'active');
        $archiveState = in_array($requestedArchiveState, ['active', 'archived', 'all'], true)
            ? $requestedArchiveState
            : 'active';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 12;

        $result = Product::getAdminCatalogPaginated($q, $page, $perPage, $archiveState);
        $csrf_token = getCsrfToken();

        self::render('admin/shop/all_products', [
            'products' => $result['products'],
            'q' => $q,
            'archiveState' => $archiveState,
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'total' => $result['total'],
            'totalPages' => $result['total_pages'],
            'csrf_token' => $csrf_token
        ]);
    }

    public function inventory(): void
    {
        checkRole('admin');

        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $requestedState = (string)($_GET['state'] ?? 'all');
        $state = in_array($requestedState, ['all', 'available', 'low', 'out', 'inactive'], true)
            ? $requestedState
            : 'all';
        $dashboard = Product::getInventoryDashboard($q, $state);

        self::render('admin/shop/inventory', [
            'items' => $dashboard['items'],
            'stats' => $dashboard['stats'],
            'movements' => $dashboard['movements'],
            'q' => $q,
            'state' => $state,
            'csrf_token' => getCsrfToken(),
        ]);
    }

    public function inventoryIssues(): void
    {
        checkRole('admin');

        $products = Product::getAdminCatalog();
        $csrf_token = getCsrfToken();
        $stats7 = Product::getInventoryIssueStats(7);
        $stats30 = Product::getInventoryIssueStats(30);
        $recentInventoryIssues = Product::getRecentInventoryIssues(10);

        self::render('admin/shop/inventory_issues', [
            'products' => $products,
            'csrf_token' => $csrf_token,
            'stats7' => $stats7,
            'stats30' => $stats30,
            'recentInventoryIssues' => $recentInventoryIssues
        ]);
    }

    public function declareInventoryIssue(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=inventoryIssues');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $reason = trim((string)($_POST['reason'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        try {
            Product::declareInventoryIssue($variantId, $quantity, $reason, $note, $adminId);

            $this->logAdminAction(
                'admin_inventory_issue_declared',
                sprintf(
                    'Déclaration %s / variante #%d / quantité %d',
                    $reason === 'theft' ? 'vol' : 'perte',
                    $variantId,
                    $quantity
                )
            );

            $_SESSION['success_message'] = $reason === 'theft'
                ? "Vol déclaré et stock mis à jour."
                : "Perte déclarée et stock mis à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_inventory_issue_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=inventoryIssues');
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

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $newStock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;

        try {
            Product::updateVariantStock($variantId, $newStock, $adminId);
            $this->logAdminAction(
                'admin_variant_stock_update',
                'Mise à jour stock variante #' . $variantId . ' => ' . $newStock
            );

            $_SESSION['success_message'] = "Stock mis à jour avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_variant_stock_update_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        if ($productId > 0) {
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        }

        header('Location: index.php?controller=shop&action=manageShop');
        exit;
    }

    public function adjustVariantStock(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=inventory');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $mode = trim((string)($_POST['mode'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? -1);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        try {
            $movement = Product::adjustVariantStock(
                $variantId,
                $mode,
                $quantity,
                $reason,
                $note,
                $adminId
            );

            $_SESSION['success_message'] = sprintf(
                'Stock mis à jour : %d → %d.',
                $movement['stock_before'],
                $movement['stock_after']
            );
        } catch (Throwable $e) {
            $this->logAdminAction('admin_inventory_adjustment_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        $returnQuery = http_build_query([
            'controller' => 'shop',
            'action' => 'inventory',
            'q' => trim((string)($_POST['return_q'] ?? '')),
            'state' => trim((string)($_POST['return_state'] ?? 'all')),
        ]);
        header('Location: index.php?' . $returnQuery);
        exit;
    }

    public function categories(): void
    {
        checkRole('admin');

        $q = isset($_GET['q']) ? normalizeSearchQuery($_GET['q']) : null;
        $csrf_token = getCsrfToken();
        $categories = Category::getAll($q);

        self::render('admin/shop/categories', [
            'categories' => $categories,
            'q' => $q,
            'csrf_token' => $csrf_token
        ]);
    }

    public function storeCategory(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            Category::create([
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $sortOrder,
                'is_active' => $isActive
            ], $adminId);

            $this->logAdminAction('admin_category_create', 'Création catégorie : ' . $name);

            $_SESSION['success_message'] = "Catégorie créée avec succès.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_category_create_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }
    }

    public function updateCategory(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

        try {
            Category::update($categoryId, [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'sort_order' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ], $adminId);

            $this->logAdminAction('admin_category_update', 'Mise à jour catégorie #' . $categoryId);

            $_SESSION['success_message'] = "Catégorie mise à jour.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_category_update_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }
    }

    public function toggleCategory(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

        try {
            Category::toggleStatus($categoryId, $adminId);

            $this->logAdminAction('admin_category_toggle', 'Changement statut catégorie #' . $categoryId);

            $_SESSION['success_message'] = "Statut de la catégorie mis à jour.";
            header('Location: index.php?controller=shop&action=categories');
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_category_toggle_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=categories');
            exit;
        }
    }

    public function addProduct(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $categories = Category::allActive();

        self::render('admin/shop/add_product', [
            'csrf_token' => $csrf_token,
            'categories' => $categories
        ]);
    }

    public function storeProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $visibility = $this->resolveProductVisibilityFromRequest();

        $variantName = trim($_POST['variant_name'] ?? '');
        $variantFlavor = trim($_POST['variant_flavor'] ?? '');
        $variantSku = trim($_POST['variant_sku'] ?? '');
        $variantPrice = isset($_POST['variant_price']) ? (float)$_POST['variant_price'] : -1;
        $variantStock = isset($_POST['variant_stock']) ? (int)$_POST['variant_stock'] : -1;
        $variantLowStockThreshold = isset($_POST['variant_low_stock_threshold'])
            ? max(0, (int)$_POST['variant_low_stock_threshold'])
            : 5;
        $variantSortOrder = isset($_POST['variant_sort_order']) ? (int)$_POST['variant_sort_order'] : 0;
        $variantIsActive = isset($_POST['variant_is_active']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['error_message'] = "Le nom du produit est obligatoire.";
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }

        if ($variantName === '') {
            $_SESSION['error_message'] = "Le nom de la variante initiale est obligatoire.";
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }

        if ($variantPrice < 0) {
            $_SESSION['error_message'] = "Le prix de la variante doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }

        if ($variantStock < 0) {
            $_SESSION['error_message'] = "Le stock initial doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }

        try {
            $imagePath = '';

            if (!empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imagePath = $this->uploadProductImage($_FILES['image']);
            }

            $productId = Product::createProduct(
                [
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'name' => $name,
                    'description' => $description,
                    'image' => $imagePath,
                    'is_active' => $isActive,
                    'visibility' => $visibility
                ],
                [
                    'name' => $variantName,
                    'flavor' => $variantFlavor,
                    'sku' => $variantSku,
                    'price' => $variantPrice,
                    'stock_quantity' => $variantStock,
                    'low_stock_threshold' => $variantLowStockThreshold,
                    'sort_order' => $variantSortOrder,
                    'is_active' => $variantIsActive,
                    'image' => $imagePath
                ],
                $adminId
            );

            $this->logAdminAction('admin_product_create', 'Produit #' . $productId . ' créé');

            $_SESSION['success_message'] = "Produit créé avec succès.";
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_create_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=addProduct');
            exit;
        }
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

    public function storeVariant(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

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

        $variantName = trim($_POST['name'] ?? '');
        $variantFlavor = trim($_POST['flavor'] ?? '');
        $variantSku = trim($_POST['sku'] ?? '');
        $variantPrice = isset($_POST['price']) ? (float)$_POST['price'] : -1;
        $variantStock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;
        $variantLowStockThreshold = isset($_POST['low_stock_threshold'])
            ? max(0, (int)$_POST['low_stock_threshold'])
            : 5;
        $variantSortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $variantIsActive = isset($_POST['is_active']) ? 1 : 0;

        if ($variantName === '') {
            $_SESSION['error_message'] = "Le nom de la variante est obligatoire.";
            header('Location: index.php?controller=shop&action=addVariant&product_id=' . $productId);
            exit;
        }

        if ($variantPrice < 0) {
            $_SESSION['error_message'] = "Le prix de la variante doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=addVariant&product_id=' . $productId);
            exit;
        }

        if ($variantStock < 0) {
            $_SESSION['error_message'] = "Le stock initial doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=addVariant&product_id=' . $productId);
            exit;
        }

        if ($variantSortOrder < 0) {
            $_SESSION['error_message'] = "L'ordre d'affichage doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=addVariant&product_id=' . $productId);
            exit;
        }

        try {
            $imagePath = trim((string)($product['image'] ?? ''));

            if (!empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imagePath = $this->uploadProductImage($_FILES['image'], $imagePath);
            }

            $variantId = Product::createVariant($productId, [
                'name' => $variantName,
                'flavor' => $variantFlavor,
                'sku' => $variantSku,
                'price' => $variantPrice,
                'stock_quantity' => $variantStock,
                'low_stock_threshold' => $variantLowStockThreshold,
                'sort_order' => $variantSortOrder,
                'is_active' => $variantIsActive,
                'image' => $imagePath
            ], $adminId);

            $this->logAdminAction(
                'admin_variant_create',
                'Variante #' . $variantId . ' créée pour produit #' . $productId
            );

            $_SESSION['success_message'] = "Variante créée avec succès.";
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_variant_create_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=addVariant&product_id=' . $productId);
            exit;
        }
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
        $categories = Category::allActive();

        self::render('admin/shop/edit_product', [
            'product' => $product,
            'csrf_token' => $csrf_token,
            'categories' => $categories
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

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $existingImage = trim($_POST['existing_image'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $visibility = $this->resolveProductVisibilityFromRequest();

        if ($productId <= 0 || $name === '') {
            $_SESSION['error_message'] = "Données produit invalides.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        try {
            $imagePath = $existingImage;

            if (!empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imagePath = $this->uploadProductImage($_FILES['image'], $existingImage);
            }

            Product::updateProduct($productId, [
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'name' => $name,
                'description' => $description,
                'image' => $imagePath,
                'previous_image' => $existingImage,
                'is_active' => $isActive,
                'visibility' => $visibility
            ], $adminId);

            $this->logAdminAction('admin_product_update', 'Produit #' . $productId . ' mis à jour');

            $_SESSION['success_message'] = "Produit mis à jour avec succès.";
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_product_update_failed',
                'Échec mise à jour produit #' . $productId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=editProduct&id=' . $productId);
            exit;
        }
    }

    public function disableProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        try {
            Product::setProductActiveState($productId, false, $adminId);
            $this->logAdminAction('admin_product_disabled', 'Produit #' . $productId . ' désactivé');
            $_SESSION['success_message'] = "Produit désactivé.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_disable_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    public function enableProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        try {
            Product::setProductActiveState($productId, true, $adminId);
            $this->logAdminAction('admin_product_enabled', 'Produit #' . $productId . ' réactivé');
            $_SESSION['success_message'] = "Produit réactivé.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_enable_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
        exit;
    }

    public function deleteProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        try {
            Product::deleteProduct($productId, $adminId);
            $this->logAdminAction('admin_product_archived', 'Produit #' . $productId . ' archivé');
            $_SESSION['success_message'] = "Produit archivé. Son historique est conservé.";
            header('Location: index.php?controller=shop&action=allProducts');
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_product_archive_failed', $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        }
    }

    public function restoreProduct(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=allProducts&archive=archived');
            exit;
        }

        checkCsrfToken();
        $productId = (int)($_POST['product_id'] ?? 0);
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Product::restoreProduct($productId, $adminId);
            $_SESSION['success_message'] = "Produit restauré dans le catalogue.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=shop&action=allProducts&archive=archived');
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

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if ($variantId <= 0 || $productId <= 0) {
            $_SESSION['error_message'] = "Variante introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        $existingImage = trim($_POST['existing_image'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        if ($sortOrder < 0) {
            $_SESSION['error_message'] = "L'ordre d'affichage doit être supérieur ou égal à 0.";
            header('Location: index.php?controller=shop&action=editVariant&id=' . $variantId);
            exit;
        }

        try {
            $imagePath = $existingImage;

            if (!empty($_FILES['image']) && is_array($_FILES['image'])) {
                $imagePath = $this->uploadProductImage($_FILES['image'], $existingImage);
            }

            Product::updateVariant($variantId, [
                'name' => trim($_POST['name'] ?? ''),
                'flavor' => trim($_POST['flavor'] ?? ''),
                'sku' => trim($_POST['sku'] ?? ''),
                'price' => isset($_POST['price']) ? (float)$_POST['price'] : 0,
                'low_stock_threshold' => isset($_POST['low_stock_threshold'])
                    ? max(0, (int)$_POST['low_stock_threshold'])
                    : 5,
                'sort_order' => $sortOrder,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'image' => $imagePath
            ], $adminId);

            $this->logAdminAction(
                'admin_variant_update',
                'Variante #' . $variantId . ' mise à jour'
            );

            $_SESSION['success_message'] = "Variante mise à jour avec succès.";
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_variant_update_failed',
                'Échec mise à jour variante #' . $variantId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=shop&action=editVariant&id=' . $variantId);
            exit;
        }
    }

    public function deleteVariant(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if ($variantId <= 0) {
            $_SESSION['error_message'] = "Variante introuvable.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        if ($productId <= 0) {
            $variant = Product::getAdminVariantById($variantId);
            $productId = (int)($variant['product_id'] ?? 0);
        }

        try {
            Product::deleteVariant($variantId, $adminId);

            $this->logAdminAction(
                'admin_variant_archived',
                'Variante #' . $variantId . ' archivée'
            );

            $_SESSION['success_message'] = "Variante archivée. Son historique est conservé.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_variant_archive_failed',
                'Échec archivage variante #' . $variantId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        if ($productId > 0) {
            header('Location: index.php?controller=shop&action=showAdminProduct&id=' . $productId);
            exit;
        }

        header('Location: index.php?controller=shop&action=manageShop');
        exit;
    }

    public function restoreVariant(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=shop&action=manageShop');
            exit;
        }

        checkCsrfToken();
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Product::restoreVariant($variantId, $adminId);
            $_SESSION['success_message'] = "Variante restaurée.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        $target = $productId > 0
            ? 'index.php?controller=shop&action=showAdminProduct&id=' . $productId
            : 'index.php?controller=shop&action=allProducts';
        header('Location: ' . $target);
        exit;
    }

}
