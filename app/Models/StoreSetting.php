<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'origin_contact_name',
        'origin_contact_phone',
        'origin_area_id',
        'origin_area_name',
        'origin_postal_code',
        'origin_address',
    ];

    public static function current(): self
    {
        return static::query()->firstOrNew();
    }

    public function hasOrigin(): bool
    {
        return filled($this->origin_area_id);
    }
}
