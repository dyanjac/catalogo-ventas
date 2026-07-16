<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum ProductType: string
{
    case PhysicalGood = 'bien_fisico';
    case Service = 'servicio';
    case Subscription = 'suscripcion';
    case Digital = 'digital';
    case Kit = 'kit';
    case Informational = 'informativo';

    public function label(): string
    {
        return match ($this) {
            self::PhysicalGood => 'Bien físico',
            self::Service => 'Servicio',
            self::Subscription => 'Suscripción',
            self::Digital => 'Digital',
            self::Kit => 'Kit',
            self::Informational => 'Informativo',
        };
    }

    public function tracksInventory(): bool
    {
        return in_array($this, [self::PhysicalGood, self::Kit], true);
    }
}
