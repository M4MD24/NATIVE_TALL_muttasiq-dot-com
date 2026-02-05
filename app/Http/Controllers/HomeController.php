<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return view('home', [
            'settings' => \App\Models\Setting::query()
                ->whereIn('name', array_keys(\App\Models\Setting::defaults()))
                ->pluck('value', 'name')
                ->all(),
        ]);
    }
}
