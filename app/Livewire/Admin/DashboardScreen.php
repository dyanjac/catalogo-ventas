<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\UnitMeasure;
use App\Models\User;
use Livewire\Component;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Security\Services\SecurityScopeService;

class DashboardScreen extends Component
{
    public function render(SecurityScopeService $scopeService)
    {
        $actor = auth()->user();

        $customersQuery = User::query()
            ->join('security_user_roles as user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->where('roles.code', 'customer')
            ->distinct('users.id');

        $ordersQuery = $scopeService->scopeOrders(Order::query(), $actor, 'sales');
        $productsQuery = $scopeService->scopeProducts(Product::query(), $actor, 'catalog');
        $lowStockQuery = $scopeService->scopeInventoryStocks(ProductBranchStock::query(), $actor, 'inventory')
            ->with(['product.category', 'product.unitMeasure', 'branch'])
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock');

        return view('livewire.admin.dashboard-screen', [
            'stats' => [
                'customers' => $scopeService->scopeUsers($customersQuery, $actor, 'customers')->count('users.id'),
                'orders' => (clone $ordersQuery)->count(),
                'products' => (clone $productsQuery)->count(),
                'categories' => Category::query()->forCurrentOrganization()->count(),
                'unitMeasures' => UnitMeasure::query()->forCurrentOrganization()->count(),
                'lowStock' => (clone $lowStockQuery)->count(),
            ],
            'latestOrders' => (clone $ordersQuery)->with('user')->latest()->take(8)->get(),
            'lowStockProducts' => (clone $lowStockQuery)->orderBy('stock')->take(8)->get(),
        ]);
    }
}
