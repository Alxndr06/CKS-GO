<?php

class Controller
{
    public static function render($view, $data = [])
    {
        extract($data);

        $file = "views/$view.php";

        if (file_exists($file)) {
            require $file;
        } else {
            die("View $view not found!");
        }
    }
}