<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', Rule::in(['image/jpeg', 'image/png', 'application/pdf'])],
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'The task ID field is required.',
            'task_id.uuid' => 'The task ID must be a valid UUID.',
            'task_id.exists' => 'The selected task does not exist.',
            'filename.required' => 'The filename field is required.',
            'filename.string' => 'The filename must be a string.',
            'filename.max' => 'The filename may not be greater than 255 characters.',
            'mime_type.required' => 'The mime type field is required.',
            'mime_type.in' => 'The mime type must be image/jpeg, image/png, or application/pdf.',
        ];
    }
}
