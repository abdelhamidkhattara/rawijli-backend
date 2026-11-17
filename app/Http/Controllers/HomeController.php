<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect((string) config('services.rawijli.frontend_url', '/admin'));
    }
}
