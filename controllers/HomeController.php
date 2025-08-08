<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';

class HomeController extends Controller
{
    public function index() : void
    {
        checkSession();
        self::render('home/index');
    }

    public function about() : void
    {
        self::render('home/about');
    }
}