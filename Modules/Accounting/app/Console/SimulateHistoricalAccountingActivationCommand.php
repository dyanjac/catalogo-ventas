<?php

declare(strict_types=1);

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Modules\Accounting\Services\HistoricalAccountingActivationService;
use Throwable;

final class SimulateHistoricalAccountingActivationCommand extends Command
{
    protected $signature = 'accounting:history:simulate {organization : ID de organización} {cutoff : Fecha inclusiva YYYY-MM-DD}';
    protected $description = 'Simula y persiste la activación contable histórica sin publicar asientos';

    public function handle(HistoricalAccountingActivationService $service): int
    {
        try {
            $run = $service->simulate((int) $this->argument('organization'), (string) $this->argument('cutoff'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        $this->table(['Run', 'Estado', 'Elegibles', 'Existentes', 'Errores', 'Hash', 'Confirmación'], [[
            $run->id, $run->status, $run->eligible_count, $run->existing_count, $run->error_count,
            $run->simulation_hash, 'CONFIRMAR '.$run->confirmation_token,
        ]]);

        return $run->status === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
