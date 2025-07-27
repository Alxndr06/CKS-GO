<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';

class HomeController extends Controller
{
    public function index()
    {
        checkSession();
        self::render('home/index');
    }

    public function about()
    {
        self::render('home/about');
    }
}