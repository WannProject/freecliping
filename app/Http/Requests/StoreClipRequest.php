<?php

namespace App\Http\Requests;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Enums\SubtitleStyle;
use App\Support\Clips\YouTubeUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClipRequest extends FormRequest
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
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'gt:start_seconds'],
            'aspect_ratio' => ['sometimes', Rule::enum(ClipAspectRatio::class)],
            'quality' => ['sometimes', Rule::enum(ClipQuality::class)],
            'subtitles_enabled' => ['sometimes', 'boolean'],
            'subtitle_style' => ['sometimes', Rule::enum(SubtitleStyle::class)],
            'rights_confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rights_confirmed.accepted' => 'Konfirmasi bahwa kamu punya hak atau izin untuk memproses dan mengekspor konten ini.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $startSeconds = (int) $this->input('start_seconds', 0);
                $endSeconds = (int) $this->input('end_seconds', 0);

                if (($endSeconds - $startSeconds) > (int) config('freekliping.max_clip_length')) {
                    $validator->errors()->add(
                        'end_seconds',
                        'Panjang klip melebihi batas maksimum.',
                    );
                }
            },
        ];
    }
}
