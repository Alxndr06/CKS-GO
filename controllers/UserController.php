<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../helpers/functions.php';

class UserController extends Controller
{
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

        $identifier = $_POST['email'];
        $password = $_POST['password'];

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::findByMail($identifier);
        } else {
            $user = User::findByUsername($identifier);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                redirectWithError("Votre compte n'est pas encore activé.", 'user', 'login');
                return;
            }

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'lastname' => $user['lastname'],
                'firstname' => $user['firstname'],
                'email' => $user['email'],
                'note' => $user['note'] ?? 0,
                'role' => $user['role'],
                'unit' => $user['unit'],
                'locked' => $user['locked'],
                'created_at' => $user['created_at'],
                'is_active' => $user['is_active']
            ];

            $_SESSION['last_activity'] = time();

            redirectWithSuccess("Connexion réussie !", 'home', 'index');
        } else {
            redirectWithError("Identifiants incorrects.", 'user', 'login');
        }
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

        if ($passwordRaw !== $confirmPasswordRaw) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (!User::checkUnicity('email', $email)) {
            $errors[] = "Cet email est déjà enregistré.";
        }

        if (!User::checkUnicity('username', $username)) {
            $errors[] = "Ce pseudo est déjà utilisé.";
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

        $password_hash = password_hash($passwordRaw, PASSWORD_DEFAULT);

        $success = User::create([
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'unit' => $unit,
            'password_hash' => $password_hash,
            'is_active' => 1,
            'activation_token' => $activation_token
        ]);

        if ($success) {
            redirectWithSuccess("Compte créé avec succès ! Vous pouvez vous connecter.", 'user', 'login');
        } else {
            redirectWithError("Une erreur est survenue lors de l'enregistrement.", 'user', 'register');
        }
    }

    public function deleteAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithError("Méthode non autorisée", 'user', 'allUsers');
            return;
        }

        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            redirectWithError("Accès refusé", 'home', 'index');
            return;
        }

        checkCsrfToken();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            redirectWithError("ID utilisateur invalide", 'user', 'allUsers');
            return;
        }

        try {
            User::deleteById($id);
            redirectWithSuccess("Utilisateur supprimé avec succès", 'user', 'allUsers');
        } catch (DomainException $e) {
            redirectWithError($e->getMessage(), 'user', 'allUsers');
        } catch (PDOException $e) {
            error_log("Erreur SQL suppression utilisateur #{$id} : " . $e->getMessage());
            redirectWithError("Erreur lors de la suppression de l'utilisateur", 'user', 'allUsers');
        }
    }

    public function logout(): void
    {
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

        $_SESSION['user']['note'] = $user['note'] ?? 0;
        $_SESSION['user']['firstname'] = $user['firstname'] ?? '';
        $_SESSION['user']['lastname'] = $user['lastname'] ?? '';
        $_SESSION['user']['email'] = $user['email'] ?? '';
        $_SESSION['user']['unit'] = $user['unit'] ?? '';
        $_SESSION['user']['role'] = $user['role'] ?? 'user';

        $recentOrders = Order::getRecentByUserId($userId, 5);
        $orderStats = Order::getUserStats($userId);

        self::render('user/dashboard', [
            'user' => $user,
            'recentOrders' => $recentOrders,
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
}