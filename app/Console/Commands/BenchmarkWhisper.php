<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('clips:whisper:benchmark
    {input : Local audio or video file path}
    {--model= : Override configured Whisper model}
    {--language= : Override configured language}
    {--device= : Override configured device}
    {--compute-type= : Override configured compute type}')]
#[Description('Benchmark faster-whisper transcription runtime for a local media file.')]
class BenchmarkWhisper extends Command
{
    public function handle(): int
    {
        $input = (string) $this->argument('input');

        if (! File::exists($input)) {
            $this->error("Input file does not exist: {$input}");

            return self::FAILURE;
        }

        $workDirectory = storage_path('app/clip-analysis/whisper-benchmark-'.uniqid());
        File::ensureDirectoryExists($workDirectory);

        $jsonFile = "{$workDirectory}/transcript.json";
        $json3File = "{$workDirectory}/transcript.json3";
        $srtFile = "{$workDirectory}/transcript.srt";
        $startedAt = microtime(true);

        try {
            $result = Process::timeout($this->timeout())->run([
                $this->binary(),
                '--input',
                $input,
                '--output-json',
                $jsonFile,
                '--output-json3',
                $json3File,
                '--output-srt',
                $srtFile,
                '--model',
                $this->optionString('model', 'freekliping.whisper.model', 'small'),
                '--language',
                $this->optionString('language', 'freekliping.subtitle_language', 'id'),
                '--device',
                $this->optionString('device', 'freekliping.whisper.device', 'cpu'),
                '--compute-type',
                $this->optionString('compute-type', 'freekliping.whisper.compute_type', 'int8'),
                '--beam-size',
                (string) $this->beamSize(),
            ]);
        } catch (ProcessTimedOutException $exception) {
            $this->error('Whisper benchmark timed out: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($result->failed()) {
            $this->error('Whisper benchmark failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $segments = $this->segmentCount($jsonFile);
        $this->table(
            ['Metric', 'Value'],
            [
                ['elapsed_ms', (string) $this->elapsedMs($startedAt)],
                ['segments', (string) $segments],
                ['json', $jsonFile],
                ['json3', $json3File],
                ['srt', $srtFile],
            ],
        );

        return self::SUCCESS;
    }

    private function binary(): string
    {
        $binary = config('freekliping.whisper.binary');

        return is_string($binary) && $binary !== '' ? $binary : base_path('app/Support/Clips/whisper_transcribe.py');
    }

    private function optionString(string $option, string $configKey, string $fallback): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $configured = config($configKey);

        return is_string($configured) && $configured !== '' ? $configured : $fallback;
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

    private function segmentCount(string $jsonFile): int
    {
        if (! File::exists($jsonFile)) {
            return 0;
        }

        $data = json_decode(File::get($jsonFile), associative: true);
        $segments = data_get($data, 'segments');

        return is_array($segments) ? count($segments) : 0;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
