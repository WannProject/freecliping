<?php

namespace App\Support\Clips;

use App\Data\CaptionAvailability;
use App\Data\YouTubeVideoMetadata;
use App\Enums\CaptionKind;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

final class YouTubeMetadataClient
{
    public function fetch(string $url): YouTubeVideoMetadata
    {
        $videoId = YouTubeUrl::videoId($url);

        if ($videoId === null) {
            throw new RuntimeException('Gunakan link YouTube yang valid.');
        }

        $binary = config('freekliping.yt_dlp_binary');
        $timeout = config('freekliping.metadata_timeout');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary yt-dlp belum dikonfigurasi.');
        }

        try {
            $result = Process::timeout(is_numeric($timeout) ? (int) $timeout : 20)
                ->run(array_filter([
                    $binary,
                    $this->jsRuntimeArgument(),
                    '--dump-single-json',
                    '--skip-download',
                    '--no-warnings',
                    '--no-playlist',
                    $url,
                ]));
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('yt-dlp terlalu lama membaca metadata. Coba lagi nanti.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException($this->failureMessage($result->errorOutput()));
        }

        try {
            $metadata = json_decode($result->output(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Metadata dari yt-dlp tidak bisa dibaca.', previous: $exception);
        }

        if (! is_array($metadata)) {
            throw new RuntimeException('Metadata dari yt-dlp tidak valid.');
        }

        $title = data_get($metadata, 'title');
        $channel = data_get($metadata, 'channel') ?: data_get($metadata, 'uploader');
        $duration = data_get($metadata, 'duration');
        $extractedId = data_get($metadata, 'id');

        if (! is_string($title) || ! is_string($channel) || ! is_numeric($duration)) {
            throw new RuntimeException('Metadata video tidak lengkap.');
        }

        return new YouTubeVideoMetadata(
            id: is_string($extractedId) && $extractedId !== '' ? $extractedId : $videoId,
            title: $title,
            channel: $channel,
            durationSeconds: (int) $duration,
            thumbnailUrl: $this->thumbnailUrl($metadata),
            captions: $this->detectCaptions($metadata),
        );
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
     * Detect captions for preferred languages, preferring manually authored
     * subtitles over auto-generated ones.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function detectCaptions(array $metadata): CaptionAvailability
    {
        foreach ($this->captionLanguages() as $language) {
            if ($this->hasSubtitles(data_get($metadata, 'subtitles'), $language)) {
                return new CaptionAvailability(CaptionKind::Manual, $language);
            }
        }

        foreach ($this->captionLanguages() as $language) {
            if ($this->hasSubtitles(data_get($metadata, 'automatic_captions'), $language)) {
                return new CaptionAvailability(CaptionKind::Auto, $language);
            }
        }

        return new CaptionAvailability(CaptionKind::None);
    }

    /**
     * @return array<int, string>
     */
    private function captionLanguages(): array
    {
        $language = config('freekliping.subtitle_language');
        $preferred = is_string($language) && $language !== '' ? $language : 'id';

        return collect([$preferred, 'id', 'id-orig', 'en'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $subtitles
     */
    private function hasSubtitles($subtitles, string $language): bool
    {
        $tracks = data_get($subtitles, $language);

        return is_array($tracks) && $tracks !== [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function thumbnailUrl(array $metadata): ?string
    {
        $thumbnail = data_get($metadata, 'thumbnail');

        if (is_string($thumbnail) && $thumbnail !== '') {
            return $thumbnail;
        }

        $thumbnails = data_get($metadata, 'thumbnails');

        if (! is_array($thumbnails)) {
            return null;
        }

        foreach (array_reverse($thumbnails) as $candidate) {
            $url = is_array($candidate) ? data_get($candidate, 'url') : null;

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function failureMessage(string $errorOutput): string
    {
        $message = trim($errorOutput);
        $lowerMessage = strtolower($message);

        if ($message === '') {
            return 'yt-dlp gagal membaca metadata video.';
        }

        if (str_contains($lowerMessage, 'failed to resolve') || str_contains($lowerMessage, 'name or service not known')) {
            return 'Server tidak bisa menghubungi youtube.com. Cek DNS atau koneksi internet server.';
        }

        if (str_contains($lowerMessage, 'sign in to confirm') || str_contains($lowerMessage, 'not a bot')) {
            return 'YouTube meminta verifikasi bot untuk video ini. Coba video lain atau update konfigurasi yt-dlp.';
        }

        if (str_contains($lowerMessage, 'private')) {
            return 'Video tidak tersedia untuk diproses publik.';
        }

        if (str_contains($lowerMessage, 'age-restricted')) {
            return 'Video dibatasi umur dan tidak bisa diproses tanpa autentikasi.';
        }

        if (str_contains($lowerMessage, 'unavailable')) {
            return 'Video tidak ditemukan atau tidak tersedia.';
        }

        return 'yt-dlp gagal membaca metadata video: '.str($message)->replaceMatches('/^ERROR:\s*/', '')->limit(240);
    }
}
