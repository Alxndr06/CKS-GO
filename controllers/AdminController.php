<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserPermissionOverride.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/UserBalance.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/TicketMessage.php';
require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/News.php';
require_once __DIR__ . '/../models/AccessBan.php';
require_once __DIR__ . '/../services/Mailer.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/AdminDashboardService.php';

class AdminController extends Controller
{
    private function logAdminAction(string $action, string $details = ''): void
    {
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($adminId > 0) {
            Log::admin($adminId, $action, $details);
        }
    }

    private function buildPasswordResetUrl(string $token): string
    {
        return buildAppUrl('index.php?controller=user&action=resetPassword&token=' . urlencode($token));
    }

    private function ensureUserCanBeManaged(array $user, string $redirectAction = 'showAllUsers'): void
    {
        $userId = (int)($user['id'] ?? 0);

        if (canManageUserAccount($user['role'] ?? 'user', $userId)) {
            return;
        }

        $_SESSION['error_message'] = "Vous ne pouvez pas modifier un compte de niveau égal ou supérieur au vôtre.";
        $location = 'index.php?controller=admin&action=' . $redirectAction;

        if ($redirectAction === 'showUser' && $userId > 0) {
            $location .= '&id=' . $userId;
        }

        header('Location: ' . $location);
        exit;
    }

    public function dashboard(): void
    {
        checkRole('admin');

        self::render('admin/dashboard', [
            'dashboard' => AdminDashboardService::build(),
        ]);
    }

    public function serverSettings(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $settings = Setting::getAppSettings();

        self::render('admin/server_settings', [
            'csrf_token' => $csrf_token,
            'settings' => $settings,
            'accessBans' => AccessBan::all(),
            'currentIpAddress' => getRequestIpAddress(),
        ]);
    }

    public function updateServerSettings(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=serverSettings');
            exit;
        }

        checkCsrfToken();

        $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $shopLocked = isset($_POST['shop_locked']) ? '1' : '0';
        $registrationMode = trim($_POST['registration_mode'] ?? 'open');
        $maintenanceWasEnabled = Setting::getBool('maintenance_mode', false);
        $maintenanceTimestamp = date('Y-m-d H:i:s');

        if (!in_array($registrationMode, ['open', 'approval_required'], true)) {
            $registrationMode = 'open';
        }

        try {
            Setting::setMany([
                'maintenance_mode' => $maintenanceMode,
                'maintenance_started_at' => $maintenanceMode === '1'
                    ? ($maintenanceWasEnabled ? (string)Setting::get('maintenance_started_at', $maintenanceTimestamp) : $maintenanceTimestamp)
                    : '',
                'maintenance_last_admin_activity_at' => $maintenanceMode === '1' ? $maintenanceTimestamp : '',
                'shop_locked' => $shopLocked,
                'registration_mode' => $registrationMode,
            ]);

            $this->logAdminAction(
                'admin_settings_updated',
                'maintenance_mode=' . $maintenanceMode .
                ' / shop_locked=' . $shopLocked .
                ' / registration_mode=' . $registrationMode
            );

            $_SESSION['success_message'] = "Les paramètres de l'application ont bien été mis à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_settings_update_failed', $e->getMessage());
            $_SESSION['error_message'] = "Impossible de mettre à jour les paramètres.";
        }

