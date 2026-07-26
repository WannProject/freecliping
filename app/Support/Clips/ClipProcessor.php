<?php

namespace App\Support\Clips;

use App\Enums\ClipStatus;
use App\Enums\SubtitleStatus;
use App\Models\Clip;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ClipProcessor
{
    public function __construct(
        private readonly SubtitleBurner $subtitleBurner,
        private readonly SmartCropPlanner $smartCropPlanner,
    ) {}

    public function process(Clip $clip): void
    {
        $workDirectory = storage_path("app/clip-processing/{$clip->uuid}");
        $sourcePattern = "{$workDirectory}/source.%(ext)s";
        $outputFile = "{$workDirectory}/output.mp4";

        File::ensureDirectoryExists($workDirectory);
        $startedAt = microtime(true);

        try {
            $downloadStart = max(0, $clip->start_seconds - $this->downloadBufferSeconds());
            $downloadEnd = $clip->end_seconds + $this->downloadBufferSeconds();
            $localStart = $clip->start_seconds - $downloadStart;

            $this->runYtDlp($clip, $sourcePattern, $downloadStart, $downloadEnd);

            Log::info('clip step completed', [
                'clip' => $clip->uuid,
                'step' => 'yt-dlp',
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            $clip->update(['progress' => 55]);

            $sourceFile = $this->sourceFile($workDirectory);

            $subtitlePath = $this->prepareSubtitles($clip, $workDirectory, $downloadStart);

            $this->runFfmpeg(
                clip: $clip,
                sourceFile: $sourceFile,
                outputFile: $outputFile,
                startSeconds: $localStart,
                durationSeconds: $clip->end_seconds - $clip->start_seconds,
                subtitlePath: $subtitlePath,
            );

            Log::info('clip step completed', [
                'clip' => $clip->uuid,
                'step' => 'ffmpeg',
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            $clip->update(['progress' => 85]);

            if (! File::exists($outputFile)) {
                throw new RuntimeException('ffmpeg selesai tanpa menghasilkan file output.');
            }

            $outputPath = "clips/{$clip->uuid}.mp4";
            $disk = $this->outputDisk();
            $contents = File::get($outputFile);

            Storage::disk($disk)->put($outputPath, $contents);

            Log::info('clip completed', [
                'clip' => $clip->uuid,
                'size_mb' => round(strlen($contents) / 1024 / 1024, 2),
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            $clip->update([
                'status' => ClipStatus::Completed,
                'progress' => 100,
                'output_disk' => $disk,
                'output_path' => $outputPath,
                'output_size_bytes' => strlen($contents),
                'output_expires_at' => now()->addHours($this->retentionHours()),
                'error_message' => null,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    private function runYtDlp(Clip $clip, string $sourcePattern, int $downloadStart, int $downloadEnd): void
    {
        try {
            $result = Process::timeout($this->processingTimeout())
                ->run(array_filter([
                    $this->ytDlpBinary(),
                    $this->jsRuntimeArgument(),
                    '--download-sections',
                    sprintf('*%s-%s', $this->timecode($downloadStart), $this->timecode($downloadEnd)),
                    '-f',
                    $clip->quality->ytDlpFormat(),
                    '--merge-output-format',
                    'mp4',
                    '--no-playlist',
                    '-o',
                    $sourcePattern,
                    $clip->source_url,
                ]));
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('yt-dlp terlalu lama mengambil stream video.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException($this->processFailureMessage('yt-dlp', $result->errorOutput()));
        }
    }

    /**
     * Build the optional `--js-runtimes` argument so yt-dlp can fully extract
     * YouTube data (including formats and captions). Returns null when
     * disabled, which array_filter strips from the command.
     */
    private function jsRuntimeArgument(): ?string
    {
        $runtime = config('freekliping.yt_dlp_js_runtime');

        if (! is_string($runtime) || $runtime === '') {
            return null;
        }

        return '--js-runtimes='.$runtime;
    }

    private function prepareSubtitles(Clip $clip, string $workDirectory, int $downloadStart): ?string
    {
        if (! $clip->subtitles_enabled) {
            return null;
        }

        try {
            $subtitlePath = $this->subtitleBurner->prepare($clip, $workDirectory, $downloadStart);
        } catch (Throwable $exception) {
            Log::warning('subtitle preparation failed', [
                'clip' => $clip->uuid,
                'error' => $exception->getMessage(),
            ]);

            $clip->update(['subtitle_status' => SubtitleStatus::Failed]);

            return null;
        }

        $clip->update([
            'subtitle_status' => $subtitlePath !== null
                ? SubtitleStatus::Burned
                : SubtitleStatus::Unavailable,
        ]);

        return $subtitlePath;
    }

    private function runFfmpeg(Clip $clip, string $sourceFile, string $outputFile, int $startSeconds, int $durationSeconds, ?string $subtitlePath = null): void
    {
        $command = [
            $this->ffmpegBinary(),
            '-y',
            '-i',
            $sourceFile,
            '-ss',
            (string) $startSeconds,
            '-t',
            (string) $durationSeconds,
        ];

        $videoFilter = $this->videoFilter($clip, $sourceFile, $startSeconds, $durationSeconds, $subtitlePath);

        if ($videoFilter !== null) {
            array_push(
                $command,
                '-vf',
                $videoFilter,
                '-c:v',
                'libx264',
                '-preset',
                $this->ffmpegPreset(),
                '-crf',
                '23',
                '-c:a',
                'aac',
            );
        } else {
            // No crop/scale/subtitles: stream-copy instead of a full
            // libx264 re-encode. This skips the expensive encode pass and
            // is near-instant versus the original always-re-encode behaviour.
            $command[] = '-c';
            $command[] = 'copy';
        }

        array_push(
            $command,
            '-movflags',
            '+faststart',
            $outputFile,
        );

        try {
            $result = Process::timeout($this->processingTimeout())
                ->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('ffmpeg terlalu lama memotong video.', previous: $exception);
        }

        if ($result->failed()) {
            throw new RuntimeException($this->processFailureMessage('ffmpeg', $result->errorOutput()));
        }
    }

    private function ffmpegPreset(): string
    {
        $preset = config('freekliping.ffmpeg_preset');

        return is_string($preset) && $preset !== '' ? $preset : 'veryfast';
    }

    private function videoFilter(Clip $clip, string $sourceFile, int $startSeconds, int $durationSeconds, ?string $subtitlePath = null): ?string
    {
        $filters = collect([
            $this->smartCropPlanner->filter($clip, $sourceFile, $startSeconds, $durationSeconds),
            $clip->quality->scaleFilter($clip->aspect_ratio),
        ])->filter()->values();

        if ($subtitlePath !== null) {
            $filters->push('subtitles='.addcslashes($subtitlePath, '\\:'));
        }

        return $filters->isEmpty() ? null : $filters->implode(',');
    }

    private function sourceFile(string $workDirectory): string
    {
        $files = collect(File::glob("{$workDirectory}/source.*") ?: [])
            ->reject(fn (string $file): bool => str_ends_with($file, '.part'))
            ->values();

        if ($files->isEmpty()) {
            throw new RuntimeException('yt-dlp selesai tanpa menghasilkan file sumber.');
        }

        return $files->first();
    }

    private function ytDlpBinary(): string
    {
        $binary = config('freekliping.yt_dlp_binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary yt-dlp belum dikonfigurasi.');
        }

        return $binary;
    }

    private function ffmpegBinary(): string
    {
        $binary = config('freekliping.ffmpeg_binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary ffmpeg belum dikonfigurasi.');
        }

        return $binary;
    }

    private function outputDisk(): string
    {
        $disk = config('freekliping.output_disk');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('Output disk belum dikonfigurasi.');
        }

        return $disk;
    }

    private function processingTimeout(): int
    {
        $timeout = config('freekliping.processing_timeout');

        return is_numeric($timeout) ? (int) $timeout : 600;
    }

    private function downloadBufferSeconds(): int
    {
        $seconds = config('freekliping.download_buffer_seconds');

        return is_numeric($seconds) ? max(0, (int) $seconds) : 3;
    }

    private function retentionHours(): int
    {
        $hours = config('freekliping.retention_hours');

        return is_numeric($hours) ? max(1, (int) $hours) : 1;
    }

    private function timecode(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function processFailureMessage(string $processName, string $errorOutput): string
    {
        $message = trim($errorOutput);

        if ($message === '') {
            return "{$processName} gagal memproses klip.";
        }

        return "{$processName} gagal memproses klip: ".str($message)->limit(240);
    }
}
