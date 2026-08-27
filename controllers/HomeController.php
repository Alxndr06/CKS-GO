<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/News.php';
require_once __DIR__ . '/../models/Product.php';

class HomeController extends Controller
{
    public function index() : void
    {
        $newsAudience = currentUserCan('staff.access')
            ? 'staff'
            : (isUserLoggedIn() ? 'authenticated' : 'all');
        $latestNews = News::getLatestPublished(5, $newsAudience);
        $productAudience = isStaff() ? 'staff' : (isUserLoggedIn() ? 'authenticated' : 'guest');
        $latestProducts = Product::getLatestPublicProducts(4, $productAudience);

        self::render('home/index', [
            'latestNews' => $latestNews,
            'latestProducts' => $latestProducts
        ]);
    }

    public function maintenance(): void
    {
        self::render('home/maintenance');
    }

    public function about() : void
    {
        self::render('home/about');
    }

    public function legal() : void
    {
        self::render('home/legal_notice');
    }

    public function privacy() : void
    {
        self::render('home/privacy_policy');
    }

}
