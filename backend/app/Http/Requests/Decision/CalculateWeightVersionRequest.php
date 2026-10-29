<?php

namespace App\Http\Requests\Decision;

use App\Models\CriterionWeightVersion;
use Illuminate\Foundation\Http\FormRequest;

class CalculateWeightVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var CriterionWeightVersion|null $weightVersion */
        $weightVersion = $this->route('criterionWeightVersion');

        return $user !== null
            && $weightVersion !== null
            && $user->can('calculate', $weightVersion);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criterion_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'criterion_ids.*' => ['integer', 'exists:decision_criteria,id'],
            'comparisons' => ['present', 'array'],
            'comparisons.*.criterion_a_id' => ['required', 'integer', 'exists:decision_criteria,id'],
            'comparisons.*.criterion_b_id' => ['required', 'integer', 'exists:decision_criteria,id', 'different:comparisons.*.criterion_a_id'],
            'comparisons.*.comparison_value' => ['required', 'numeric', 'gt:0'],
            'comparisons.*.expert_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
