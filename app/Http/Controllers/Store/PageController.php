<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard');
    }

    public function faq(): Response
    {
        return Inertia::render('Store/Pages/Faq');
    }

    public function shipping(): Response
    {
        return Inertia::render('Store/Pages/Shipping');
    }

    public function returns(): Response
    {
        return Inertia::render('Store/Pages/Returns');
    }

    public function about(): Response
    {
        return Inertia::render('Store/Pages/About');
    }
}