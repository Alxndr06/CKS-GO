<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Log.php';

class AdminController extends Controller
{
    private function logAdminAction(string $action, string $details = ''): void
    {
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($adminId > 0) {
            Log::admin($adminId, $action, $details);
        }
    }

    public function dashboard(): void
    {
        checkRole('admin');

        $stats = [
            'sum_of_notes' => User::getSumOfNotes(),
            'inactive_users' => User::getInactiveCount(),
            'top_debtors' => User::getTopDebtors(5),
            'recent_orders' => Order::getRecentOrders(6),
            'order_stats' => Order::getAdminStats()
        ];

        self::render('admin/dashboard', $stats);
    }

    public function serverSettings(): void
    {
        checkRole('admin');
        self::render('admin/server_settings');
    }

    public function showAllUsers(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;
        $users = User::searchAll($q);

        self::render('admin/user/show_all_users', [
            'users' => $users,
            'csrf_token' => $csrf_token,
            'q' => $q
        ]);
    }

    public function showUser(): void
    {
        checkRole('admin');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $user = User::findByID($id);
        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/user/show_user', [
            'user' => $user,
            'csrf_token' => $csrf_token
        ]);
    }

    public function addUser(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();

        self::render('admin/user/add_user', [
            'csrf_token' => $csrf_token
        ]);
    }

    public function createUser(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=addUser');
            exit;
        }

        checkCsrfToken();

        $username = preg_replace('/\s+/', '', trim($_POST['username'] ?? ''));
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $unit = trim($_POST['unit'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $note = isset($_POST['note']) ? (float)$_POST['note'] : 0;
        $passwordRaw = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        if ($username === '' || $lastname === '' || $firstname === '' || $email === '' || $passwordRaw === '') {
            $errors[] = "Merci de remplir tous les champs obligatoires.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse e-mail invalide.";
        }

        if (!User::checkUnicity('email', $email)) {
            $errors[] = "Cet email est déjà utilisé.";
        }

        if (!User::checkUnicity('username', $username)) {
            $errors[] = "Ce pseudo est déjà utilisé.";
        }

        $allowedUnits = ['mineurs', 'vif', 'syndicat'];
        if (!in_array($unit, $allowedUnits, true)) {
            $errors[] = "Service invalide.";
        }

        $allowedRoles = ['user', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = "Rôle invalide.";
        }

        if ($note < 0) {
            $errors[] = "La note ne peut pas être négative.";
        }

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode('<br>', $errors);
            header('Location: index.php?controller=admin&action=addUser');
            exit;
        }

        try {
            $userId = User::createByAdmin([
                'username' => $username,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'unit' => $unit,
                'password_hash' => password_hash($passwordRaw, PASSWORD_DEFAULT),
                'role' => $role,
                'note' => $note,
                'is_active' => $isActive,
                'activation_token' => bin2hex(random_bytes(32))
            ]);

            $this->logAdminAction('admin_user_create', 'Utilisateur #' . $userId . ' créé');

            $_SESSION['success_message'] = "Utilisateur créé avec succès.";
            header('Location: index.php?controller=admin&action=showUser&id=' . $userId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_user_create_failed', 'Échec création utilisateur / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=admin&action=addUser');
            exit;
        }
    }

    public function editUser(): void
    {
        checkRole('admin');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $user = User::findByID($id);
        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/user/edit_user', [
            'user' => $user,
            'csrf_token' => $csrf_token
        ]);
    }

    public function updateUser(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username = preg_replace('/\s+/', '', trim($_POST['username'] ?? ''));
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $unit = trim($_POST['unit'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $note = isset($_POST['note']) ? (float)$_POST['note'] : 0;
        $passwordRaw = trim($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $errors = [];

        if ($username === '' || $lastname === '' || $firstname === '' || $email === '') {
            $errors[] = "Merci de remplir tous les champs obligatoires.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse e-mail invalide.";
        }

        if (!User::checkUnicityForUpdate('email', $email, $id)) {
            $errors[] = "Cet email est déjà utilisé.";
        }

        if (!User::checkUnicityForUpdate('username', $username, $id)) {
            $errors[] = "Ce pseudo est déjà utilisé.";
        }

        $allowedUnits = ['mineurs', 'vif', 'syndicat'];
        if (!in_array($unit, $allowedUnits, true)) {
            $errors[] = "Service invalide.";
        }

        $allowedRoles = ['user', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = "Rôle invalide.";
        }

        if ($note < 0) {
            $errors[] = "La note ne peut pas être négative.";
        }

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode('<br>', $errors);
            header('Location: index.php?controller=admin&action=editUser&id=' . $id);
            exit;
        }

        try {
            User::updateById($id, [
                'username' => $username,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'unit' => $unit,
                'role' => $role,
                'note' => $note,
                'is_active' => $isActive
            ]);

            if ($passwordRaw !== '') {
                User::updatePasswordById($id, password_hash($passwordRaw, PASSWORD_DEFAULT));
            }

            $this->logAdminAction('admin_user_update', 'Utilisateur #' . $id . ' mis à jour');

            $_SESSION['success_message'] = "Utilisateur modifié avec succès.";
            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_user_update_failed', 'Échec modification utilisateur #' . $id . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=admin&action=editUser&id=' . $id);
            exit;
        }
    }

    public function deleteUser(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $currentAdminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error_message'] = "ID utilisateur invalide.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        if ($id === $currentAdminId) {
            $_SESSION['error_message'] = "Tu ne peux pas supprimer ton propre compte.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        try {
            User::deleteById($id);

            $this->logAdminAction('admin_user_delete', 'Utilisateur #' . $id . ' supprimé');

            $_SESSION['success_message'] = "Utilisateur supprimé avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_user_delete_failed', 'Échec suppression utilisateur #' . $id . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAllUsers');
        exit;
    }

    public function payments(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

        $pendingOrders = Payment::getPendingOrders($filterUserId > 0 ? $filterUserId : null);
        $recentPayments = Payment::getRecentPayments(12);
        $users = User::getAll();

        $pendingTotalForUser = 0.0;
        $selectedUser = null;

        if ($filterUserId > 0) {
            $selectedUser = User::findByID($filterUserId);
            $pendingTotalForUser = $selectedUser ? (float)($selectedUser['note'] ?? 0) : 0.0;
        }

        self::render('admin/payments/index', [
            'csrf_token' => $csrf_token,
            'pendingOrders' => $pendingOrders,
            'recentPayments' => $recentPayments,
            'users' => $users,
            'filterUserId' => $filterUserId,
            'pendingTotalForUser' => $pendingTotalForUser,
            'selectedUser' => $selectedUser
        ]);
    }

    public function capturePayment(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        checkCsrfToken();

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $method = trim($_POST['method'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $providerRef = trim($_POST['provider_ref'] ?? '');
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($orderId <= 0 || $adminId <= 0 || $method === '') {
            $_SESSION['error_message'] = "Requête de paiement invalide.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        try {
            $paymentId = Payment::captureOrderPayment(
                $orderId,
                $adminId,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null
            );

            $this->logAdminAction(
                'admin_capture_payment',
                'Encaissement commande #' . $orderId . ' / paiement #' . $paymentId . ' / méthode=' . $method
            );

            $_SESSION['success_message'] = "Commande #{$orderId} encaissée avec succès. Paiement #{$paymentId} enregistré.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_capture_payment_failed',
                'Échec encaissement commande #' . $orderId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=payments');
        exit;
    }

    public function captureUserBalance(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        checkCsrfToken();

        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $method = trim($_POST['method'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $providerRef = trim($_POST['provider_ref'] ?? '');
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0 || $adminId <= 0 || $method === '') {
            $_SESSION['error_message'] = "Requête d'encaissement invalide.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        try {
            $count = Payment::captureAllPendingPaymentsForUser(
                $userId,
                $adminId,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null
            );

            $this->logAdminAction(
                'admin_capture_user_balance',
                'Encaissement total user #' . $userId . ' / commandes soldées=' . $count . ' / méthode=' . $method
            );

            $_SESSION['success_message'] = "Encaissement total réussi : {$count} commande(s) soldée(s) pour cet utilisateur.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_capture_user_balance_failed',
                'Échec encaissement total user #' . $userId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=payments&user_id=' . $userId);
        exit;
    }

    public function captureUserCustomAmount(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        checkCsrfToken();

        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $method = trim($_POST['method'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $providerRef = trim($_POST['provider_ref'] ?? '');
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0 || $adminId <= 0 || $method === '' || $amount <= 0) {
            $_SESSION['error_message'] = "Requête d'encaissement partiel invalide.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        try {
            $result = Payment::captureCustomAmountForUser(
                $userId,
                $adminId,
                $amount,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null
            );

            $this->logAdminAction(
                'admin_capture_user_custom_amount',
                'Encaissement partiel user #' . $userId . ' / montant=' . $amount . ' / paiements=' . (int)$result['payments_count']
            );

            $_SESSION['success_message'] =
                "Encaissement partiel réussi : " .
                number_format((float)$result['applied_amount'], 2, ',', ' ') .
                " € encaissés, " .
                (int)$result['payments_count'] .
                " paiement(s) créé(s), " .
                (int)$result['fully_paid_orders'] .
                " commande(s) totalement soldée(s).";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_capture_user_custom_amount_failed',
                'Échec encaissement partiel user #' . $userId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=payments&user_id=' . $userId);
        exit;
    }

    public function billUserProduct(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $users = User::getAll();
        $products = Product::getBillableProductsWithVariants();

        self::render('admin/shop/bill_user_product', [
            'csrf_token' => $csrf_token,
            'users' => $users,
            'products' => $products
        ]);
    }

    public function createUserProductCharge(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=billUserProduct');
            exit;
        }

        checkCsrfToken();

        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $variantIds = $_POST['variant_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0 || $adminId <= 0 || !is_array($variantIds) || !is_array($quantities)) {
            $_SESSION['error_message'] = "Requête de facturation invalide.";
            header('Location: index.php?controller=admin&action=billUserProduct');
            exit;
        }

        $lines = [];
        $max = max(count($variantIds), count($quantities));

        for ($i = 0; $i < $max; $i++) {
            $variantId = isset($variantIds[$i]) ? (int)$variantIds[$i] : 0;
            $quantity = isset($quantities[$i]) ? (int)$quantities[$i] : 0;

            if ($variantId > 0 && $quantity > 0) {
                $lines[] = [
                    'variant_id' => $variantId,
                    'quantity' => $quantity
                ];
            }
        }

        if (empty($lines)) {
            $_SESSION['error_message'] = "Ajoute au moins un produit valide à facturer.";
            header('Location: index.php?controller=admin&action=billUserProduct');
            exit;
        }

        try {
            $orderId = Order::createAdminMultiChargeForUser($userId, $lines, $adminId);

            $this->logAdminAction(
                'admin_create_user_product_charge',
                'Facturation multi-produit user #' . $userId . ' / commande #' . $orderId . ' / lignes=' . count($lines)
            );

            $_SESSION['success_message'] = "Facturation multi-produit réussie. Commande #{$orderId} créée.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_create_user_product_charge_failed',
                'Échec facturation multi-produit user #' . $userId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=billUserProduct');
        exit;
    }

    public function logs(): void
    {
        checkRole('admin');

        $q = isset($_GET['q']) ? trim($_GET['q']) : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 25;

        $totalLogs = Log::countAdminLogs($q);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $logs = Log::getAdminLogs($q, $perPage, $offset);

        self::render('admin/logs/index', [
            'logs' => $logs,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages
        ]);
    }
}