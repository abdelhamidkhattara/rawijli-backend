<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BootstrapService;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, BootstrapService $bootstrap)
    {
        return response()->json(['data' => $bootstrap->for($request->user())]);
    }
}
