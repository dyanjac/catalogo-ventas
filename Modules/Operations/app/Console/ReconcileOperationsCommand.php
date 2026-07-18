<?php

declare(strict_types=1);

namespace Modules\Operations\Console;

use App\Models\Organization;
use Illuminate\Console\Command;
use Modules\Operations\Jobs\ProcessOperationalReconciliationJob;
use Modules\Operations\Services\OperationalReconciliationService;

final class ReconcileOperationsCommand extends Command
{
    protected $signature = 'operations:reconcile {organization? : ID de organización} {--all : Todas las organizaciones activas} {--sync : Ejecutar en el proceso actual}';

    protected $description = 'Ejecuta o encola la conciliación integral de inventario, documentos y contabilidad';

    public function handle(OperationalReconciliationService $reconciliation): int
    {
        $failed = false;
        $ids = $this->option('all')
            ? Organization::query()->where('status', 'active')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)
            : collect([(int) $this->argument('organization')])->filter();

        if ($ids->isEmpty()) {
            $this->error('Indique una organización o use --all.');

            return self::INVALID;
        }

        foreach ($ids as $organizationId) {
            Organization::query()->findOrFail($organizationId);
            if ($this->option('sync')) {
                $run = $reconciliation->run($organizationId, 'console');
                $this->line("Organización {$organizationId}: corrida {$run->id}, estado {$run->status}.");
                $failed = $failed || in_array($run->status, ['failed', 'error'], true);
            } else {
                ProcessOperationalReconciliationJob::dispatch($organizationId, 'scheduled');
                $this->line("Organización {$organizationId}: conciliación encolada.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
