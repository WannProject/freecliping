<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClipFilenameRequest extends FormRequest
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
            'file_name' => [
                'required',
                'string',
                'min:1',
                'max:120',
                'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_name.regex' => 'Nama file hanya boleh memakai huruf, angka, spasi, titik, garis bawah, dan strip.',
        ];
    }
}
