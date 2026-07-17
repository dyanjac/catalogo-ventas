<?php

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Services\EconomicEventService;

class ProcessEconomicEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public int $organizationId, public int $eventId)
    {
        $this->onQueue(config('accounting.events.queue', 'accounting'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->organizationId.':'.$this->eventId;
    }

    public function handle(EconomicEventService $events): void
    {
        $events->process($this->organizationId, $this->eventId);
    }
}
