<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'damage_report_id',
        'storage_disk',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'width',
        'height',
        'sha256',
        'is_public',
        'captured_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_public' => 'boolean',
        'captured_at' => 'datetime',
    ];

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class, 'damage_report_id');
    }
}

