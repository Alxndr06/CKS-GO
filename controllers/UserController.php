<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/TicketMessage.php';
require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../services/Mailer.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/AccessBan.php';

class UserController extends Controller
{
    private const LOGIN_MAX_FAILED_ATTEMPTS = 5;
    private const LOGIN_OBSERVATION_WINDOW_MINUTES = 15;
    private const LOGIN_LOCKOUT_MINUTES = 15;
    private const LOGIN_FAILURE_DELAY_MIN_MICROSECONDS = 250000;
    private const LOGIN_FAILURE_DELAY_MAX_MICROSECONDS = 450000;
    private const PASSWORD_RESET_TOKEN_TTL_MINUTES = 60;
    private const PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS = 300;

    private function applyLoginFailureDelay(): void
    {
        try {
            usleep(random_int(
                self::LOGIN_FAILURE_DELAY_MIN_MICROSECONDS,
                self::LOGIN_FAILURE_DELAY_MAX_MICROSECONDS
            ));
        } catch (Throwable $throwable) {
            usleep(300000);
        }
    }

    private function buildTemporaryLoginLockMessage(array $user): string
    {
        $remainingSeconds = User::getLoginLockRemainingSeconds($user);

        if ($remainingSeconds <= 0) {
            return "Connexion temporairement bloquée. Réessayez dans quelques instants.";
        }

        $remainingMinutes = (int)ceil($remainingSeconds / 60);

        return sprintf(
            "Trop de tentatives de connexion. Réessayez dans %d minute%s.",
            $remainingMinutes,
            $remainingMinutes > 1 ? 's' : ''
        );
    }

    private function getPasswordResetGenericMessage(): string
    {
        return "Si un compte correspondant existe, un lien de réinitialisation vient d’être envoyé à l’adresse associée.";
    }

    private function getGenericLoginFailureMessage(): string
    {
        return "Connexion impossible. Vérifiez vos identifiants ou réessayez dans quelques minutes.";
    }

    private function getPasswordValidationError(string $newPassword, string $confirmPassword): ?string
    {
        return validatePasswordPolicy($newPassword, $confirmPassword);
    }

    private function redirectToResetPasswordFormWithError(string $message, string $token): void
    {
        checkSession();
        $_SESSION['error'] = '⛔ ' . $message;
        header('Location: index.php?controller=user&action=resetPassword&token=' . urlencode($token));
        exit;
    }

    private function buildPasswordResetUrl(string $token): string
    {
        return buildAppUrl('index.php?controller=user&action=resetPassword&token=' . urlencode($token));
    }

