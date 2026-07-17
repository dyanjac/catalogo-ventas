<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\Orders\Entities\Order;
use Modules\Sales\Http\Controllers\SalesPosController;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $productId, $quantity, $key] = $argv;
$user = auth()->loginUsingId((int) $userId);

try {
    $request = Request::create('/admin/sales/pos', 'POST', [
        'document_type' => 'order',
        'currency' => 'PEN',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'idempotency_key' => $key,
        'customer' => ['name' => 'MySQL POS Phase 06'],
        'items' => [['product_id' => (int) $productId, 'quantity' => (int) $quantity, 'unit_price' => 10]],
    ]);
    $request->setUserResolver(fn () => $user);
    app()->instance('request', $request);
    app(SalesPosController::class)->store(
        $request,
        app(ElectronicBillingService::class),
        app(SalesAccountingService::class),
    );
    $order = Order::query()
        ->where('organization_id', $user->organization_id)
        ->where('sales_channel', 'pos')
        ->where('idempotency_key', $key)
        ->firstOrFail();
    fwrite(STDOUT, (string) $order->id);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class.':'.$exception->getMessage());
    exit(2);
}
