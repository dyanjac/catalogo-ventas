<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\UnitMeasure;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'customers' => User::where('role', 'customer')->count(),
                'orders' => Order::count(),
                'products' => Product::count(),
                'categories' => Category::count(),
                'unitMeasures' => UnitMeasure::count(),
                'lowStock' => Product::query()->whereColumn('stock', '<=', 'min_stock')->count(),
            ],
            'latestOrders' => Order::with('user')->latest()->take(8)->get(),
            'lowStockProducts' => Product::with(['category', 'unitMeasure'])
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderBy('stock')
                ->take(8)
                ->get(),
        ]);
    }
}
