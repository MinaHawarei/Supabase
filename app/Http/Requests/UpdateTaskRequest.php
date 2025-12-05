<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'due_date.date' => 'The due date must be a valid date.',
            'priority.in' => 'The priority must be low, medium, or high.',
            'is_completed.boolean' => 'The is_completed field must be true or false.',
        ];
    }
}
