<?php

namespace App\Support\Clips;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class YouTubeTranscriptClient
{
    /**
     * @return array{content: string, language: string}
     */
    public function fetchJson3(string $url, string $workDirectory, ?string $videoId = null): array
    {
        $languages = $this->languages();

        $cachedTranscript = $this->cachedTranscript($videoId, $languages);

        if ($cachedTranscript !== null) {
            return $cachedTranscript;
        }

        File::ensureDirectoryExists($workDirectory);

        $outputTemplate = "{$workDirectory}/transcript";

        // A single yt-dlp call requests manual AND auto subs for every
        // preferred language at once. This replaces up to 8 sequential
        // invocations (2 kinds x 4 languages) that each re-downloaded the
        // YouTube page and re-ran the JS runtime, which was both slow and a
        // fast way to trigger YouTube's 429 rate limiting.
        $this->download($url, $outputTemplate, $languages);

        $source = $this->locateTranscript($workDirectory, $languages);

        if ($source !== null) {
            $language = $this->languageFromPath($source) ?? $languages[0];
            $content = File::get($source);
            $this->storeCachedTranscript($videoId, $language, $content);

            return [
                'content' => $content,
                'language' => $language,
            ];
        }

        throw new RuntimeException('Transcript atau caption YouTube tidak tersedia untuk video ini.');
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function download(string $url, string $outputTemplate, array $languages): void
    {
        try {
            $result = Process::timeout($this->timeout())->run(array_values(array_filter([
                $this->ytDlpBinary(),
                $this->jsRuntimeArgument(),
                '--write-subs',
                '--write-auto-subs',
                '--sub-langs',
                implode(',', $languages),
                '--sub-format',
                'json3',
                '--skip-download',
                '--no-playlist',
                '--no-warnings',
                '-o',
                $outputTemplate,
                $url,
            ])));
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('yt-dlp terlalu lama mengambil transcript.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException('yt-dlp gagal mengambil transcript: '.trim($result->errorOutput()));
        }
    }

    /**
     * @param  array<int, string>  $languages
     * @return array{content: string, language: string}|null
     */
    private function cachedTranscript(?string $videoId, array $languages): ?array
    {
        if ($videoId === null || $videoId === '') {
            return null;
        }

        $disk = $this->cacheDisk();

        foreach ($languages as $language) {
            $path = $this->cachePath($videoId, $language);

            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $content = Storage::disk($disk)->get($path);

            if (is_string($content) && $content !== '') {
                return [
                    'content' => $content,
                    'language' => $language,
                ];
            }
        }

        return null;
    }

    private function storeCachedTranscript(?string $videoId, string $language, string $content): void
    {
        if ($videoId === null || $videoId === '' || $content === '') {
            return;
        }

        Storage::disk($this->cacheDisk())->put($this->cachePath($videoId, $language), $content);
    }

    /**
     * Build the optional `--js-runtimes` argument so yt-dlp can fully extract
     * YouTube data (including captions). Returns null when disabled, which
     * array_filter strips from the command.
     */
    private function jsRuntimeArgument(): ?string
    {
        $runtime = config('freekliping.yt_dlp_js_runtime');

        if (! is_string($runtime) || $runtime === '') {
            return null;
        }

        return '--js-runtimes='.$runtime;
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function locateTranscript(string $workDirectory, array $languages): ?string
    {
        $files = File::glob("{$workDirectory}/transcript.*.json3") ?: [];

        return collect($files)
            ->sortBy(fn (string $file): int => $this->languagePriority($file, $languages))
            ->first();
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function languagePriority(string $file, array $languages): int
    {
        $language = $this->languageFromPath($file);

        if ($language === null) {
            return PHP_INT_MAX;
        }

        $index = array_search($language, $languages, true);

        return is_int($index) ? $index : PHP_INT_MAX - 1;
    }

    private function languageFromPath(string $file): ?string
    {
        if (! preg_match('/^transcript\.(.+)\.json3$/', basename($file), $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<int, string>
     */
    private function languages(): array
    {
        $language = config('freekliping.subtitle_language');
        $preferred = is_string($language) && $language !== '' ? $language : 'id';

        return collect([$preferred, 'id', 'id-orig', 'en'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function ytDlpBinary(): string
    {
        $binary = config('freekliping.yt_dlp_binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary yt-dlp belum dikonfigurasi.');
        }

        return $binary;
    }

    private function timeout(): int
    {
        $timeout = config('freekliping.metadata_timeout');

        return is_numeric($timeout) ? (int) $timeout : 20;
    }

    private function cacheDisk(): string
    {
        $disk = config('freekliping.analysis_transcript_cache_disk');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    private function cacheBasePath(): string
    {
        $path = config('freekliping.analysis_transcript_cache_path');

        return is_string($path) && $path !== '' ? $path : 'clip-analysis/transcripts/youtube';
    }

    private function cachePath(string $videoId, string $language): string
    {
        $safeVideoId = preg_replace('/[^A-Za-z0-9_-]/', '', $videoId) ?: $videoId;

        return sprintf('%s/%s-%s.json3', trim($this->cacheBasePath(), '/'), $safeVideoId, $language);
    }
}
