<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'assignee_id' => ['required', 'uuid'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'assignee_id.required' => 'The assignee ID field is required.',
            'assignee_id.uuid' => 'The assignee ID must be a valid UUID.',
            'due_date.required' => 'The due date field is required.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be today or later.',
            'priority.required' => 'The priority field is required.',
            'priority.in' => 'The priority must be low, medium, or high.',
            'attachment.file' => 'The attachment must be a file.',
            'attachment.mimes' => 'The attachment must be a JPEG, PNG, or PDF file.',
            'attachment.max' => 'The attachment may not be greater than 10 MB.',
        ];
    }
}
