<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/functions.php';

class UserController extends Controller
{
    public function login() : void
    {
        checkSession();
        redirectIfConnected('Vous êtes déjà connecté.');
        $csrf_token = getCsrfToken();
        self::render('user/login', ['csrf_token' => $csrf_token]);
    }

    public function doLogin() : void
    {
        checkSession();
        checkCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $identifier = $_POST['email']; // champ email OU pseudo (thug life)
            $password = $_POST['password'];

            // Détection email / pseudo
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $user = User::findByMail($identifier);
            } else {
                $user = User::findByUsername($identifier);
            }

            if ($user && password_verify($password, $user['password'])) {

                if (!$user['is_active']) {
                    redirectWithError("Votre compte n'est pas encore activé.", 'user', 'login');
                }

                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'lastname' => $user['lastname'],
                    'firstname' => $user['firstname'],
                    'email' => $user['email'],
                    'note' => $user['note'],
                    'total_spent' => $user['total_spent'],
                    'role' => $user['role'],
                    'unit' => $user['unit'],
                    'locked' => $user['locked'],
                    'created_at' => $user['created_at'],
                    'is_active' => $user['is_active']
                ];
                $_SESSION['last_activity'] = time();

                redirectWithSuccess("Connexion réussie !", 'home', 'index');
                exit();
            } else {
                redirectWithError("Identifiants incorrects.", 'user', 'login');
            }
        }

    }

    public function register() : void
    {
        checkSession();
        redirectIfConnected('Impossible car déjà connecté.');
            $csrf_token = getCsrfToken();
            self::render('user/register', ['csrf_token' => $csrf_token]);
    }

    public function doRegister() : void
    {
        checkSession();
        checkCsrfToken();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = preg_replace('/\s+/', '', trim($_POST['username']));
            $lastname = trim($_POST['lastname']);
            $firstname = trim($_POST['firstname']);
            $email = strtolower(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
            $passwordRaw = $_POST['password'];
            $confirmPasswordRaw = $_POST['confirmPassword'];
            $unit = $_POST['unit'];
            $is_active = true; // Vrai par défaut
            $activation_token = bin2hex(random_bytes(32));

            $errors = [];

            if (empty($username) || empty($lastname) || empty($email) || empty($passwordRaw)) {
                $errors[] ="Merci de remplir tous les champs obligatoires.";
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
            if (!in_array($unit, $allowed_units)) {
                $errors[] = "Service sélectionné invalide.";
            }

            if (!empty($errors)) {
                redirectWithError(implode('<br>', $errors), 'user', 'register');
                return;
            }

            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

            $success = User::create([
                'username' => $username,
                'lastname' => $lastname,
                'firstname' => $firstname,
                'email' => $email,
                'password' => $password,
                'is_active' => $is_active,
                'activation_token' => $activation_token
            ]);

            if ($success) {
                redirectWithSuccess("Compte créé avec succès ! Vous pouvez vous connecter.", 'user', 'login');
            } else {
                redirectWithError("Une erreur est survenue lors de l'enregistrement.", 'user', 'register');
            }
        }
    }

    public function logout() : void
    {
        if (!isUserLoggedIn()) {
            redirectWithError("Vous n'êtes pas connecté. Vous n'avez pas accès à cette fonctionnalité.", 'home', 'index');
        }

        checkSession();
        session_unset();
        session_destroy();
        redirectWithSuccess('Déconnexion avec succès.', 'home', 'index');
    }


    public function allUsers() : void
    {
        self::render('user/all_users');
    }

    public function dashboard() : void
    {
        checkConnect();
        self::render('user/dashboard');
    }

}
