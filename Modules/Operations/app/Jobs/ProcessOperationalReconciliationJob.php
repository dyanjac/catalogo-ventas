<?php

declare(strict_types=1);

namespace Modules\Operations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Operations\Services\OperationalReconciliationService;
use Throwable;

final class ProcessOperationalReconciliationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $organizationId, public string $trigger = 'scheduled', public ?int $actorId = null)
    {
        $this->onQueue((string) config('operations.reconciliation.queue', 'operations'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->organizationId;
    }

    public function handle(OperationalReconciliationService $reconciliation): void
    {
        $reconciliation->run($this->organizationId, $this->trigger, $this->actorId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('operations')->critical('erp.reconciliation.job_failed', [
            'organization_id' => $this->organizationId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
