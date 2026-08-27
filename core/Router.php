<?php

class Router
{
    public function handleRequest(): void
    {
        $controllerName = $_GET['controller'] ?? 'home';
        $action = $_GET['action'] ?? 'index';

        $controllerName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$controllerName);
        $action = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$action);

        $file = 'controllers/' . ucfirst($controllerName) . 'Controller.php';
        $class = ucfirst($controllerName) . 'Controller';

        if (!file_exists($file)) {
            error_log("Controller not found: {$controllerName} ({$file})");
            Controller::renderError(404);
            return;
        }

        require_once $file;

        if (!class_exists($class)) {
            error_log("Controller class not found after require: {$class}");
            Controller::renderError(500);
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            error_log("Action not found: {$class}::{$action}");
            Controller::renderError(404);
            return;
        }

        $method = new ReflectionMethod($controller, $action);

        if (
            !$method->isPublic()
            || $method->isStatic()
            || $method->getDeclaringClass()->getName() !== $class
            || $method->getNumberOfRequiredParameters() > 0
            || str_starts_with($action, '__')
        ) {
            error_log("Action not routable: {$class}::{$action}");
            Controller::renderError(404);
            return;
        }

        try {
            $controller->$action();
        } catch (Throwable $e) {
            error_log(sprintf(
                'Unhandled exception in router for %s::%s — %s in %s:%d',
                $class,
                $action,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            Controller::renderError(500);
        }
    }
}
