<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersIndex extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'status', history: true, keep: true)]
    public string $status = '';

    #[Url(as: 'payment_status', history: true, keep: true)]
    public string $paymentStatus = '';

    public array $statusOptions = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'processing' => 'En proceso',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
    ];

    public array $paymentStatusOptions = [
        'pending' => 'Pendiente',
        'paid' => 'Pagado',
        'failed' => 'Fallido',
        'refunded' => 'Reembolsado',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'paymentStatus']);
        $this->resetPage();
    }

    public function render()
    {
        $orders = Order::query()
            ->with('user')
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $normalized = strtoupper(str_replace(' ', '', $search));

                $query->where(function ($sub) use ($search, $normalized) {
                    $sub->whereRaw("CONCAT(series, '-', LPAD(order_number, 8, '0')) LIKE ?", ['%' . $normalized . '%'])
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->paymentStatus !== '', fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.orders-index', [
            'orders' => $orders,
        ]);
    }
}
