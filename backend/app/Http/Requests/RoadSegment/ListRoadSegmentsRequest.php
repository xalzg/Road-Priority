<?php

namespace App\Http\Requests\RoadSegment;

use App\Models\RoadSegment;
use App\Services\GeoGeometryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRoadSegmentsRequest extends FormRequest
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
            'administrative_area_id' => ['nullable', 'integer', 'exists:administrative_areas,id'],
            'road_function' => ['nullable', 'string', Rule::in(RoadSegment::FUNCTIONS)],
            'road_authority' => ['nullable', 'string', Rule::in(RoadSegment::AUTHORITIES)],
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
            'q' => ['nullable', 'string', 'max:100'],
            'include_demo' => ['nullable', 'boolean'],
            'include_geometry' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
