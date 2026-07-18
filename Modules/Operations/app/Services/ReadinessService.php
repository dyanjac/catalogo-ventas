<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Operations\Models\OperationalReconciliationRun;
use Throwable;

final class ReadinessService
{
    /** @return array{ready:bool,checks:array<string,array{ok:bool,detail:string}>} */
    public function inspect(): array
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = ['ok' => true, 'detail' => 'available'];
        } catch (Throwable) {
            $checks['database'] = ['ok' => false, 'detail' => 'unavailable'];
        }

        try {
            $key = 'operations:readiness:'.bin2hex(random_bytes(8));
            Cache::put($key, true, 10);
            $cacheOk = Cache::pull($key) === true;
            $checks['cache'] = ['ok' => $cacheOk, 'detail' => $cacheOk ? 'read_write' : 'write_failed'];
        } catch (Throwable) {
            $checks['cache'] = ['ok' => false, 'detail' => 'unavailable'];
        }

        $required = ['organizations', 'inventory_balances', 'inventory_movements', 'accounting_economic_events', 'accounting_entries', 'operational_reconciliation_runs'];
        try {
            $missing = array_values(array_filter($required, fn (string $table): bool => ! Schema::hasTable($table)));
            $checks['schema'] = ['ok' => $missing === [], 'detail' => $missing === [] ? 'current' : 'missing:'.implode(',', $missing)];
        } catch (Throwable) {
            $checks['schema'] = ['ok' => false, 'detail' => 'unavailable'];
        }

        if ((bool) config('operations.readiness.require_recent_reconciliation', false)) {
            try {
                $activeOrganizations = $checks['schema']['ok']
                    ? Organization::query()->where('status', 'active')->count()
                    : 0;
                $latestRunIds = $activeOrganizations > 0
                    ? OperationalReconciliationRun::query()
                        ->whereIn('organization_id', Organization::query()->select('id')->where('status', 'active'))
                        ->selectRaw('MAX(id) AS id')
                        ->groupBy('organization_id')
                        ->pluck('id')
                    : collect();
                $freshOrganizations = $latestRunIds->isNotEmpty()
                    ? OperationalReconciliationRun::query()
                        ->whereIn('id', $latestRunIds)
                        ->whereIn('status', ['passed', 'degraded'])
                        ->where('finished_at', '>=', now()->subMinutes((int) config('operations.readiness.max_reconciliation_age_minutes', 30)))
                        ->count()
                    : 0;
                $fresh = $activeOrganizations > 0 && $freshOrganizations === $activeOrganizations;
                $checks['reconciliation'] = [
                    'ok' => $fresh,
                    'detail' => $fresh ? 'all_active_organizations_recent' : "fresh:{$freshOrganizations}/{$activeOrganizations}",
                ];
            } catch (Throwable) {
                $checks['reconciliation'] = ['ok' => false, 'detail' => 'unavailable'];
            }
        }

        return ['ready' => collect($checks)->every(fn (array $check): bool => $check['ok']), 'checks' => $checks];
    }
}
