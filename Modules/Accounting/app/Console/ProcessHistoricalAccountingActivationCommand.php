<?php

declare(strict_types=1);

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Modules\Accounting\Jobs\ProcessHistoricalAccountingActivationJob;
use Modules\Accounting\Models\AccountingActivationRun;
use Modules\Accounting\Services\HistoricalAccountingActivationService;
use Throwable;

final class ProcessHistoricalAccountingActivationCommand extends Command
{
    protected $signature = 'accounting:history:process {organization : ID de organización} {run : ID de corrida} {--confirmation=} {--hash=} {--sync : Procesar en este proceso}';
    protected $description = 'Confirma y procesa una simulación histórica sellada';

    public function handle(HistoricalAccountingActivationService $service): int
    {
        try {
            $run = AccountingActivationRun::query()->where('organization_id', (int) $this->argument('organization'))
                ->findOrFail((int) $this->argument('run'));
            if ($run->status === 'simulated') {
                $run = $service->confirm($run, (string) $this->option('confirmation'), (string) $this->option('hash'), null);
            }
            if ($run->status === 'completed') {
                $this->info("Corrida {$run->id} ya completada; no se publicaron duplicados.");

                return self::SUCCESS;
            }
            if (! in_array($run->status, ['confirmed', 'failed'], true)) {
                throw new \DomainException('La corrida no está confirmada ni disponible para reproceso.');
            }
            if ($this->option('sync')) {
                $run = $service->process($run);
                $this->info("Corrida {$run->id} completada con {$run->processed_count} evento(s).");
            } else {
                ProcessHistoricalAccountingActivationJob::dispatch((int) $run->organization_id, (int) $run->id);
                $this->info("Corrida {$run->id} encolada.");
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
