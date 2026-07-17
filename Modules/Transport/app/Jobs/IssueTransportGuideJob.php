<?php

declare(strict_types=1);

namespace Modules\Transport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Transport\Services\TransportGuideService;
use Throwable;

class IssueTransportGuideJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $organizationId, public int $guideId) {}

    public function uniqueId(): string
    {
        return $this->organizationId.':'.$this->guideId;
    }

    public function handle(TransportGuideService $service): void
    {
        $service->issue($this->organizationId, $this->guideId);
    }

    public function failed(?Throwable $exception): void
    {
        app(TransportGuideService::class)->markSubmissionUncertain(
            $this->organizationId,
            $this->guideId,
            $exception?->getMessage() ?? 'El worker termino sin confirmar el resultado del envio.',
        );
    }
}
