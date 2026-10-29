<?php

namespace App\Http\Requests\Criterion;

use App\Models\DecisionCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDecisionCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', DecisionCriterion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', 'unique:decision_criteria,code'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:2000'],
            'data_type' => ['required', 'string', Rule::in(DecisionCriterion::DATA_TYPES)],
            'unit' => ['nullable', 'string', 'max:64'],
            'criterion_type' => ['required', 'string', Rule::in(DecisionCriterion::CRITERION_TYPES)],
            'minimum_value' => ['required', 'numeric'],
            'maximum_value' => ['required', 'numeric', 'gte:minimum_value'],
            'aggregation_rule' => ['nullable', 'string', 'max:191'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
