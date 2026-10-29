<?php

namespace App\Http\Requests\Report;

use App\Models\DamageReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var DamageReport|null $report */
        $report = $this->route('damageReport');

        return $actor !== null && $report !== null && $actor->can('updateStatus', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_status' => ['required', 'string', Rule::in(DamageReport::STATUSES)],
            'notes' => [
                Rule::requiredIf(function (): bool {
                    return in_array((string) $this->input('new_status'), [
                        DamageReport::STATUS_REJECTED,
                        DamageReport::STATUS_DUPLICATE,
                        DamageReport::STATUS_NEEDS_INFO,
                        DamageReport::STATUS_RESOLVED,
                    ], true);
                }),
                'nullable',
                'string',
                'max:1000',
            ],
            'road_segment_id' => ['nullable', 'integer', 'exists:road_segments,id'],
            'duplicate_of_id' => [
                Rule::requiredIf(fn (): bool => (string) $this->input('new_status') === DamageReport::STATUS_DUPLICATE),
                'nullable',
                'integer',
                'exists:damage_reports,id',
            ],
            'publish_photos' => ['sometimes', 'boolean'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }
}
