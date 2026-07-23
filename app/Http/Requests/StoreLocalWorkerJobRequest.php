<?php

namespace App\Http\Requests;

use App\Enums\ClipAspectRatio;
use App\Enums\ClipQuality;
use App\Support\Clips\YouTubeUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLocalWorkerJobRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'gt:start_seconds'],
            'aspect_ratio' => ['sometimes', Rule::enum(ClipAspectRatio::class)],
            'quality' => ['sometimes', Rule::enum(ClipQuality::class)],
            'subtitles_enabled' => ['sometimes', 'boolean'],
            'sync_output' => ['sometimes', 'boolean'],
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
                $durationSeconds = $this->integer('duration_seconds');

                if (($endSeconds - $startSeconds) > (int) config('freekliping.max_clip_length')) {
                    $validator->errors()->add(
                        'end_seconds',
                        'Panjang klip melebihi batas maksimum.',
                    );
                }

                if ($durationSeconds > 0 && $endSeconds > $durationSeconds) {
                    $validator->errors()->add(
                        'end_seconds',
                        'Titik akhir klip melewati durasi video.',
                    );
                }
            },
        ];
    }
}
