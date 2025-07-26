<?php
require_once 'core/Controller.php';

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