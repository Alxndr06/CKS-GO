<?php

class Controller
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $file = "views/$view.php";

        if (file_exists($file)) {
            require $file;
            return;
        }

        error_log("View not found: {$file}");
        self::renderError(500);
    }

    public static function renderError(int $code = 500, array $data = []): void
    {
        http_response_code($code);

        extract($data, EXTR_SKIP);

        $file = "views/errors/{$code}.php";

        if (file_exists($file)) {
            require $file;
            return;
        }

        $message = $code === 404
            ? "Page introuvable."
            : "Une erreur interne est survenue.";

        echo "<h1>Erreur {$code}</h1>";
        echo "<p>{$message}</p>";
    }
}
