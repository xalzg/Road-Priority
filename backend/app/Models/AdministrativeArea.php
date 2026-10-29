<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'administrative_level',
        'parent_id',
        'external_code',
        'boundary_geojson',
        'is_study_area',
    ];

    protected $casts = [
        'boundary_geojson' => 'array',
        'is_study_area' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function roadSegments(): HasMany
    {
        return $this->hasMany(RoadSegment::class, 'administrative_area_id');
    }
}