    private function sendPasswordResetEmail(array $user, string $token): bool
    {
        $resetUrl = $this->buildPasswordResetUrl($token);

        return Mailer::sendPasswordResetLink([
            'username' => (string)($user['username'] ?? ''),
            'firstname' => (string)($user['firstname'] ?? ''),
            'lastname' => (string)($user['lastname'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'reset_url' => $resetUrl,
        ]);
    }

    private function ensureTicketAccess(int $userId): void
    {
        $user = User::findByID($userId);

        if (!$user) {
            $_SESSION['error_message'] = "Utilisateur introuvable.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $isActive = (int)($user['is_active'] ?? 0) === 1;
        $isLocked = (int)($user['locked'] ?? ($user['is_locked'] ?? 0)) === 1;

        if (!$isActive) {
            $_SESSION['error_message'] = "Votre compte n'est pas actif.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if (empty($user['email_verified_at'])) {
            $_SESSION['error_message'] = "Veuillez confirmer votre adresse e-mail avant d’accéder au support.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        if ($isLocked) {
            $_SESSION['error_message'] = "Votre accès au support est temporairement bloqué.";
            header('Location: index.php?controller=user&action=dashboard');
            exit;
        }
    }

    public function login(): void
    {
        checkSession();
        redirectIfConnected('Vous êtes déjà connecté.');
        $csrf_token = getCsrfToken();
        self::render('user/login', ['csrf_token' => $csrf_token]);
    }

    public function doLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithError("Méthode non autorisée", 'user', 'login');
            return;
        }

        checkSession();
        checkCsrfToken();

        $identifier = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::findByMail($identifier);
        } else {
            $user = User::findByUsername($identifier);
        }

        $loginEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $identifier
            : (string)($user['email'] ?? '');

        if ($loginEmail !== '' && AccessBan::isEmailBanned($loginEmail)) {
            $this->applyLoginFailureDelay();
            redirectWithError($this->getGenericLoginFailureMessage(), 'user', 'login');
            return;
        }

        if ($user && (int)($user['is_banned'] ?? 0) === 1) {
            $this->applyLoginFailureDelay();
            redirectWithError($this->getGenericLoginFailureMessage(), 'user', 'login');
            return;
        }

        if ($user && User::isLoginTemporarilyLocked($user)) {
            $this->applyLoginFailureDelay();
            redirectWithError($this->getGenericLoginFailureMessage(), 'user', 'login');
            return;
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            if (empty($user['email_verified_at'])) {
                $this->applyLoginFailureDelay();
                redirectWithError("Votre adresse e-mail n'a pas encore été confirmée. Vérifiez votre boîte mail.", 'user', 'login');
                return;
            }

            if (!(int)($user['is_active'] ?? 0)) {
                $this->applyLoginFailureDelay();
                redirectWithError("Votre compte n'est pas encore activé.", 'user', 'login');
                return;
            }

            User::resetFailedLoginAttempts((int)$user['id']);

            if (passwordHashNeedsUpgrade((string)$user['password_hash'])) {
                $rehash = hashPassword($password);
                User::updatePasswordById((int)$user['id'], $rehash);
            }

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'lastname' => $user['lastname'],
                'firstname' => $user['firstname'],
                'email' => $user['email'],
                'note' => $user['note'] ?? 0,
                'role' => $user['role'],
                'unit' => $user['unit'],
                'locked' => $user['locked'] ?? ($user['is_locked'] ?? 0),
                'created_at' => $user['created_at'],
                'is_active' => $user['is_active']
            ];

            $_SESSION['last_activity'] = time();

            redirectWithSuccess("Connexion réussie !", 'home', 'index');
            return;
        }

        if ($user) {
            $loginAttemptState = User::registerFailedLoginAttempt(
                (int)$user['id'],
                self::LOGIN_MAX_FAILED_ATTEMPTS,
                self::LOGIN_OBSERVATION_WINDOW_MINUTES,
                self::LOGIN_LOCKOUT_MINUTES
            );

            $this->applyLoginFailureDelay();

            if (!empty($loginAttemptState['is_locked'])) {
                redirectWithError($this->getGenericLoginFailureMessage(), 'user', 'login');
                return;
            }
        } else {
            $this->applyLoginFailureDelay();
        }

