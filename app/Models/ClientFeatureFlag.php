<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFeatureFlag extends Model
{
    protected $table = 'client_feature_flags';
    protected $fillable = ['cliente','feature_key','enabled','meta'];

    protected $casts = [
        'enabled' => 'boolean',
        'meta'    => 'array',
    ];

    public static function isEnabled(string $cliente, string $featureKey, bool $default = false): bool
    {
        $row = self::where('cliente', $cliente)
            ->where('feature_key', $featureKey)
            ->first();

        return $row ? (bool)$row->enabled : $default;
    }

    public static function set(string $cliente, string $featureKey, bool $enabled, array $meta = null): self
    {
        return self::updateOrCreate(
            ['cliente' => $cliente, 'feature_key' => $featureKey],
            ['enabled' => $enabled, 'meta' => $meta]
        );
    }
}
