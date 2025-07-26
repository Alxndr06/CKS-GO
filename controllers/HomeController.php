<?php
require_once 'core/Controller.php';

class HomeController extends Controller
{
    public function index()
    {
        isUserLoggedIn();
        $csrf_token = getCsrfToken();
        self::render('home/index', ['csrf_token' => $csrf_token]);
    }

    public function about()
    {
        self::render('home/about');
    }
}