<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Subscriptions\Services\SubscriptionAccrualService;

class ProcessSubscriptionAccrualJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $organizationId, public readonly int $scheduleId)
    {
        $this->onQueue((string) config('subscriptions.queue', 'subscriptions'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "{$this->organizationId}:{$this->scheduleId}";
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SubscriptionAccrualService $service): void
    {
        $service->process($this->organizationId, $this->scheduleId);
    }
}
