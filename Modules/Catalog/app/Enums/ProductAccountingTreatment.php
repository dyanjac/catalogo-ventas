<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum ProductAccountingTreatment: string
{
    case Inherit = 'HEREDAR';
    case Automatic = 'AUTOMATICO';
    case Manual = 'MANUAL';
    case NotApplicable = 'NO_APLICA';
    case PendingConfiguration = 'PENDIENTE_CONFIGURACION';

    public static function fromLegacyFlag(bool $requiresAccountingEntry): self
    {
        return $requiresAccountingEntry ? self::Automatic : self::NotApplicable;
    }

    public function label(): string
    {
        return match ($this) {
            self::Inherit => 'Heredar configuración',
            self::Automatic => 'Automático',
            self::Manual => 'Manual',
            self::NotApplicable => 'No aplica',
            self::PendingConfiguration => 'Pendiente de configuración',
        };
    }

    public function requiresLegacyAccountingEntry(): bool
    {
        return in_array($this, [self::Inherit, self::Automatic], true);
    }
}
