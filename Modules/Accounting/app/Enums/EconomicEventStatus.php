<?php

namespace Modules\Accounting\Enums;

enum EconomicEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Error = 'error';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'Procesando',
            self::Processed => 'Procesado',
            self::Error => 'Error',
            self::Reversed => 'Revertido',
        };
    }
}
