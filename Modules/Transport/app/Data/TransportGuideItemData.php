<?php

declare(strict_types=1);

namespace Modules\Transport\Data;

final readonly class TransportGuideItemData
{
    public function __construct(
        public ?int $productId,
        public string $code,
        public string $description,
        public int|float|string $quantity,
        public string $unitCode = 'NIU',
        public ?string $sunatProductCode = null,
    ) {}
}
