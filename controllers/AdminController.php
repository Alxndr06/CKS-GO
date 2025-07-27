<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';

class AdminController extends Controller
{
    public function dashboard()
    {
        checkRole('admin');
        self::render('admin/dashboard');
    }
}