<?php

namespace App\Http\Requests\Decision;

use App\Models\CriterionWeightVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeightVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', CriterionWeightVersion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version_name' => ['required', 'string', 'max:150'],
            'aggregation_method' => ['sometimes', 'nullable', 'string', Rule::in(['geometric_mean_aij', 'single_expert'])],
        ];
    }
}

