<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Thikr;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return view('home', [
            'athkar' => Thikr::defaultsPayload(),
            'athkarSettings' => Setting::query()
                ->whereIn('name', array_keys(Setting::defaults()))
                ->pluck('value', 'name')
                ->all(),
        ]);
    }
}
