<?php

namespace App\Http\Requests;

use App\Enums\LocalWorkerJobStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocalWorkerJobRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:40', 'max:128'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    LocalWorkerJobStatus::Processing->value,
                    LocalWorkerJobStatus::Completed->value,
                    LocalWorkerJobStatus::Failed->value,
                    LocalWorkerJobStatus::Cancelled->value,
                ]),
            ],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'local_output_path' => ['nullable', 'string', 'max:2048'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
