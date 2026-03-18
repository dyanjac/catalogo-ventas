<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\UnitMeasure;
use App\Models\User;
use Livewire\Component;

class DashboardScreen extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard-screen', [
            'stats' => [
                'customers' => User::query()
                    ->join('security_user_roles as user_roles', 'user_roles.user_id', '=', 'users.id')
                    ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.is_active', true)
                    ->where('roles.is_active', true)
                    ->where('roles.code', 'customer')
                    ->distinct('users.id')
                    ->count('users.id'),
                'orders' => Order::query()->count(),
                'products' => Product::query()->count(),
                'categories' => Category::query()->count(),
                'unitMeasures' => UnitMeasure::query()->count(),
                'lowStock' => Product::query()->whereColumn('stock', '<=', 'min_stock')->count(),
            ],
            'latestOrders' => Order::query()->with('user')->latest()->take(8)->get(),
            'lowStockProducts' => Product::query()
                ->with(['category', 'unitMeasure'])
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderBy('stock')
                ->take(8)
                ->get(),
        ]);
    }
}
