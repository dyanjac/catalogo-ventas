<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Transport\Enums\TransportEnvironment;

class TransportSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'enabled', 'environment', 'provider', 'dispatch_mode', 'queue_connection',
        'queue_name', 'sender_series', 'carrier_series', 'allow_carrier_without_sender',
        'provider_credentials', 'credentials_hash', 'credentials_validated_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'environment' => TransportEnvironment::class,
        'allow_carrier_without_sender' => 'boolean',
        'provider_credentials' => 'encrypted:array',
        'credentials_validated_at' => 'datetime',
    ];

    public function productionCredentialsAreValid(): bool
    {
        if (! $this->credentials_validated_at || ! $this->credentials_hash) {
            return false;
        }

        $current = hash('sha256', json_encode($this->provider_credentials ?? [], JSON_THROW_ON_ERROR));

        return hash_equals((string) $this->credentials_hash, $current);
    }

    public function configurationFingerprint(): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive([
            'provider' => $this->provider,
            'environment' => $this->environment->value,
            'credentials' => $this->provider_credentials ?? [],
            'sender_series' => $this->sender_series,
            'carrier_series' => $this->carrier_series,
        ]), JSON_THROW_ON_ERROR));
    }
}
