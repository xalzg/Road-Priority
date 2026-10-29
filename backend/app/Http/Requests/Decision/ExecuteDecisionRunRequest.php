<?php

namespace App\Http\Requests\Decision;

use App\Models\DecisionRun;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteDecisionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', DecisionRun::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'criterion_weight_version_id' => ['required', 'integer', 'exists:criterion_weight_versions,id'],
            'is_simulation' => ['sometimes', 'boolean'],
            'segment_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'segment_ids.*' => ['integer', 'exists:road_segments,id'],
            'parameters' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
