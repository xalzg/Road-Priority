<?php

namespace App\Http\Requests\Criterion;

use App\Models\DecisionCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDecisionCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DecisionCriterion|null $criterion */
        $criterion = $this->route('decisionCriterion');

        return $user !== null && $criterion !== null && $user->can('update', $criterion);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DecisionCriterion|null $criterion */
        $criterion = $this->route('decisionCriterion');
        $criterionId = $criterion ? $criterion->id : null;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('decision_criteria', 'code')->ignore($criterionId),
            ],
            'name' => ['sometimes', 'string', 'max:191'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'data_type' => ['sometimes', 'string', Rule::in(DecisionCriterion::DATA_TYPES)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'criterion_type' => ['sometimes', 'string', Rule::in(DecisionCriterion::CRITERION_TYPES)],
            'minimum_value' => ['sometimes', 'numeric'],
            'maximum_value' => ['sometimes', 'numeric'],
            'aggregation_rule' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

