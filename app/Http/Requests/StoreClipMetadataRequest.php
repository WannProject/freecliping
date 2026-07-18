<?php

namespace App\Http\Requests;

use App\Support\Clips\YouTubeUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClipMetadataRequest extends FormRequest
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
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! is_string($value) || YouTubeUrl::videoId($value) === null) {
                        $fail('Gunakan link YouTube dari youtube.com, youtu.be, atau Shorts.');
                    }
                },
            ],
        ];
    }
}
