<?php

declare(strict_types=1);

namespace Modules\Transport\Services\Contracts;

use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;

interface TransportGuideProviderInterface
{
    public function code(): string;

    /** @return array{ok: bool, message: string} */
    public function validateCredentials(TransportSetting $setting): array;

    /** @return array<string, mixed> */
    public function submit(TransportSetting $setting, TransportGuide $guide): array;

    /** @return array<string, mixed> */
    public function poll(TransportSetting $setting, TransportGuide $guide): array;
}
