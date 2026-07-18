<?php

declare(strict_types=1);

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Models\AccountingActivationRun;
use Modules\Accounting\Services\HistoricalAccountingActivationService;

final class ProcessHistoricalAccountingActivationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;
    public int $uniqueFor = 1800;

    public function __construct(public int $organizationId, public int $runId)
    {
        $this->onQueue(config('accounting.events.queue', 'accounting'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->organizationId.':historical:'.$this->runId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(HistoricalAccountingActivationService $service): void
    {
        $run = AccountingActivationRun::query()->where('organization_id', $this->organizationId)->findOrFail($this->runId);
        $service->process($run);
    }
}
