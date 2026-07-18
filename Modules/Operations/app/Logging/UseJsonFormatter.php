<?php

declare(strict_types=1);

namespace Modules\Operations\Logging;

use Monolog\Formatter\JsonFormatter;
use Illuminate\Log\Logger;

final class UseJsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        }
    }
}
