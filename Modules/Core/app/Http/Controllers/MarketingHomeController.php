<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MarketingHomeController extends Controller
{
    public function __invoke(): View
    {
        return view('marketing.home');
    }
}
