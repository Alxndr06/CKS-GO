<?php
class Router {
    public function handleRequest()
    {
        // Lecture des parametres dans l'URL
        $controllerName = $_GET['controller'] ?? 'home';
        $action = $_GET['action'] ?? 'index';

        // Construction du nom du fichier controleur (On met la premiere lettre en majuscule
        $file = 'controllers/' . ucfirst($controllerName) . 'Controller.php';

        // On vérifie que le contrôleur existe bien
        if (!file_exists($file)) {
            die("Controller '{$controllerName}' not found");
        }

        // On inclu le fichier
        require_once $file;

        // On construit le nom de classe visée dans le controller (comme pour le controleur)
        $class = ucfirst($controllerName) . 'Controller';

        // Instanciation
        $controller = new $class();

        // Vérif que la méthode existe
        if (!method_exists($controller, $action)) {
            die("Action '$action' does not exist");
        }

        // On finit par appeler la méthode
        $controller->$action();
    }
}
