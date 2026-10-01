<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'month_id' => ['nullable', 'integer', 'exists:months,id'],
            'date' => ['nullable', 'date'],
            'cause' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_grouped' => ['nullable', 'boolean'],
            'amount' => ['required', 'integer', 'min:1'],
            'contributions' => ['required', 'array', 'min:1'],
            'contributions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'contributions.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
