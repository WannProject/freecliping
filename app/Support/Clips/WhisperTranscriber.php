<?php

namespace App\Support\Clips;

use App\Models\ClipAnalysis;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class WhisperTranscriber
{
    /**
     * @return array{content: string, language: string}
     */
    public function transcribe(ClipAnalysis $analysis, string $workDirectory): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Whisper belum diaktifkan untuk fallback transcript.');
        }

        $language = $this->language();
        $cachePath = $this->cachePath($analysis, $language);
        $disk = $this->cacheDisk();

        if (Storage::disk($disk)->exists($cachePath)) {
            return [
                'content' => Storage::disk($disk)->get($cachePath),
                'language' => $language,
            ];
        }

        File::ensureDirectoryExists($workDirectory);

        $audioFile = "{$workDirectory}/whisper-audio.m4a";
        $jsonFile = "{$workDirectory}/whisper.json";
        $json3File = "{$workDirectory}/whisper.json3";
        $srtFile = "{$workDirectory}/whisper.srt";

        $this->downloadAudio($analysis->source_url, $audioFile);
        $this->runWhisper($audioFile, $jsonFile, $json3File, $srtFile, $language);

        if (! File::exists($json3File)) {
            throw new RuntimeException('Whisper selesai tanpa menghasilkan transcript JSON3.');
        }

        $content = File::get($json3File);
        Storage::disk($disk)->put($cachePath, $content);

        return [
            'content' => $content,
            'language' => $this->languageFromOutput($jsonFile) ?? $language,
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('freekliping.whisper.enabled', false);
    }

    private function downloadAudio(string $url, string $audioFile): void
    {
        try {
            $result = Process::timeout($this->timeout())->run(array_values(array_filter([
                $this->ytDlpBinary(),
                $this->jsRuntimeArgument(),
                '-f',
                'ba/b',
                '--extract-audio',
                '--audio-format',
                'm4a',
                '--no-playlist',
                '--no-warnings',
                '-o',
                $audioFile,
                $url,
            ])));
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('yt-dlp terlalu lama mengambil audio untuk Whisper.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException('yt-dlp gagal mengambil audio untuk Whisper: '.trim($result->errorOutput()));
        }
    }

    private function runWhisper(string $audioFile, string $jsonFile, string $json3File, string $srtFile, string $language): void
    {
        try {
            $result = Process::timeout($this->timeout())->run([
                $this->binary(),
                '--input',
                $audioFile,
                '--output-json',
                $jsonFile,
                '--output-json3',
                $json3File,
                '--output-srt',
                $srtFile,
                '--model',
                $this->model(),
                '--language',
                $language,
                '--device',
                $this->device(),
                '--compute-type',
                $this->computeType(),
                '--beam-size',
                (string) $this->beamSize(),
            ]);
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Whisper terlalu lama membuat transcript.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException('Whisper gagal membuat transcript: '.trim($result->errorOutput()));
        }
    }

    private function languageFromOutput(string $jsonFile): ?string
    {
        if (! File::exists($jsonFile)) {
            return null;
        }

        $data = json_decode(File::get($jsonFile), associative: true);
        $language = data_get($data, 'language');

        return is_string($language) && $language !== '' ? $language : null;
    }

    private function cachePath(ClipAnalysis $analysis, string $language): string
    {
        return sprintf(
            '%s/%s-%s-%s.json3',
            trim($this->cacheBasePath(), '/'),
            $analysis->youtube_video_id,
            Str::slug($this->model()),
            $language,
        );
    }

    private function binary(): string
    {
        $binary = config('freekliping.whisper.binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary Whisper belum dikonfigurasi.');
        }

        return $binary;
    }

    private function ytDlpBinary(): string
    {
        $binary = config('freekliping.yt_dlp_binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary yt-dlp belum dikonfigurasi.');
        }

        return $binary;
    }

    private function jsRuntimeArgument(): ?string
    {
        $runtime = config('freekliping.yt_dlp_js_runtime');

        return is_string($runtime) && $runtime !== '' ? '--js-runtimes='.$runtime : null;
    }

    private function model(): string
    {
        $model = config('freekliping.whisper.model');

        return is_string($model) && $model !== '' ? $model : 'small';
    }

    private function language(): string
    {
        $language = config('freekliping.subtitle_language');

        return is_string($language) && $language !== '' ? $language : 'id';
    }

    private function device(): string
    {
        $device = config('freekliping.whisper.device');

        return is_string($device) && $device !== '' ? $device : 'cpu';
    }

    private function computeType(): string
    {
        $computeType = config('freekliping.whisper.compute_type');

        return is_string($computeType) && $computeType !== '' ? $computeType : 'int8';
    }

    private function beamSize(): int
    {
        $beamSize = config('freekliping.whisper.beam_size');

        return is_int($beamSize) && $beamSize > 0 ? $beamSize : 5;
    }

    private function timeout(): int
    {
        $timeout = config('freekliping.whisper.timeout');

        return is_int($timeout) && $timeout > 0 ? $timeout : 1800;
    }

    private function cacheDisk(): string
    {
        $disk = config('freekliping.whisper.cache_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    private function cacheBasePath(): string
    {
        $path = config('freekliping.whisper.cache_path');

        return is_string($path) && $path !== '' ? $path : 'clip-analysis/transcripts';
    }
}
