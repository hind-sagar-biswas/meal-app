<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class AdjustExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'contributions' => ['required', 'array', 'min:1'],
            'contributions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'contributions.*.amount' => ['required', 'integer', 'not_in:0'],
        ];
    }
}
