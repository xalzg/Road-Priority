<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadSegment extends Model
{
    use HasFactory;

    public const FUNCTIONS = ['arteri', 'kolektor', 'lokal', 'lingkungan'];
    public const AUTHORITIES = [
        'nasional',
        'provinsi',
        'kota',
        'kabupaten',
        'kawasan_pengembang',
        'belum_terverifikasi',
    ];

    protected $fillable = [
        'administrative_area_id',
        'name',
        'road_reference_code',
        'geometry_geojson',
        'centroid_latitude',
        'centroid_longitude',
        'length_meters',
        'road_function',
        'road_authority',
        'nearest_facility_meters',
        'source_name',
        'source_reference',
        'data_origin',
        'is_demo',
        'is_active',
        'last_verified_at',
    ];

    protected $casts = [
        'geometry_geojson' => 'array',
        'centroid_latitude' => 'float',
        'centroid_longitude' => 'float',
        'length_meters' => 'float',
        'nearest_facility_meters' => 'float',
        'is_demo' => 'boolean',
        'is_active' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    public function administrativeArea(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class, 'administrative_area_id');
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'road_segment_id');
    }

    public function damageAssessments(): HasMany
    {
        return $this->hasMany(DamageAssessment::class, 'road_segment_id');
    }

    public function decisionRunItems(): HasMany
    {
        return $this->hasMany(DecisionRunItem::class, 'road_segment_id');
    }
}

