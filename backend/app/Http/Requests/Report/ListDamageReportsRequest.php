<?php

namespace App\Http\Requests\Report;

use App\Models\DamageReport;
use App\Services\GeoGeometryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDamageReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(DamageReport::STATUSES)],
            'damage_type' => ['nullable', 'string', Rule::in(DamageReport::DAMAGE_TYPES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'bbox' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value !== null && $value !== '') {
                        $parsed = app(GeoGeometryService::class)->parseBoundingBox((string) $value);
                        if ($parsed === null) {
                            $fail('The bbox parameter must be formatted as minLng,minLat,maxLng,maxLat within valid WGS 84 bounds.');
                        }
                    }
                },
            ],
            'q' => ['nullable', 'string', 'max:64'],
            'road_segment_id' => ['nullable', 'integer', 'exists:road_segments,id'],
            'mine' => ['nullable', 'boolean'],
            'include_demo' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

