<?php

namespace App\Http\Requests\Report;

use App\Models\DamageReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', DamageReport::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPhotos = (int) config('reporting.max_photos_per_report', 5);
        $maxPhotoSizeKb = (int) config('reporting.max_photo_size_kb', 5120);

        return [
            'damage_type' => ['required', 'string', Rule::in(DamageReport::DAMAGE_TYPES)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'road_segment_id' => ['nullable', 'integer', 'exists:road_segments,id'],
            'photos' => ['nullable', 'array', "max:{$maxPhotos}"],
            'photos.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maxPhotoSizeKb}",
            ],
        ];
    }
}