        header('Location: index.php?controller=admin&action=serverSettings');
        exit;
    }

    public function addAccessBan(): void
    {
        checkRole('admin');
        checkSession();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirectWithError('Méthode non autorisée.', 'admin', 'serverSettings');
            return;
        }

        checkCsrfToken();

        $type = strtolower(trim((string)($_POST['ban_type'] ?? '')));
        $value = AccessBan::normalizeValue($type, (string)($_POST['ban_value'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $currentUser = User::findByID($adminId);

        if ($type === 'ip' && hash_equals(getRequestIpAddress(), $value)) {
            redirectWithError('Vous ne pouvez pas bannir votre adresse IP actuelle.', 'admin', 'serverSettings');
            return;
        }

        if ($type === 'email' && $currentUser && hash_equals(mb_strtolower((string)$currentUser['email']), $value)) {
            redirectWithError('Vous ne pouvez pas bannir votre propre adresse e-mail.', 'admin', 'serverSettings');
            return;
        }

        try {
            $banId = AccessBan::create($type, $value, $reason, $adminId);
            $this->logAdminAction(
                'security_access_ban_created',
                sprintf('Bannissement #%d / type=%s / valeur=%s / motif=%s', $banId, $type, $value, $reason !== '' ? $reason : 'non précisé')
            );
            redirectWithSuccess('Le bannissement est maintenant actif.', 'admin', 'serverSettings');
        } catch (Throwable $exception) {
            $this->logAdminAction('security_access_ban_create_failed', $exception->getMessage());
            redirectWithError($exception->getMessage(), 'admin', 'serverSettings');
        }
    }

    public function removeAccessBan(): void
    {
        checkRole('admin');
        checkSession();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirectWithError('Méthode non autorisée.', 'admin', 'serverSettings');
            return;
        }

        checkCsrfToken();

        $banId = (int)($_POST['ban_id'] ?? 0);
        $ban = AccessBan::findById($banId);

        if (!$ban || !AccessBan::deleteById($banId)) {
            redirectWithError('Bannissement introuvable.', 'admin', 'serverSettings');
            return;
        }

        $this->logAdminAction(
            'security_access_ban_removed',
            sprintf('Bannissement #%d retiré / type=%s / valeur=%s', $banId, $ban['ban_type'], $ban['ban_value'])
        );
        redirectWithSuccess('Le bannissement a été retiré.', 'admin', 'serverSettings');
    }

    public function showAllUsers(): void
    {
        checkRole('admin');

        Payment::refreshAllOrderFinancialStatuses();

        $csrf_token = getCsrfToken();
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
        $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $unitFilter = isset($_GET['unit']) ? trim($_GET['unit']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;
        $filters = [
            'q' => $q,
            'role' => $roleFilter,
            'status' => $statusFilter,
            'unit' => $unitFilter,
        ];
        $totalUsers = User::countAdminUsers($filters);
        $totalPages = max(1, (int)ceil($totalUsers / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $users = User::searchAdminUsers($filters, $perPage, $offset);

        self::render('admin/user/all_users', [
            'users' => $users,
            'csrf_token' => $csrf_token,
            'q' => $q,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'unitFilter' => $unitFilter,
            'directoryStats' => User::getAdminDirectoryStats(),
            'page' => $page,
            'perPage' => $perPage,
            'totalUsers' => $totalUsers,
            'totalPages' => $totalPages
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
        $commerceStats = Order::getAdminUserCommerceSnapshot($id);
        $lastPayment = Payment::getLastCapturedForUser($id);

        self::render('admin/user/show_user', [
            'user' => $user,
            'csrf_token' => $csrf_token,
            'commerceStats' => $commerceStats,
            'lastPayment' => $lastPayment,
            'currentAdminId' => (int)($_SESSION['user']['id'] ?? 0),
            'permissionMatrix' => getUserPermissionMatrix($user)
        ]);
    }

    public function sendUserPasswordResetLink(): void
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

        $this->ensureUserCanBeManaged($user, 'showUser');

        $result = User::createPasswordResetTokenForUser($id, 60, 300);

        if (empty($result['issued']) || empty($result['token'])) {
            $reason = (string)($result['reason'] ?? 'unknown');

            if ($reason === 'cooldown') {
                $remainingSeconds = (int)($result['remaining_seconds'] ?? 0);
                $remainingMinutes = max(1, (int)ceil($remainingSeconds / 60));

                $_SESSION['warning_message'] = sprintf(
                    "Un lien de réinitialisation a déjà été généré récemment. Réessayez dans %d minute%s.",
                    $remainingMinutes,
                    $remainingMinutes > 1 ? 's' : ''
                );
            } else {
                $_SESSION['error_message'] = "Impossible de générer un lien de réinitialisation pour le moment.";
            }

            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        }

        $resetUrl = $this->buildPasswordResetUrl((string)$result['token']);
        $mailSent = Mailer::sendPasswordResetLink([
            'username' => (string)($user['username'] ?? ''),
            'firstname' => (string)($user['firstname'] ?? ''),
            'lastname' => (string)($user['lastname'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'reset_url' => $resetUrl,
        ]);

        if (!$mailSent) {
            User::clearPasswordResetToken($id);
            $this->logAdminAction('admin_user_password_reset_link_failed', 'Échec envoi lien reset utilisateur #' . $id);
            $_SESSION['warning_message'] = "Lien généré, mais l’e-mail de réinitialisation n’a pas pu être envoyé.";
            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        }

        $this->logAdminAction('admin_user_password_reset_link_sent', 'Lien reset envoyé pour utilisateur #' . $id);
        $_SESSION['success_message'] = "Lien de réinitialisation envoyé par e-mail.";
        header('Location: index.php?controller=admin&action=showUser&id=' . $id);
        exit;
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
        $role = normalizeUserRole($_POST['role'] ?? 'user');
        $passwordRaw = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];

        if ($username === '' || $lastname === '' || $firstname === '' || $email === '' || $passwordRaw === '') {
            $errors[] = "Merci de remplir tous les champs obligatoires.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse e-mail invalide.";
        }

        $passwordError = validatePasswordPolicy((string)$passwordRaw);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
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

        if (!canAssignRole($role)) {
            $errors[] = "Vous ne pouvez pas attribuer ce rôle.";
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
                'password_hash' => hashPassword((string)$passwordRaw),
                'role' => $role,
                'note' => 0,
                'is_active' => $isActive,
                'is_locked' => 0,
                'activation_token' => null,
                'email_verified_at' => date('Y-m-d H:i:s')
            ]);

            $this->logAdminAction('admin_user_create', 'Utilisateur #' . $userId . ' créé par admin avec e-mail déjà vérifié');

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


        $this->ensureUserCanBeManaged($user);

        $csrf_token = getCsrfToken();

        self::render('admin/user/edit_user', [
            'user' => $user,
            'csrf_token' => $csrf_token,
            'permissionMatrix' => getUserPermissionMatrix($user)
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
        $role = normalizeUserRole($_POST['role'] ?? 'user');
        $passwordRaw = (string)($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $postedPermissionOverrides = is_array($_POST['permission_overrides'] ?? null)
            ? $_POST['permission_overrides']
            : [];

        if ($id <= 0) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $existingUser = User::findByID($id);
        if (!$existingUser) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }


        $this->ensureUserCanBeManaged($existingUser);

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

        if ($passwordRaw !== '') {
            $passwordError = validatePasswordPolicy($passwordRaw);
            if ($passwordError !== null) {
                $errors[] = $passwordError;
            }
        }

        $allowedUnits = ['mineurs', 'vif', 'syndicat'];
        if (!in_array($unit, $allowedUnits, true)) {
            $errors[] = "Service invalide.";
        }

        if (!canChangeUserRole($existingUser['role'] ?? 'user', $role, $id)) {
            $errors[] = "Vous ne pouvez pas attribuer ce rôle.";
        }

        $existingPermissionOverrides = getUserPermissionOverrides($id);
        $permissionOverrides = [];
        foreach (getPermissionDefinitions() as $permission => $definition) {
            if (!canAdministerPermission($permission)) {
                $existingEffect = (string)($existingPermissionOverrides[$permission] ?? 'inherit');
                if (in_array($existingEffect, ['allow', 'deny'], true)) {
                    $permissionOverrides[$permission] = $existingEffect;
                }
                continue;
            }

            $effect = (string)($postedPermissionOverrides[$permission] ?? 'inherit');

            if (!in_array($effect, ['inherit', 'allow', 'deny'], true)) {
                $errors[] = "Valeur de permission invalide.";
                break;
            }

            if ($effect !== 'inherit') {
                $permissionOverrides[$permission] = $effect;
            }
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
                'note' => (float)($existingUser['note'] ?? 0),
                'is_active' => $isActive,
                'is_locked' => (int)($existingUser['is_locked'] ?? 0)
            ]);

            if ($passwordRaw !== '') {
                User::updatePasswordById($id, hashPassword($passwordRaw));
            }

            UserPermissionOverride::replaceForUser(
                $id,
                $role === 'admin' ? [] : $permissionOverrides,
                (int)($_SESSION['user']['id'] ?? 0)
            );

            $this->logAdminAction(
                'admin_user_update',
                'Utilisateur #' . $id . ' mis à jour / ' . count($permissionOverrides) . ' exception(s) de permission'
            );

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

    public function toggleUserLock(): void
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
            $_SESSION['error_message'] = "Tu ne peux pas verrouiller ton propre compte.";
            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        }

        $user = User::findByID($id);

        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }


        $this->ensureUserCanBeManaged($user, 'showUser');

        if (normalizeUserRole($user['role'] ?? 'user') === 'admin') {
            $_SESSION['error_message'] = "Impossible de verrouiller un administrateur.";
            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        }

        try {
            $isLocked = (int)($user['is_locked'] ?? 0) === 1;

            if ($isLocked) {
                User::unlockById($id);
                $this->logAdminAction('admin_user_unlock', 'Utilisateur #' . $id . ' déverrouillé');
                $_SESSION['success_message'] = "Utilisateur déverrouillé avec succès.";
            } else {
                User::lockById($id);
                $this->logAdminAction('admin_user_lock', 'Utilisateur #' . $id . ' verrouillé');
                $_SESSION['success_message'] = "Utilisateur verrouillé. Il ne peut plus utiliser la boutique.";
            }
        } catch (Throwable $e) {
            $this->logAdminAction('admin_user_lock_toggle_failed', 'Échec verrouillage utilisateur #' . $id . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showUser&id=' . $id);
        exit;
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

        $user = User::findByID($id);

        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $this->ensureUserCanBeManaged($user);

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

    public function pendingUsers(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 10;

        $allUsers = User::searchPending($q !== '' ? $q : null);
        $totalUsers = count($allUsers);
        $totalPages = max(1, (int)ceil($totalUsers / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $users = array_slice($allUsers, $offset, $perPage);

        self::render('admin/user/pending_users', [
            'users' => $users,
            'csrf_token' => $csrf_token,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalUsers' => $totalUsers,
            'totalPages' => $totalPages
        ]);
    }

    public function approveUser(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $_SESSION['error_message'] = "ID utilisateur invalide.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        $userBefore = User::findByID($id);

        if (!$userBefore) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        if ((int)($userBefore['is_active'] ?? 0) === 1) {
            $_SESSION['warning_message'] = "Cet utilisateur est déjà actif.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        try {
            User::activateById($id);

            $user = User::findByID($id);

            if (!$user) {
                throw new RuntimeException("Utilisateur introuvable après activation.");
            }

            $this->logAdminAction(
                'admin_user_approved',
                'Utilisateur #' . $id . ' validé'
            );

            error_log('CKS GO approveUser / email=' . ($user['email'] ?? 'NULL') . ' / firstname=' . ($user['firstname'] ?? 'NULL'));

            $mailSent = Mailer::sendAccountApproved([
                'email' => (string)($user['email'] ?? ''),
                'firstname' => (string)($user['firstname'] ?? ''),
                'lastname' => (string)($user['lastname'] ?? ''),
                'username' => (string)($user['username'] ?? ''),
                'is_active' => (int)($user['is_active'] ?? 0),
            ]);

            if (!$mailSent) {
                $this->logAdminAction(
                    'admin_user_approved_mail_failed',
                    'Validation OK mais mail non envoyé pour utilisateur #' . $id . ' / email=' . ($user['email'] ?? 'NULL')
                );
            }

            $_SESSION['success_message'] = $mailSent
                ? "Utilisateur validé avec succès. Un e-mail de confirmation a été envoyé."
                : "Utilisateur validé avec succès. Le compte est actif, mais l’e-mail n’a pas pu être envoyé.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_user_approve_failed',
                'Échec validation utilisateur #' . $id . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=pendingUsers');
        exit;
    }

    public function activateUser(): void
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
        $redirect = trim($_POST['redirect'] ?? 'showAllUsers');

        if ($id <= 0) {
            $_SESSION['error_message'] = "ID utilisateur invalide.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        $user = User::findByID($id);

        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        if ((int)($user['is_active'] ?? 0) === 1) {
            $_SESSION['warning_message'] = "Cet utilisateur est déjà actif.";

            if ($redirect === 'showUser') {
                header('Location: index.php?controller=admin&action=showUser&id=' . $id);
                exit;
            }

            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        try {
            User::activateById($id);

            $this->logAdminAction(
                'admin_user_activated_from_list',
                'Utilisateur #' . $id . ' activé depuis la liste générale'
            );

            $_SESSION['success_message'] = "Utilisateur activé avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_user_activation_failed_from_list',
                'Échec activation utilisateur #' . $id . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        if ($redirect === 'showUser') {
            header('Location: index.php?controller=admin&action=showUser&id=' . $id);
            exit;
        }

        header('Location: index.php?controller=admin&action=showAllUsers');
        exit;
    }

    public function rejectUser(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $currentAdminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error_message'] = "ID utilisateur invalide.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        if ($id === $currentAdminId) {
            $_SESSION['error_message'] = "Tu ne peux pas supprimer ton propre compte.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        $user = User::findByID($id);

        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=pendingUsers');
            exit;
        }

        try {
            $mailSent = Mailer::sendAccountRejected($user);

            if (!$mailSent) {
                $this->logAdminAction(
                    'admin_user_rejected_mail_failed',
                    'Refus OK mais mail non envoyé pour utilisateur #' . $id
                );
            }

            User::deleteById($id);

            $this->logAdminAction(
                'admin_user_rejected',
                'Utilisateur #' . $id . ' refusé/supprimé'
            );

            $_SESSION['success_message'] = $mailSent
                ? "Inscription refusée, utilisateur supprimé, et e-mail envoyé."
                : "Inscription refusée et utilisateur supprimé. L’e-mail n’a pas pu être envoyé.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_user_reject_failed',
                'Échec refus utilisateur #' . $id . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=pendingUsers');
        exit;
    }


    public function payments(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $userSearch = isset($_GET['user_search']) ? trim((string)$_GET['user_search']) : '';
        $pendingUsersOnly = isset($_GET['pending_users_only']) && $_GET['pending_users_only'] === '1';

        $pendingOrders = Payment::getPendingOrders($filterUserId > 0 ? $filterUserId : null);
        $users = Payment::getUsersForPaymentFilter($userSearch !== '' ? $userSearch : null, $pendingUsersOnly);

        $pendingTotalForUser = 0.0;
        $selectedUser = null;
        $recentPayments = [];

        if ($filterUserId > 0) {
            $selectedUser = User::findByID($filterUserId);

            if (!$selectedUser) {
                $_SESSION['error_message'] = "Utilisateur introuvable.";
                header('Location: index.php?controller=admin&action=showAllUsers');
                exit;
            }

            $selectedInList = false;
            foreach ($users as $user) {
                if ((int)($user['id'] ?? 0) === $filterUserId) {
                    $selectedInList = true;
                    break;
                }
            }

            if (!$selectedInList) {
                $fallbackUsers = Payment::getUsersForPaymentFilter(null, false);
                foreach ($fallbackUsers as $user) {
                    if ((int)($user['id'] ?? 0) === $filterUserId) {
                        array_unshift($users, $user);
                        break;
                    }
                }
            }

            $pendingTotalForUser = (float)Payment::getPendingTotalForUser($filterUserId);
            $recentPayments = Payment::getRecentPayments(12, $filterUserId);
        }

        self::render('admin/payments/index', [
            'csrf_token' => $csrf_token,
            'pendingOrders' => $pendingOrders,
            'recentPayments' => $recentPayments,
            'users' => $users,
            'filterUserId' => $filterUserId,
            'pendingTotalForUser' => $pendingTotalForUser,
            'selectedUser' => $selectedUser,
            'userSearch' => $userSearch,
            'pendingUsersOnly' => $pendingUsersOnly,
            'captureToken' => bin2hex(random_bytes(24)),
            'balanceMovements' => $filterUserId > 0 ? UserBalance::getRecentForUser($filterUserId, 8) : []
        ]);
    }

    public function captureUserPayment(): void
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
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $mode = trim((string)($_POST['payment_mode'] ?? 'orders'));
        $method = trim((string)($_POST['method'] ?? ''));
        $provider = trim((string)($_POST['provider'] ?? ''));
        $providerRef = trim((string)($_POST['provider_ref'] ?? ''));
        $idempotencyKey = trim((string)($_POST['payment_token'] ?? ''));
        $orderIds = is_array($_POST['order_ids'] ?? null) ? $_POST['order_ids'] : [];
        $redirectUrl = 'index.php?controller=admin&action=payments' . ($userId > 0 ? '&user_id=' . $userId : '');

        try {
            $freeAmountCents = null;
            if ($mode === 'free') {
                $freeAmountCents = UserBalance::decimalToCents($_POST['amount'] ?? '');
            }

            $result = PaymentService::captureForUser(
                $userId,
                $adminId,
                $mode,
                $orderIds,
                $freeAmountCents,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null,
                $idempotencyKey
            );

            $balanceAfter = (float)$result['balance_after'];
            $balanceLabel = $balanceAfter < 0
                ? 'Avoir disponible : ' . number_format(abs($balanceAfter), 2, ',', ' ') . ' €.'
                : 'Solde restant : ' . number_format($balanceAfter, 2, ',', ' ') . ' €.';

            $_SESSION['success_message'] = !empty($result['duplicate'])
                ? 'Cet encaissement avait déjà été enregistré. Aucune écriture supplémentaire n’a été créée.'
                : 'Encaissement #' . (int)$result['batch_id'] . ' enregistré : ' .
                    number_format((float)$result['applied_amount'], 2, ',', ' ') . ' €. ' . $balanceLabel;
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_capture_user_payment_failed',
                'Échec encaissement unifié utilisateur #' . $userId . ' / ' . $e->getMessage()
            );
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }



    public function showPayment(): void
    {
        checkRole('admin');

        $paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($paymentId <= 0) {
            $_SESSION['error_message'] = "Paiement introuvable.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        $payment = Payment::getAdminPaymentById($paymentId);
        if (!$payment) {
            $_SESSION['error_message'] = "Paiement introuvable.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        self::render('admin/payments/show', [
            'payment' => $payment
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
        $selectedUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $method = trim($_POST['method'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $providerRef = trim($_POST['provider_ref'] ?? '');
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $redirectUrl = 'index.php?controller=admin&action=payments' . ($selectedUserId > 0 ? '&user_id=' . $selectedUserId : '');

        if ($orderId <= 0 || $adminId <= 0 || $method === '') {
            $_SESSION['error_message'] = "Requête de paiement invalide.";
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $result = PaymentService::captureForUser(
                $selectedUserId,
                $adminId,
                'orders',
                [$orderId],
                null,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null,
                bin2hex(random_bytes(24))
            );
            $paymentId = (int)($result['payment_ids'][0] ?? 0);

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

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function captureSelectedPayments(): void
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
        $scope = trim($_POST['capture_scope'] ?? 'selected');
        $method = trim($_POST['method'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $providerRef = trim($_POST['provider_ref'] ?? '');
        $orderIds = $_POST['order_ids'] ?? [];
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0 || $adminId <= 0 || $method === '') {
            $_SESSION['error_message'] = "Requête d'encaissement multiple invalide.";
            header('Location: index.php?controller=admin&action=payments');
            exit;
        }

        $user = User::findByID($userId);
        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        try {
            if ($scope === 'all') {
                $result = PaymentService::captureForUser(
                    $userId,
                    $adminId,
                    'balance',
                    [],
                    null,
                    $method,
                    $provider !== '' ? $provider : null,
                    $providerRef !== '' ? $providerRef : null,
                    bin2hex(random_bytes(24))
                );

                $this->logAdminAction(
                    'admin_capture_all_visible_payments',
                    'Encaissement total via page paiements user #' . $userId . ' / montant=' . (float)$result['applied_amount'] . ' / paiements=' . (int)$result['payments_count']
                );

                $_SESSION['success_message'] =
                    'Le solde débiteur de cet utilisateur a été encaissé : ' .
                    number_format((float)$result['applied_amount'], 2, ',', ' ') .
                    ' € encaissés, ' .
                    (int)$result['fully_paid_orders'] .
                    ' commande(s) soldée(s)' .
                    ((float)$result['unallocated_amount'] > 0
                        ? ' et ' . number_format((float)$result['unallocated_amount'], 2, ',', ' ') . ' € imputés au solde historique.'
                        : '.');
            } else {
                if (!is_array($orderIds) || empty($orderIds)) {
                    throw new RuntimeException("Sélectionne au moins une commande à encaisser.");
                }

                $result = PaymentService::captureForUser(
                    $userId,
                    $adminId,
                    'orders',
                    $orderIds,
                    null,
                    $method,
                    $provider !== '' ? $provider : null,
                    $providerRef !== '' ? $providerRef : null,
                    bin2hex(random_bytes(24))
                );

                $this->logAdminAction(
                    'admin_capture_selected_payments',
                    'Encaissement sélection user #' . $userId . ' / commandes=' . implode(',', $result['order_ids'] ?? []) . ' / montant=' . (float)$result['applied_amount']
                );

                $_SESSION['success_message'] =
                    'Sélection encaissée : ' .
                    number_format((float)$result['applied_amount'], 2, ',', ' ') .
                    ' € sur ' .
                    (int)$result['payments_count'] .
                    ' commande(s).';
            }
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_capture_selected_payments_failed',
                'Échec encaissement multiple user #' . $userId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=payments&user_id=' . $userId);
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

        $user = User::findByID($userId);
        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        try {
            $result = PaymentService::captureForUser(
                $userId,
                $adminId,
                'balance',
                [],
                null,
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null,
                bin2hex(random_bytes(24))
            );
            $count = (int)$result['payments_count'];

            $this->logAdminAction(
                'admin_capture_user_balance',
                'Encaissement total user #' . $userId . ' / paiements créés=' . $count . ' / méthode=' . $method
            );

            $_SESSION['success_message'] =
                "Encaissement total réussi : " .
                number_format((float)$result['applied_amount'], 2, ',', ' ') .
                " € encaissés, " .
                $count .
                " paiement(s) créé(s).";
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

        $user = User::findByID($userId);
        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=admin&action=showAllUsers');
            exit;
        }

        try {
            $result = PaymentService::captureForUser(
                $userId,
                $adminId,
                'free',
                [],
                UserBalance::decimalToCents(number_format($amount, 2, '.', '')),
                $method,
                $provider !== '' ? $provider : null,
                $providerRef !== '' ? $providerRef : null,
                bin2hex(random_bytes(24))
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

    public function billing(): void
    {
        $this->renderBillingPage();
    }

    public function billUserProduct(): void
    {
        $this->renderBillingPage();
    }

    private function renderBillingPage(): void
    {
        checkRole('admin');

        $preselectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $users = array_values(array_filter(
            Payment::getUsersForPaymentFilter(),
            static fn(array $user): bool => (int)($user['is_active'] ?? 0) === 1
        ));

        if ($preselectedUserId <= 0) {
            self::render('admin/billing/index', [
                'pageTitle' => 'Facturation — CKS GO',
                'users' => $users,
            ]);
            return;
        }

        $selectedUser = User::findByID($preselectedUserId);

        if (!$selectedUser || (int)($selectedUser['is_active'] ?? 0) !== 1) {
            $_SESSION['error_message'] = "Utilisateur introuvable ou inactif.";
            header('Location: index.php?controller=admin&action=billing');
            exit;
        }

        self::render('admin/shop/bill_user_product', [
            'pageTitle' => 'Facturer un utilisateur — CKS GO',
            'csrf_token' => getCsrfToken(),
            'users' => $users,
            'products' => Product::getBillableProductsWithVariants(),
            'selectedUser' => $selectedUser,
            'preselectedUserId' => $preselectedUserId,
        ]);
    }

    public function createUserProductCharge(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=billing');
            exit;
        }

        checkCsrfToken();

        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $variantIds = $_POST['variant_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $customLabels = $_POST['custom_label'] ?? [];
        $customAmounts = $_POST['custom_amount'] ?? [];
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $redirectUrl = 'index.php?controller=admin&action=billing' . ($userId > 0 ? '&user_id=' . $userId : '');

        if (
            $userId <= 0
            || $adminId <= 0
            || !is_array($variantIds)
            || !is_array($quantities)
            || !is_array($customLabels)
            || !is_array($customAmounts)
        ) {
            $_SESSION['error_message'] = "Requête de facturation invalide.";
            header('Location: ' . $redirectUrl);
            exit;
        }

        $user = User::findByID($userId);
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            $_SESSION['error_message'] = "Impossible de facturer un utilisateur inactif.";
            header('Location: index.php?controller=admin&action=billing');
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

        $customLines = [];
        $customMax = max(count($customLabels), count($customAmounts));

        for ($i = 0; $i < $customMax; $i++) {
            $label = isset($customLabels[$i]) ? trim((string)$customLabels[$i]) : '';
            $amount = isset($customAmounts[$i]) ? trim((string)$customAmounts[$i]) : '';

            if ($label !== '' || $amount !== '') {
                $customLines[] = [
                    'label' => $label,
                    'amount' => $amount,
                ];
            }
        }

        if (empty($lines) && empty($customLines)) {
            $_SESSION['error_message'] = "Ajoute au moins un produit ou un montant libre à facturer.";
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $orderId = Order::createAdminMultiChargeForUser($userId, $lines, $adminId, $customLines);

            $this->logAdminAction(
                'admin_create_user_product_charge',
                'Facturation user #' . $userId . ' / commande #' . $orderId .
                ' / produits=' . count($lines) . ' / montants_libres=' . count($customLines)
            );

            $_SESSION['success_message'] = "Facturation réussie. Commande #{$orderId} créée.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_create_user_product_charge_failed',
                'Échec facturation user #' . $userId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function logs(): void
    {
        checkRole('admin');

        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $category = trim((string)($_GET['category'] ?? ''));
        $outcome = trim((string)($_GET['outcome'] ?? ''));
        $actorId = max(0, (int)($_GET['actor_id'] ?? 0));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 30;

        if (!in_array($category, ['', 'users', 'catalog', 'billing', 'support', 'security', 'settings'], true)) {
            $category = '';
        }

        if (!in_array($outcome, ['', 'success', 'failure'], true)) {
            $outcome = '';
        }

        foreach (['dateFrom', 'dateTo'] as $dateVariable) {
            $dateValue = $$dateVariable;
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateValue);
            if ($dateValue !== '' && (!$parsed || $parsed->format('Y-m-d') !== $dateValue)) {
                $$dateVariable = '';
            }
        }

        $filters = compact('q', 'category', 'outcome', 'actorId', 'dateFrom', 'dateTo');
        $filters['actor_id'] = $filters['actorId'];
        $filters['date_from'] = $filters['dateFrom'];
        $filters['date_to'] = $filters['dateTo'];

        $totalLogs = Log::countAdminLogs($filters);
        $totalPages = max(1, (int)ceil($totalLogs / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $logs = Log::getAdminLogs($filters, $perPage, $offset);

        self::render('admin/logs/index', [
            'logs' => $logs,
            'q' => $q,
            'category' => $category,
            'outcome' => $outcome,
            'actorId' => $actorId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'logStats' => Log::getAdminLogStats($filters),
            'logActors' => Log::getAdminLogActors(),
            'page' => $page,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages
        ]);
    }

    public function tickets(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $assignment = isset($_GET['assignment']) ? trim($_GET['assignment']) : '';
        $waiting = isset($_GET['waiting']) ? trim($_GET['waiting']) : '';
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        $allowedStatuses = Ticket::getAllowedStatuses();
        $allowedPriorities = Ticket::getAllowedPriorities();
        $allowedCategories = Ticket::getAllowedCategories();
        $ticketStats = Ticket::getAdminStats();
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if ($priority !== '' && !in_array($priority, $allowedPriorities, true)) {
            $priority = '';
        }

        if ($category !== '' && !in_array($category, $allowedCategories, true)) {
            $category = '';
        }
        if (!in_array($assignment, ['', 'mine', 'unassigned'], true)) {
            $assignment = '';
        }
        if (!in_array($waiting, ['', 'staff', 'user'], true)) {
            $waiting = '';
        }

        $totalTickets = Ticket::countAll(
            $status !== '' ? $status : null,
            $priority !== '' ? $priority : null,
            $q !== '' ? $q : null,
            $category !== '' ? $category : null,
            $assignment !== '' ? $assignment : null,
            $adminId,
            $waiting !== '' ? $waiting : null
        );

        $totalPages = max(1, (int)ceil($totalTickets / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $tickets = Ticket::getAll(
            $status !== '' ? $status : null,
            $priority !== '' ? $priority : null,
            $q !== '' ? $q : null,
            $perPage,
            $offset,
            $category !== '' ? $category : null,
            $assignment !== '' ? $assignment : null,
            $adminId,
            $waiting !== '' ? $waiting : null
        );

        self::render('admin/tickets/index', [
            'tickets' => $tickets,
            'csrf_token' => $csrf_token,
            'status' => $status,
            'priority' => $priority,
            'category' => $category,
            'assignment' => $assignment,
            'waiting' => $waiting,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalTickets' => $totalTickets,
            'totalPages' => $totalPages,
            'allowedStatuses' => $allowedStatuses,
            'allowedPriorities' => $allowedPriorities,
            'allowedCategories' => $allowedCategories,
            'ticket_stats' => $ticketStats
        ]);
    }

    public function showTicket(): void
    {
        checkRole('admin');

        $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        $ticket = Ticket::findById($ticketId);

        if (!$ticket) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        $messages = TicketMessage::getByTicketId($ticketId);
        $csrf_token = getCsrfToken();

        self::render('admin/tickets/show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'csrf_token' => $csrf_token,
            'allowedStatuses' => Ticket::getAllowedStatuses(),
            'allowedPriorities' => Ticket::getAllowedPriorities(),
            'allowedCategories' => Ticket::getAllowedCategories(),
            'staffMembers' => array_values(array_filter(
                User::getActiveStaffDirectory(),
                static fn(array $member): bool => (bool) (
                    getUserPermissionMatrix($member)['support.manage']['effective_allowed'] ?? false
                )
            ))
        ]);
    }

    public function assignTicket(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $assigneeId = isset($_POST['assigned_admin_id']) && $_POST['assigned_admin_id'] !== ''
            ? (int)$_POST['assigned_admin_id']
            : null;

        try {
            Ticket::assign($ticketId, $assigneeId);
            $this->logAdminAction('admin_ticket_assigned', 'Ticket #' . $ticketId . ' / responsable ' . ($assigneeId ?? 'aucun'));
            $_SESSION['success_message'] = $assigneeId === null
                ? "Le ticket n'est plus attribué."
                : "Le ticket a bien été attribué.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function replyTicket(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();

        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $message = trim($_POST['message'] ?? '');
        $setInProgress = isset($_POST['set_in_progress']) ? (bool)$_POST['set_in_progress'] : true;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($ticketId <= 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        try {
            TicketMessage::addAdminMessage($ticketId, $adminId, $message, $setInProgress);

            $this->logAdminAction(
                'admin_ticket_reply',
                'Réponse admin sur ticket #' . $ticketId
            );

            $_SESSION['success_message'] = "La réponse a bien été envoyée.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_ticket_reply_failed',
                'Échec réponse ticket #' . $ticketId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function updateTicketStatus(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();

        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $status = trim($_POST['status'] ?? '');

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        try {
            $ticket = Ticket::findById($ticketId);

            if (!$ticket) {
                throw new RuntimeException("Ticket introuvable.");
            }

            Ticket::updateStatus($ticketId, $status, (int)($_SESSION['user']['id'] ?? 0));

            $this->logAdminAction(
                'admin_ticket_status_update',
                'Ticket #' . $ticketId . ' / statut => ' . $status
            );

            $_SESSION['success_message'] = "Le statut du ticket a bien été mis à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_ticket_status_update_failed',
                'Échec mise à jour statut ticket #' . $ticketId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function updateTicketPriority(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();

        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $priority = trim($_POST['priority'] ?? '');

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        try {
            $ticket = Ticket::findById($ticketId);

            if (!$ticket) {
                throw new RuntimeException("Ticket introuvable.");
            }

            Ticket::updatePriority($ticketId, $priority);

            $this->logAdminAction(
                'admin_ticket_priority_update',
                'Ticket #' . $ticketId . ' / priorité => ' . $priority
            );

            $_SESSION['success_message'] = "La priorité du ticket a bien été mise à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_ticket_priority_update_failed',
                'Échec mise à jour priorité ticket #' . $ticketId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function closeTicket(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();

        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        try {
            $ticket = Ticket::findById($ticketId);

            if (!$ticket) {
                throw new RuntimeException("Ticket introuvable.");
            }

            Ticket::close($ticketId, (int)($_SESSION['user']['id'] ?? 0));

            $this->logAdminAction(
                'admin_ticket_closed',
                'Ticket #' . $ticketId . ' fermé'
            );

            $_SESSION['success_message'] = "Le ticket a bien été fermé.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_ticket_closed_failed',
                'Échec fermeture ticket #' . $ticketId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function reopenTicket(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        checkCsrfToken();

        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=admin&action=tickets');
            exit;
        }

        try {
            $ticket = Ticket::findById($ticketId);

            if (!$ticket) {
                throw new RuntimeException("Ticket introuvable.");
            }

            Ticket::reopen($ticketId);

            $this->logAdminAction(
                'admin_ticket_reopened',
                'Ticket #' . $ticketId . ' rouvert'
            );

            $_SESSION['success_message'] = "Le ticket a bien été rouvert.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_ticket_reopened_failed',
                'Échec réouverture ticket #' . $ticketId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showTicket&id=' . $ticketId);
        exit;
    }


    public function alerts(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
        $type = isset($_GET['type']) ? trim($_GET['type']) : '';
        $owner = isset($_GET['owner']) ? trim($_GET['owner']) : '';
        $age = isset($_GET['age']) ? trim($_GET['age']) : '';
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        $allowedStatuses = Alert::getAllowedStatuses();
        $allowedPriorities = Alert::getAllowedPriorities();
        $allowedTypes = Alert::getAllowedTypes();
        $alertStats = Alert::getDashboardStats();
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if ($priority !== '' && !in_array($priority, $allowedPriorities, true)) {
            $priority = '';
        }

        if ($type !== '' && !in_array($type, $allowedTypes, true)) {
            $type = '';
        }
        if (!in_array($owner, ['', 'mine', 'unassigned'], true)) {
            $owner = '';
        }
        if (!in_array($age, ['', 'recent', 'stale'], true)) {
            $age = '';
        }

        $totalAlerts = Alert::countWorkQueue(
            $status !== '' ? $status : null,
            $priority !== '' ? $priority : null,
            $type !== '' ? $type : null,
            $q !== '' ? $q : null,
            $owner !== '' ? $owner : null,
            $adminId,
            $age !== '' ? $age : null
        );

        $totalPages = max(1, (int)ceil($totalAlerts / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $alerts = Alert::getWorkQueue(
            $status !== '' ? $status : null,
            $priority !== '' ? $priority : null,
            $type !== '' ? $type : null,
            $q !== '' ? $q : null,
            $perPage,
            $offset,
            $owner !== '' ? $owner : null,
            $adminId,
            $age !== '' ? $age : null
        );

        self::render('admin/alerts/index', [
            'alerts' => $alerts,
            'csrf_token' => $csrf_token,
            'status' => $status,
            'priority' => $priority,
            'type' => $type,
            'owner' => $owner,
            'age' => $age,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalAlerts' => $totalAlerts,
            'totalPages' => $totalPages,
            'allowedStatuses' => $allowedStatuses,
            'allowedPriorities' => $allowedPriorities,
            'allowedTypes' => $allowedTypes,
            'alert_stats' => $alertStats
        ]);
    }

    public function showAlert(): void
    {
        checkRole('admin');

        $alertId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($alertId <= 0) {
            $_SESSION['error_message'] = "Alerte introuvable.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        $alert = Alert::findById($alertId);
        if (!$alert) {
            $_SESSION['error_message'] = "Alerte introuvable.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        $events = Alert::getEventsByAlertId($alertId);
        $refundContext = Alert::getRefundContext($alertId);
        $csrf_token = getCsrfToken();

        self::render('admin/alerts/show', [
            'alert' => $alert,
            'events' => $events,
            'refundContext' => $refundContext,
            'csrf_token' => $csrf_token,
            'allowedStatuses' => Alert::getAllowedStatuses(),
            'allowedPriorities' => Alert::getAllowedPriorities(),
            'allowedTypes' => Alert::getAllowedTypes()
        ]);
    }

    public function assignAlert(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Alert::assignToAdmin($alertId, $adminId);
            $this->logAdminAction('admin_alert_assigned', 'Alerte #' . $alertId . ' prise en charge');
            $_SESSION['success_message'] = "L'alerte a bien été prise en charge.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_alert_assigned_failed', 'Alerte #' . $alertId . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function refundAlertReporter(): void
    {
        checkRole('admin');
        checkSession();

        if (!currentUserCan('alerts.manage')) {
            checkPermission('alerts.manage');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $submittedItemIds = $_POST['refund_item_ids'] ?? [];
        if (!is_array($submittedItemIds)) {
            $submittedItemIds = [$submittedItemIds];
        }
        $submittedQuantities = is_array($_POST['refund_quantities'] ?? null)
            ? $_POST['refund_quantities']
            : [];
        $quantitiesByItem = [];
        foreach (array_unique(array_map('intval', $submittedItemIds)) as $orderItemId) {
            $quantity = (int)($submittedQuantities[$orderItemId] ?? 0);
            if ($orderItemId > 0 && $quantity > 0) {
                $quantitiesByItem[$orderItemId] = $quantity;
            }
        }

        if ($quantitiesByItem === [] && isset($_POST['order_item_id'], $_POST['quantity'])) {
            $legacyItemId = (int)$_POST['order_item_id'];
            $legacyQuantity = (int)$_POST['quantity'];
            if ($legacyItemId > 0 && $legacyQuantity > 0) {
                $quantitiesByItem[$legacyItemId] = $legacyQuantity;
            }
        }
        $stockAction = (($_POST['stock_action'] ?? '') === 'restock') ? 'restock' : 'consumed';
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            $refund = Alert::refundReportedItems(
                $alertId,
                $quantitiesByItem,
                $adminId,
                $stockAction
            );

            $this->logAdminAction(
                'admin_alert_reporter_refunded',
                'Alerte #' . $alertId
                . ' / commande #' . (int)$refund['order_id']
                . ' / lignes=' . (int)$refund['refunded_line_count']
                . ' / quantité=' . (int)$refund['refunded_quantity']
                . ' / montant=' . number_format((float)$refund['refunded_amount'], 2, '.', '')
            );
            $_SESSION['success_message'] = sprintf(
                'Le signalant a été remboursé de %.2f € pour %d produit(s).',
                (float)$refund['refunded_amount'],
                (int)$refund['refunded_quantity']
            );
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_alert_reporter_refund_failed',
                'Alerte #' . $alertId . ' / ' . $e->getMessage()
            );
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function updateAlertStatus(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $status = trim((string)($_POST['status'] ?? ''));
        $resolutionCode = trim((string)($_POST['resolution_code'] ?? ''));
        $resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Alert::updateStatus($alertId, $status, $adminId, $resolutionNote, $resolutionCode);
            $this->logAdminAction('admin_alert_status_updated', 'Alerte #' . $alertId . ' / statut => ' . $status);
            $_SESSION['success_message'] = "Le statut de l'alerte a bien été mis à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_alert_status_update_failed', 'Alerte #' . $alertId . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function updateAlertPriority(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $priority = trim((string)($_POST['priority'] ?? ''));
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Alert::updatePriority($alertId, $priority, $adminId);
            $this->logAdminAction('admin_alert_priority_updated', 'Alerte #' . $alertId . ' / priorité => ' . $priority);
            $_SESSION['success_message'] = "La priorité de l'alerte a bien été mise à jour.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_alert_priority_update_failed', 'Alerte #' . $alertId . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function reopenAlert(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $message = trim((string)($_POST['message'] ?? ''));
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Alert::reopen($alertId, $adminId, $message);
            $this->logAdminAction('admin_alert_reopened', 'Alerte #' . $alertId . ' rouverte');
            $_SESSION['success_message'] = "L'alerte a bien été rouverte.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_alert_reopen_failed', 'Alerte #' . $alertId . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function addAlertNote(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=alerts');
            exit;
        }

        checkCsrfToken();

        $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
        $message = trim((string)($_POST['message'] ?? ''));
        $adminId = (int)($_SESSION['user']['id'] ?? 0);

        try {
            Alert::addAdminNote($alertId, $adminId, $message);
            $this->logAdminAction('admin_alert_note_added', 'Note ajoutée sur alerte #' . $alertId);
            $_SESSION['success_message'] = "La note admin a bien été ajoutée.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_alert_note_failed', 'Alerte #' . $alertId . ' / ' . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showAlert&id=' . $alertId);
        exit;
    }

    public function news(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $state = isset($_GET['state']) ? trim($_GET['state']) : '';
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 9;
        $allowedCategories = News::getAllowedCategories();

        if (!in_array($state, ['', 'published', 'draft', 'pinned'], true)) {
            $state = '';
        }
        if ($category !== '' && !in_array($category, $allowedCategories, true)) {
            $category = '';
        }

        $totalNews = News::countAll($q ?: null, $state ?: null, $category ?: null);
        $totalPages = max(1, (int)ceil($totalNews / $perPage));
        $page = min($page, $totalPages);
        $newsList = News::getAll(
            $q ?: null,
            $state ?: null,
            $category ?: null,
            $perPage,
            ($page - 1) * $perPage
        );

        self::render('admin/news/index', [
            'newsList' => $newsList,
            'csrf_token' => $csrf_token,
            'q' => $q,
            'state' => $state,
            'category' => $category,
            'page' => $page,
            'totalNews' => $totalNews,
            'totalPages' => $totalPages,
            'allowedCategories' => $allowedCategories,
            'newsStats' => News::getAdminStats()
        ]);
    }

    public function createNews(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();

        self::render('admin/news/create', [
            'csrf_token' => $csrf_token,
            'allowedCategories' => News::getAllowedCategories(),
            'allowedAudiences' => News::getAllowedAudiences()
        ]);
    }

    public function storeNews(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        checkCsrfToken();

        try {
            News::create([
                'title' => $_POST['title'] ?? '',
                'content' => $_POST['content'] ?? '',
                'summary' => $_POST['summary'] ?? '',
                'category' => $_POST['category'] ?? 'general',
                'audience' => $_POST['audience'] ?? 'all',
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
                'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
                'author_id' => (int)($_SESSION['user']['id'] ?? 0)
            ]);

            $this->logAdminAction('admin_news_created', 'Nouvelle annonce créée');

            $_SESSION['success_message'] = "Annonce créée avec succès.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction('admin_news_create_failed', $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=admin&action=createNews');
            exit;
        }
    }

    public function editNews(): void
    {
        checkRole('admin');

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            $_SESSION['error_message'] = "Annonce introuvable.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        $news = News::findById($id);

        if (!$news) {
            $_SESSION['error_message'] = "Annonce introuvable.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        $csrf_token = getCsrfToken();

        self::render('admin/news/edit', [
            'news' => $news,
            'csrf_token' => $csrf_token,
            'allowedCategories' => News::getAllowedCategories(),
            'allowedAudiences' => News::getAllowedAudiences()
        ]);
    }

    public function updateNews(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $_SESSION['error_message'] = "Annonce invalide.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        try {
            News::updateById($id, [
                'title' => $_POST['title'] ?? '',
                'content' => $_POST['content'] ?? '',
                'summary' => $_POST['summary'] ?? '',
                'category' => $_POST['category'] ?? 'general',
                'audience' => $_POST['audience'] ?? 'all',
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
                'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
                'updated_by_id' => (int)($_SESSION['user']['id'] ?? 0)
            ]);

            $this->logAdminAction('admin_news_updated', 'Annonce #' . $id . ' mise à jour');

            $_SESSION['success_message'] = "Annonce mise à jour avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_news_update_failed', 'Annonce #' . $id . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=news');
        exit;
    }

    public function toggleNewsPublication(): void
    {
        checkRole('admin');
        checkSession();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        checkCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $published = (int)($_POST['published'] ?? 0) === 1;

        try {
            if (!News::findById($id)) {
                throw new RuntimeException('Actualité introuvable.');
            }
            News::setPublished($id, $published, (int)($_SESSION['user']['id'] ?? 0));
            $this->logAdminAction('admin_news_publication_changed', 'Actualité #' . $id . ' / publication ' . ($published ? 'active' : 'inactive'));
            $_SESSION['success_message'] = $published ? "L'actualité est publiée." : "L'actualité repasse en brouillon.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=news');
        exit;
    }

    public function duplicateNews(): void
    {
        checkRole('admin');
        checkSession();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        checkCsrfToken();
        $id = (int)($_POST['id'] ?? 0);

        try {
            $copyId = News::duplicateById($id, (int)($_SESSION['user']['id'] ?? 0));
            $this->logAdminAction('admin_news_duplicated', 'Actualité #' . $id . ' dupliquée en #' . $copyId);
            $_SESSION['success_message'] = "Une copie en brouillon a été créée.";
            header('Location: index.php?controller=admin&action=editNews&id=' . $copyId);
            exit;
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=admin&action=news');
            exit;
        }
    }

    public function deleteNews(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $_SESSION['error_message'] = "Annonce invalide.";
            header('Location: index.php?controller=admin&action=news');
            exit;
        }

        try {
            News::deleteById($id);

            $this->logAdminAction('admin_news_deleted', 'Annonce #' . $id . ' supprimée');

            $_SESSION['success_message'] = "Annonce supprimée avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction('admin_news_delete_failed', 'Annonce #' . $id . ' / ' . $e->getMessage());

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=news');
        exit;
    }

    public function orders(): void
    {
        checkRole('admin');

        $csrf_token = getCsrfToken();
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $allowedStatuses = ['pending_payment', 'paid', 'partially_refunded', 'refunded', 'cancelled'];

        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        try {
            Payment::refreshAllOrderFinancialStatuses();
        } catch (Throwable $e) {
            // On évite de casser la liste si une commande est incohérente.
        }

        $summary = Order::summarizeAdminOrders($q !== '' ? $q : null, $status !== '' ? $status : null);
        $totalOrders = (int)($summary['total_orders'] ?? 0);
        $totalPages = max(1, (int)ceil($totalOrders / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $totalAmount = (float)($summary['total_amount'] ?? 0);
        $totalPaid = (float)($summary['total_paid'] ?? 0);
        $totalRemaining = (float)($summary['total_remaining'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $orders = Order::searchAdminOrders(
            $q !== '' ? $q : null,
            $status !== '' ? $status : null,
            $perPage,
            $offset
        );
        $orderIds = [];

        foreach ($orders as $orderSummary) {
            $orderIds[] = (int)($orderSummary['id'] ?? 0);
        }

        $invoiceMap = Invoice::getMapByOrderIds($orderIds);

        self::render('admin/orders/index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'perPage' => $perPage,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages,
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
            'invoiceMap' => $invoiceMap,
            'csrf_token' => $csrf_token
        ]);
    }

    public function showOrder(): void
    {
        checkRole('admin');

        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($orderId <= 0) {
            $_SESSION['error_message'] = "Commande introuvable.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        Payment::refreshOrderFinancialStatus($orderId);

        $order = Order::getAdminOrderById($orderId);
        if (!$order) {
            $_SESSION['error_message'] = "Commande introuvable.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        $csrf_token = getCsrfToken();
        $invoice = Invoice::findByOrderId($orderId);

        self::render('admin/orders/show', [
            'order' => $order,
            'invoice' => $invoice,
            'csrf_token' => $csrf_token
        ]);
    }

    public function showInvoice(): void
    {
        checkRole('admin');

        $invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($invoiceId <= 0) {
            $_SESSION['error_message'] = "Facture introuvable.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        $invoice = Invoice::findById($invoiceId);

        if (!$invoice) {
            $_SESSION['error_message'] = "Facture introuvable.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        self::render('admin/invoices/show', [
            'invoice' => $invoice,
            'snapshot' => $invoice['snapshot'] ?? []
        ]);
    }

    public function generateInvoice(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        checkCsrfToken();

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $refundStockAction = (($_POST['refund_stock_action'] ?? '') === 'consumed') ? 'consumed' : 'restock';

        if ($orderId <= 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        try {
            $invoiceId = Invoice::createForOrder($orderId, $adminId);
            $invoice = Invoice::findById($invoiceId);

            $this->logAdminAction(
                'admin_invoice_generate',
                'Facture ' . (($invoice['invoice_number'] ?? '#?')) . ' générée pour commande #' . $orderId
            );

            $_SESSION['success_message'] = !empty($invoice['invoice_number'])
                ? 'Facture ' . $invoice['invoice_number'] . ' générée avec succès.'
                : 'Facture générée avec succès.';

            header('Location: index.php?controller=admin&action=showInvoice&id=' . $invoiceId);
            exit;
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_invoice_generate_failed',
                'Échec génération facture commande #' . $orderId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=admin&action=showOrder&id=' . $orderId);
            exit;
        }
    }

    public function generateSelectedInvoices(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        checkCsrfToken();

        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $orderIds = isset($_POST['order_ids']) && is_array($_POST['order_ids']) ? $_POST['order_ids'] : [];
        $q = normalizeSearchQuery($_POST['q'] ?? '');
        $status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;

        $redirectUrl = 'index.php?controller=admin&action=orders&page=' . $page;
        if ($q !== '') {
            $redirectUrl .= '&q=' . urlencode($q);
        }
        if ($status !== '') {
            $redirectUrl .= '&status=' . urlencode($status);
        }

        if ($adminId <= 0) {
            $_SESSION['error_message'] = "Session admin invalide.";
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (empty($orderIds)) {
            $_SESSION['error_message'] = "Sélectionne au moins une commande payée.";
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $result = Invoice::createManyForOrders($orderIds, $adminId);

            $generatedCount = count($result['generated'] ?? []);
            $existingCount = count($result['existing'] ?? []);
            $skippedCount = count($result['skipped'] ?? []);
            $errorCount = count($result['errors'] ?? []);

            $messageParts = [];

            if ($generatedCount > 0) {
                $messageParts[] = $generatedCount . ' facture(s) générée(s)';
            }
            if ($existingCount > 0) {
                $messageParts[] = $existingCount . ' déjà existante(s)';
            }
            if ($skippedCount > 0) {
                $messageParts[] = $skippedCount . ' commande(s) ignorée(s)';
            }
            if ($errorCount > 0) {
                $messageParts[] = $errorCount . ' erreur(s)';
            }

            $message = !empty($messageParts)
                ? 'Facturation lot : ' . implode(' · ', $messageParts) . '.'
                : 'Aucune facture générée.';

            if ($generatedCount > 0 || $existingCount > 0 || $skippedCount > 0) {
                $_SESSION['success_message'] = $message;
            } else {
                $_SESSION['error_message'] = $message;
            }

            $this->logAdminAction(
                'admin_invoice_generate_batch',
                'Génération factures lot / sélection=' . count($orderIds) .
                ' / générées=' . $generatedCount .
                ' / existantes=' . $existingCount .
                ' / ignorées=' . $skippedCount .
                ' / erreurs=' . $errorCount
            );
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_invoice_generate_batch_failed',
                'Échec génération lot factures / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function refundOrder(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        checkCsrfToken();

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $refundStockAction = (($_POST['refund_stock_action'] ?? '') === 'consumed') ? 'consumed' : 'restock';

        if ($orderId <= 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        try {
            Payment::refundOrderFull($orderId, $adminId, $refundStockAction);
            $stockActionLabel = $refundStockAction === 'restock'
                ? 'produit reversé au stock'
                : 'produit consommé / détruit';

            $this->logAdminAction(
                'admin_refund_order_full',
                'Commande #' . $orderId . ' remboursée intégralement / ' . $stockActionLabel
            );

            $_SESSION['success_message'] = "Commande remboursée intégralement ({$stockActionLabel}).";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_refund_order_full_failed',
                'Échec remboursement intégral commande #' . $orderId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showOrder&id=' . $orderId);
        exit;
    }

    public function refundOrderItem(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        checkCsrfToken();

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $orderItemId = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $refundStockAction = (($_POST['refund_stock_action'] ?? '') === 'consumed') ? 'consumed' : 'restock';

        if ($orderId <= 0 || $orderItemId <= 0 || $quantity <= 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        try {
            Payment::refundOrderItemPartial($orderItemId, $quantity, $adminId, $refundStockAction);
            $stockActionLabel = $refundStockAction === 'restock'
                ? 'produit reversé au stock'
                : 'produit consommé / détruit';

            $this->logAdminAction(
                'admin_refund_order_item_partial',
                'Commande #' . $orderId . ' / ligne #' . $orderItemId . ' / quantité=' . $quantity . ' / ' . $stockActionLabel
            );

            $_SESSION['success_message'] = "Remboursement partiel enregistré ({$stockActionLabel}).";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_refund_order_item_partial_failed',
                'Commande #' . $orderId . ' / ligne #' . $orderItemId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showOrder&id=' . $orderId);
        exit;
    }

    public function cancelOrder(): void
    {
        checkRole('admin');
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        checkCsrfToken();

        $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $adminId = (int)($_SESSION['user']['id'] ?? 0);
        $refundStockAction = (($_POST['refund_stock_action'] ?? '') === 'consumed') ? 'consumed' : 'restock';

        if ($orderId <= 0 || $adminId <= 0) {
            $_SESSION['error_message'] = "Requête invalide.";
            header('Location: index.php?controller=admin&action=orders');
            exit;
        }

        try {
            Order::cancelOrderByAdmin($orderId, $adminId);

            $this->logAdminAction(
                'admin_order_cancelled',
                'Commande #' . $orderId . ' annulée'
            );

            $_SESSION['success_message'] = "Commande annulée avec succès.";
        } catch (Throwable $e) {
            $this->logAdminAction(
                'admin_order_cancel_failed',
                'Commande #' . $orderId . ' / ' . $e->getMessage()
            );

            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=admin&action=showOrder&id=' . $orderId);
        exit;
    }
}
