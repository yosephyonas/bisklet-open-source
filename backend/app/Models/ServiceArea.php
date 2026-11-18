<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceArea extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'polygon',
        'fill_color',
        'stroke_color',
        'is_active',
    ];

    protected $casts = [
        'polygon' => 'array',
        'is_active' => 'boolean',
    ];

    public static function active(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();
    }
}
