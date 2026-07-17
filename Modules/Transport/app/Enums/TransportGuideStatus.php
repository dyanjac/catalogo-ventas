<?php

declare(strict_types=1);

namespace Modules\Transport\Enums;

enum TransportGuideStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Queued = 'queued';
    case Submitting = 'submitting';
    case Uncertain = 'uncertain';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case AcceptedWithObservation = 'accepted_with_observation';
    case Rejected = 'rejected';
    case Error = 'error';
    case Voided = 'voided';

    public function isFinal(): bool
    {
        return in_array($this, [self::Uncertain, self::Accepted, self::AcceptedWithObservation, self::Rejected, self::Voided], true);
    }
}
