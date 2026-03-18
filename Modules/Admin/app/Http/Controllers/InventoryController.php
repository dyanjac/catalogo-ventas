<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('admin.inventory.index');
    }
}
