<?php

declare(strict_types=1);

namespace Modules\Operations\Console;

use Illuminate\Console\Command;
use Modules\Operations\Services\ReadinessService;

final class OperationsDoctorCommand extends Command
{
    protected $signature = 'operations:doctor';

    protected $description = 'Valida dependencias operativas y seguridad temporal de las colas';

    public function handle(ReadinessService $readiness): int
    {
        $result = $readiness->inspect();
        foreach ($result['checks'] as $name => $check) {
            $this->line(sprintf('[%s] %s: %s', $check['ok'] ? 'OK' : 'FAIL', $name, $check['detail']));
        }

        $connection = (string) config('queue.default');
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 0);
        $required = (int) config('operations.queue.required_retry_after', 960);
        $safe = $connection === 'sync' || $retryAfter >= $required;
        $this->line(sprintf('[%s] queue.retry_after: %s (requerido >= %d)', $safe ? 'OK' : 'FAIL', $connection === 'sync' ? 'sync' : $retryAfter, $required));

        return $result['ready'] && $safe ? self::SUCCESS : self::FAILURE;
    }
}
