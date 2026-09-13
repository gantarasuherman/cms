<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\HomepageReader;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomepageReader $homepage): View
    {
        return view('public.home', [
            'sections' => $homepage->sections(),
        ]);
    }
}