        redirectWithError($this->getGenericLoginFailureMessage(), 'user', 'login');
    }

    public function register(): void
    {
        checkSession();
        redirectIfConnected('Impossible car déjà connecté.');
        $csrf_token = getCsrfToken();
        self::render('user/register', ['csrf_token' => $csrf_token]);
    }

    public function doRegister(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithError("Méthode non autorisée", 'user', 'register');
            return;
        }

        checkSession();
        checkCsrfToken();

        $username = preg_replace('/\s+/', '', trim($_POST['username']));
        $lastname = trim($_POST['lastname']);
        $firstname = trim($_POST['firstname']);
        $email = strtolower(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $passwordRaw = $_POST['password'];
        $confirmPasswordRaw = $_POST['confirmPassword'];
        $unit = $_POST['unit'];
        $activation_token = bin2hex(random_bytes(32));

        $errors = [];

        if (empty($username) || empty($lastname) || empty($email) || empty($passwordRaw)) {
            $errors[] = "Merci de remplir tous les champs obligatoires.";
        }

        if (!validateString($lastname)) {
            $errors[] = "Le nom contient des caractères non autorisés.";
        }

        if (!validateString($firstname)) {
            $errors[] = "Le prénom contient des caractères non autorisés.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse e-mail invalide.";
        }

        if ($email !== '' && AccessBan::isEmailBanned($email)) {
            $errors[] = "Cette adresse e-mail ne peut pas être utilisée.";
        }

        if (!User::checkUnicity('email', $email)) {
            $errors[] = "Cet email est déjà enregistré.";
        }

        if (!User::checkUnicity('username', $username)) {
            $errors[] = "Ce pseudo est déjà utilisé.";
        }

        $passwordError = validatePasswordPolicy($passwordRaw, $confirmPasswordRaw);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }

        if (empty($unit)) {
            $errors[] = "Vous devez sélectionner un service.";
        }

        $allowed_units = ['mineurs', 'vif', 'syndicat'];
        if (!in_array($unit, $allowed_units, true)) {
            $errors[] = "Service sélectionné invalide.";
        }

        if (!empty($errors)) {
            redirectWithError(implode('<br>', $errors), 'user', 'register');
            return;
        }

        $password_hash = hashPassword($passwordRaw);

        $registrationMode = Setting::get('registration_mode', 'open');
        $isActive = $registrationMode === 'approval_required' ? 0 : 1;

        $success = User::create([
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'unit' => $unit,
            'password_hash' => $password_hash,
            'is_active' => $isActive,
            'is_locked' => 0,
            'activation_token' => $activation_token,
            'email_verified_at' => null
        ]);

        if (!$success) {
            redirectWithError("Une erreur est survenue lors de l'enregistrement.", 'user', 'register');
            return;
        }

        $verificationUrl = buildAppUrl('index.php?controller=user&action=verifyEmail&token=' . urlencode($activation_token));

        $createdUser = [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'unit' => $unit,
            'verification_url' => $verificationUrl
        ];

        $mailSent = Mailer::sendRegistrationReceived($createdUser);

        if (!$mailSent) {
            error_log('Mail inscription non envoyé pour ' . $email);
        }

        if ($registrationMode === 'approval_required') {
            redirectWithSuccess(
                "Votre inscription a bien été enregistrée. Confirmez votre adresse e-mail depuis le lien reçu, puis un administrateur devra valider votre compte.",
                'user',
                'login'
            );
            return;
        }

        redirectWithSuccess(
            "Votre inscription est presque terminée. Cliquez sur le lien reçu par e-mail pour activer votre accès.",
            'user',
            'login'
        );
    }

    public function verifyEmail(): void
    {
        checkSession();

        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            redirectWithError("Lien de validation invalide.", 'user', 'login');
            return;
        }

        $user = User::findByActivationToken($token);

        if (!$user) {
            redirectWithError("Le lien de validation est invalide ou a déjà été utilisé.", 'user', 'login');
            return;
        }

        if (!empty($user['email_verified_at'])) {
            redirectWithSuccess("Votre adresse e-mail est déjà confirmée. Vous pouvez vous connecter.", 'user', 'login');
            return;
        }

        $verified = User::markEmailAsVerified((int)$user['id']);

        if (!$verified) {
            redirectWithError("Impossible de valider votre adresse e-mail pour le moment.", 'user', 'login');
            return;
        }

        if ((int)($user['is_active'] ?? 0) === 1) {
            redirectWithSuccess("Votre adresse e-mail a bien été confirmée. Vous pouvez maintenant vous connecter.", 'user', 'login');
            return;
        }

        redirectWithSuccess(
            "Votre adresse e-mail a bien été confirmée. Votre compte reste en attente de validation par un administrateur.",
            'user',
            'login'
        );
    }

    public function forgotPassword(): void
    {
        checkSession();

        if (isUserLoggedIn()) {
            redirectWithInformation("Vous êtes déjà connecté. Vous pouvez modifier votre mot de passe depuis votre compte.", 'user', 'account');
            return;
        }

        $csrf_token = getCsrfToken();

        self::render('user/forgot_password', [
            'csrf_token' => $csrf_token,
        ]);
    }

    public function sendPasswordResetLink(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithError("Méthode non autorisée.", 'user', 'forgotPassword');
            return;
        }

        checkCsrfToken();

        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $user = null;

        if ($identifier !== '') {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $user = User::findByMail(strtolower($identifier));
            } else {
                $user = User::findByUsername($identifier);
            }
        }

        if ($user) {
            $result = User::createPasswordResetTokenForUser(
                (int)$user['id'],
                self::PASSWORD_RESET_TOKEN_TTL_MINUTES,
                self::PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS
            );

            if (!empty($result['issued']) && !empty($result['token'])) {
                $mailSent = $this->sendPasswordResetEmail($user, (string)$result['token']);

                if (!$mailSent) {
                    User::clearPasswordResetToken((int)$user['id']);
                    error_log('Mail reset password non envoyé pour ' . (string)($user['email'] ?? ''));
                }
            }
        }

        $this->applyLoginFailureDelay();

        redirectWithSuccess($this->getPasswordResetGenericMessage(), 'user', 'login');
    }

    public function resetPassword(): void
    {
        checkSession();

        if (isUserLoggedIn()) {
            redirectWithInformation("Vous êtes déjà connecté. Vous pouvez modifier votre mot de passe depuis votre compte.", 'user', 'account');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->doResetPassword();
            return;
        }

        $token = trim((string)($_GET['token'] ?? ''));

        if ($token === '') {
            redirectWithError("Lien de réinitialisation invalide.", 'user', 'forgotPassword');
            return;
        }

        $user = User::findByPasswordResetToken($token);

        if (!$user) {
            redirectWithError("Le lien de réinitialisation est invalide ou expiré.", 'user', 'forgotPassword');
            return;
        }

        $csrf_token = getCsrfToken();

        self::render('user/reset_password', [
            'csrf_token' => $csrf_token,
            'token' => $token,
            'page_referrer_policy' => 'no-referrer',
        ]);
    }

    public function doResetPassword(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithError("Méthode non autorisée.", 'user', 'forgotPassword');
            return;
        }

        checkCsrfToken();

        $token = trim((string)($_POST['token'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($token === '') {
            redirectWithError("Lien de réinitialisation invalide.", 'user', 'forgotPassword');
            return;
        }

        $user = User::findByPasswordResetToken($token);

        if (!$user) {
            redirectWithError("Le lien de réinitialisation est invalide ou expiré.", 'user', 'forgotPassword');
            return;
        }

        $passwordError = $this->getPasswordValidationError($newPassword, $confirmPassword);

        if ($passwordError !== null) {
            $this->redirectToResetPasswordFormWithError($passwordError, $token);
        }

        $hash = hashPassword($newPassword);

        if (!User::updatePasswordById((int)$user['id'], $hash)) {
            $this->redirectToResetPasswordFormWithError("Impossible de réinitialiser le mot de passe.", $token);
        }

        $notificationSent = Mailer::sendPasswordResetConfirmation([
            'username' => (string)($user['username'] ?? ''),
            'firstname' => (string)($user['firstname'] ?? ''),
            'lastname' => (string)($user['lastname'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
        ]);

        if (!$notificationSent) {
            error_log('Mail confirmation reset password non envoyé pour ' . (string)($user['email'] ?? ''));
        }

        redirectWithSuccess("Votre mot de passe a bien été réinitialisé. Vous pouvez maintenant vous connecter.", 'user', 'login');
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirectWithError("Méthode non autorisée.", 'home', 'index');
            return;
        }

        checkSession();
        checkCsrfToken();

        if (!isUserLoggedIn()) {
            redirectWithError("Vous n'êtes pas connecté. Vous n'avez pas accès à cette fonctionnalité.", 'home', 'index');
            return;
        }

        session_unset();
        session_destroy();
        redirectWithSuccess('Déconnexion avec succès.', 'home', 'index');
    }

    public function dashboard(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user) {
            redirectWithError("Utilisateur introuvable.", 'home', 'index');
            return;
        }

        if (empty($user['email_verified_at'])) {
            redirectWithError("Veuillez confirmer votre adresse e-mail avant d’accéder à votre espace.", 'user', 'login');
            return;
        }

        $_SESSION['user']['note'] = $user['note'] ?? 0;
        $_SESSION['user']['firstname'] = $user['firstname'] ?? '';
        $_SESSION['user']['lastname'] = $user['lastname'] ?? '';
        $_SESSION['user']['email'] = $user['email'] ?? '';
        $_SESSION['user']['unit'] = $user['unit'] ?? '';
        $_SESSION['user']['role'] = $user['role'] ?? 'user';
        $_SESSION['user']['locked'] = $user['locked'] ?? 0;
        $_SESSION['user']['is_active'] = $user['is_active'] ?? 0;

        $orderStats = Order::getUserStats($userId);

        self::render('user/dashboard', [
            'user' => $user,
            'orderStats' => $orderStats
        ]);
    }

    public function show(): void
    {
        checkRole('admin');
        $csrf_token = getCsrfToken();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            redirectWithError("ID utilisateur invalide", 'user', 'allUsers');
            return;
        }

        $user = User::findByID($id);
        if (!$user) {
            redirectWithError("Utilisateur introuvable", 'user', 'allUsers');
            return;
        }

        self::render('admin/user/show_user', [
            'user' => $user,
            'csrf_token' => $csrf_token
        ]);
    }

    public function tickets(): void
    {
        checkSession();

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour accéder à vos tickets.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        $csrf_token = getCsrfToken();
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 10;

        $allowedStatuses = Ticket::getAllowedStatuses();
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $totalTickets = Ticket::countForUser(
            $userId,
            $status !== '' ? $status : null,
            $q !== '' ? $q : null
        );

        $totalPages = max(1, (int)ceil($totalTickets / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $tickets = Ticket::getAllForUser(
            $userId,
            $status !== '' ? $status : null,
            $q !== '' ? $q : null,
            $perPage,
            $offset
        );

        self::render('user/tickets/index', [
            'tickets' => $tickets,
            'csrf_token' => $csrf_token,
            'status' => $status,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalTickets' => $totalTickets,
            'totalPages' => $totalPages,
            'allowedStatuses' => $allowedStatuses
        ]);
    }

    public function createTicket(): void
    {
        checkSession();

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour créer un ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        $csrf_token = getCsrfToken();

        self::render('user/tickets/create', [
            'csrf_token' => $csrf_token,
            'allowedPriorities' => Ticket::getAllowedPriorities(),
            'allowedCategories' => Ticket::getAllowedCategories()
        ]);
    }

    public function storeTicket(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour créer un ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $category = trim($_POST['category'] ?? 'other');

        try {
            $ticketId = Ticket::create($userId, $subject, $message, $priority, $category);

            $_SESSION['success_message'] = "Votre ticket a été créé avec succès.";
            header('Location: index.php?controller=user&action=showTicket&id=' . $ticketId);
            exit;
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: index.php?controller=user&action=createTicket');
            exit;
        }
    }

    public function showTicket(): void
    {
        checkSession();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour accéder à ce ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        $ticket = Ticket::findByIdForUser($ticketId, $userId);

        if (!$ticket) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        $messages = TicketMessage::getByTicketId($ticketId);
        $csrf_token = getCsrfToken();

        self::render('user/tickets/show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'csrf_token' => $csrf_token
        ]);
    }

    public function replyTicket(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $message = trim($_POST['message'] ?? '');

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour répondre à un ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        try {
            TicketMessage::addUserMessage($ticketId, $userId, $message);

            $_SESSION['success_message'] = "Votre réponse a bien été envoyée.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=user&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function closeTicket(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour fermer un ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        $ticket = Ticket::findByIdForUser($ticketId, $userId);

        if (!$ticket) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        try {
            Ticket::close($ticketId);
            $_SESSION['success_message'] = "Le ticket a bien été fermé.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=user&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function reopenTicket(): void
    {
        checkSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Méthode non autorisée.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;

        if ($userId <= 0) {
            $_SESSION['error_message'] = "Vous devez être connecté pour rouvrir un ticket.";
            header('Location: index.php?controller=user&action=login');
            exit;
        }

        $this->ensureTicketAccess($userId);

        if ($ticketId <= 0) {
            $_SESSION['error_message'] = "Ticket invalide.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        $ticket = Ticket::findByIdForUser($ticketId, $userId);

        if (!$ticket) {
            $_SESSION['error_message'] = "Ticket introuvable.";
            header('Location: index.php?controller=user&action=tickets');
            exit;
        }

        try {
            Ticket::reopen($ticketId);
            $_SESSION['success_message'] = "Le ticket a bien été rouvert.";
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }

        header('Location: index.php?controller=user&action=showTicket&id=' . $ticketId);
        exit;
    }

    public function orders(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user || empty($user['email_verified_at'])) {
            redirectWithError("Accès refusé.", 'user', 'login');
            return;
        }

        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $totalOrders = Order::countForUser($userId, $q !== '' ? $q : null);
        $totalPages = max(1, (int)ceil($totalOrders / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $orders = Order::searchForUser(
            $userId,
            $q !== '' ? $q : null,
            $perPage,
            $offset
        );

        self::render('user/orders', [
            'orders' => $orders,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages
        ]);
    }

    public function showOrder(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $orderId = (int)($_GET['id'] ?? 0);

        if ($orderId <= 0) {
            redirectWithError("Commande introuvable.", 'user', 'orders');
            return;
        }

        $order = Order::getDetailedByUserId($orderId, $userId);

        if (!$order) {
            redirectWithError("Commande introuvable.", 'user', 'orders');
            return;
        }

        self::render('user/order_show', [
            'order' => $order
        ]);
    }

    public function printOrder(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $orderId = (int)($_GET['id'] ?? 0);

        if ($orderId <= 0) {
            redirectWithError("Commande introuvable.", 'user', 'orders');
            return;
        }

        $order = Order::getDetailedByUserId($orderId, $userId);

        if (!$order) {
            redirectWithError("Commande introuvable.", 'user', 'orders');
            return;
        }

        $invoice = Invoice::findByOrderId($orderId);

        self::render('user/order_print', [
            'order' => $order,
            'invoice' => $invoice
        ]);
    }

    public function payments(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user || empty($user['email_verified_at'])) {
            redirectWithError("Accès refusé.", 'user', 'login');
            return;
        }

        $q = normalizeSearchQuery($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $totalPayments = Payment::countForUser($userId, $q !== '' ? $q : null);
        $totalPages = max(1, (int)ceil($totalPayments / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $payments = Payment::searchForUser(
            $userId,
            $q !== '' ? $q : null,
            $perPage,
            $offset
        );

        self::render('user/payments', [
            'payments' => $payments,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'totalPayments' => $totalPayments,
            'totalPages' => $totalPages
        ]);
    }

    public function account(): void
    {
        checkConnect();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user) {
            redirectWithError("Utilisateur introuvable.", 'home', 'index');
            return;
        }

        $csrf_token = getCsrfToken();

        self::render('user/account', [
            'user' => $user,
            'csrf_token' => $csrf_token
        ]);
    }

    public function updateEmail(): void
    {
        checkConnect();
        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user) {
            redirectWithError("Utilisateur introuvable.", 'user', 'account');
            return;
        }

        $newEmail = strtolower(trim($_POST['email'] ?? ''));
        $currentPassword = $_POST['current_password'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            redirectWithError("Adresse e-mail invalide.", 'user', 'account');
            return;
        }

        if (AccessBan::isEmailBanned($newEmail)) {
            redirectWithError("Cette adresse e-mail ne peut pas être utilisée.", 'user', 'account');
            return;
        }

        if ($newEmail === strtolower((string)$user['email'])) {
            redirectWithError("Cette adresse e-mail est déjà celle de votre compte.", 'user', 'account');
            return;
        }

        if (!password_verify($currentPassword, (string)$user['password_hash'])) {
            redirectWithError("Mot de passe actuel incorrect.", 'user', 'account');
            return;
        }

        if (!User::checkUnicityForUpdate('email', $newEmail, $userId)) {
            redirectWithError("Cette adresse e-mail est déjà utilisée.", 'user', 'account');
            return;
        }

        $activationToken = bin2hex(random_bytes(32));

        if (!User::updateEmailForUser($userId, $newEmail, $activationToken)) {
            redirectWithError("Impossible de mettre à jour l’adresse e-mail.", 'user', 'account');
            return;
        }

        $verificationUrl = rtrim(APP_URL, '/') . '/index.php?controller=user&action=verifyEmail&token=' . urlencode($activationToken);

        $mailSent = Mailer::sendRegistrationReceived([
            'username' => $user['username'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'email' => $newEmail,
            'unit' => $user['unit'],
            'verification_url' => $verificationUrl
        ]);

        session_unset();
        session_destroy();

        if ($mailSent) {
            redirectWithSuccess(
                "Adresse e-mail mise à jour. Confirmez-la depuis le lien reçu avant de vous reconnecter.",
                'user',
                'login'
            );
            return;
        }

        redirectWithWarning(
            "Adresse e-mail mise à jour, mais le mail de confirmation n’a pas pu être envoyé.",
            'user',
            'login'
        );
    }

    public function updatePassword(): void
    {
        checkConnect();
        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user) {
            redirectWithError("Utilisateur introuvable.", 'user', 'account');
            return;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, (string)$user['password_hash'])) {
            redirectWithError("Mot de passe actuel incorrect.", 'user', 'account');
            return;
        }

        $passwordError = $this->getPasswordValidationError($newPassword, $confirmPassword);

        if ($passwordError !== null) {
            redirectWithError($passwordError, 'user', 'account');
            return;
        }

        $hash = hashPassword($newPassword);

        if (!User::updatePasswordById($userId, $hash)) {
            redirectWithError("Impossible de modifier le mot de passe.", 'user', 'account');
            return;
        }

        redirectWithSuccess("Mot de passe mis à jour avec succès.", 'user', 'account');
    }

    public function deleteAccount(): void
    {
        checkConnect();
        checkCsrfToken();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $user = User::findByID($userId);

        if (!$user) {
            redirectWithError("Utilisateur introuvable.", 'user', 'account');
            return;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $confirmText = trim($_POST['confirm_text'] ?? '');

        if (!password_verify($currentPassword, (string)$user['password_hash'])) {
            redirectWithError("Mot de passe actuel incorrect.", 'user', 'account');
            return;
        }

        if ($confirmText !== 'SUPPRIMER') {
            redirectWithError("Vous devez saisir SUPPRIMER pour confirmer.", 'user', 'account');
            return;
        }

        $currentNote = (float)($user['note'] ?? 0);
        $pendingTotal = (float)Payment::getPendingTotalForUser($userId);

        if ($currentNote > 0 || $pendingTotal > 0) {
            redirectWithError(
                "Impossible de supprimer votre compte tant qu’un solde reste dû. Merci de régulariser votre situation avant de réessayer.",
                'user',
                'account'
            );
            return;
        }

        error_log(sprintf(
            'CKS GO user_account_deleted / id=%d / username=%s / email=%s / note=%.2f / pending_total=%.2f',
            $userId,
            (string)($user['username'] ?? ''),
            (string)($user['email'] ?? ''),
            $currentNote,
            $pendingTotal
        ));

        if (!User::deleteOwnAccountById($userId)) {
            redirectWithError("Impossible de supprimer le compte.", 'user', 'account');
            return;
        }

        session_unset();
        session_destroy();

        redirectWithSuccess("Votre compte a bien été supprimé.", 'home', 'index');
    }
}
