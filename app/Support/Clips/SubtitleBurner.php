<?php

namespace App\Support\Clips;

use App\Enums\ClipAspectRatio;
use App\Models\Clip;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

/**
 * Downloads and aligns a subtitle track for a clip's sectioned source.
 *
 * The source video is downloaded as a section starting at $sectionStartSeconds,
 * so subtitle timestamps (which are absolute video times) must be shifted by
 * -sectionStartSeconds to line up with the sectioned file before burning.
 */
final class SubtitleBurner
{
    public function prepare(Clip $clip, string $workDirectory, int $sectionStartSeconds): ?string
    {
        $languages = $this->languages();
        $outputTemplate = "{$workDirectory}/subtitle";
        $styled = "{$workDirectory}/subtitle.styled.ass";

        $jsonSource = $this->downloadAndLocate($clip, $outputTemplate, $languages, format: 'json3');

        if ($jsonSource !== null) {
            $content = $this->wordTimedAss(
                clip: $clip,
                content: File::get($jsonSource),
                offsetMs: -$sectionStartSeconds * 1000,
            );

            if ($content !== null) {
                File::put($styled, $content);

                return $styled;
            }
        }

        $srtSource = $this->downloadAndLocate($clip, $outputTemplate, $languages, format: 'srt');

        if ($srtSource === null) {
            return null;
        }

        $content = $this->styledAss(
            clip: $clip,
            content: File::get($srtSource),
            offsetMs: -$sectionStartSeconds * 1000,
        );

        if ($content === null) {
            return null;
        }

        File::put($styled, $content);

        return $styled;
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function downloadAndLocate(Clip $clip, string $outputTemplate, array $languages, string $format): ?string
    {
        $source = null;

        foreach ($languages as $language) {
            $this->download($clip, $outputTemplate, manual: true, language: $language, format: $format);
            $source = $this->locateSubtitle(dirname($outputTemplate), $languages, $format);

            if ($source !== null) {
                break;
            }
        }

        if ($source === null) {
            foreach ($languages as $language) {
                $this->download($clip, $outputTemplate, manual: false, language: $language, format: $format);
                $source = $this->locateSubtitle(dirname($outputTemplate), $languages, $format);

                if ($source !== null) {
                    break;
                }
            }
        }

        return $source;
    }

    private function download(Clip $clip, string $outputTemplate, bool $manual, string $language, string $format): void
    {
        $command = [
            $this->ytDlpBinary(),
            $manual ? '--write-subs' : '--write-auto-subs',
            '--sub-langs',
            $language,
            '--sub-format',
            $format === 'json3' ? 'json3' : 'best',
        ];

        if ($format === 'srt') {
            array_push($command, '--convert-subs', 'srt');
        }

        array_push(
            $command,
            '--skip-download',
            '--no-playlist',
            '--no-warnings',
            '-o',
            $outputTemplate,
            $clip->source_url,
        );

        try {
            Process::timeout($this->timeout())->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('yt-dlp terlalu lama mengambil subtitle.', previous: $exception);
        }
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function locateSubtitle(string $workDirectory, array $languages, string $format): ?string
    {
        $extension = $format === 'json3' ? 'json3' : 'srt';
        $files = File::glob("{$workDirectory}/subtitle.*.{$extension}") ?: [];

        return collect($files)
            ->sortBy(fn (string $file): int => $this->languagePriority($file, $languages, $extension))
            ->first();
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function languagePriority(string $file, array $languages, string $extension): int
    {
        $filename = basename($file);

        if (! preg_match('/^subtitle\.(.+)\.'.preg_quote($extension, '/').'$/', $filename, $matches)) {
            return PHP_INT_MAX;
        }

        $index = array_search($matches[1], $languages, true);

        return is_int($index) ? $index : PHP_INT_MAX - 1;
    }

    private function wordTimedAss(Clip $clip, string $content, int $offsetMs): ?string
    {
        $groups = $this->wordGroups($content, $offsetMs, $clip);

        if ($groups === []) {
            return null;
        }

        $canvas = $this->subtitleCanvas($clip);
        $style = $this->subtitleStyle($clip);
        $events = collect($groups)
            ->map(fn (array $group): string => implode(',', [
                'Dialogue: 0',
                $this->msToAss($group['start']),
                $this->msToAss($group['end']),
                'FreeKlipingBase',
                '',
                '0000',
                '0000',
                '0000',
                '',
                $this->animatedAssLine($group),
            ]))
            ->implode("\n");

        return <<<ASS
[Script Info]
ScriptType: v4.00+
PlayResX: {$canvas['width']}
PlayResY: {$canvas['height']}
ScaledBorderAndShadow: yes
WrapStyle: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: FreeKlipingBase,{$style['font']},{$style['size']},&H00FFFFFF,&H00FFFFFF,&H00000000,&H7A000000,-1,0,0,0,100,100,0,0,1,{$style['outline']},{$style['shadow']},2,{$style['marginX']},{$style['marginX']},{$style['marginBottom']},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
{$events}
ASS;
    }

    /**
     * @return array<int, array{start: int, end: int, words: array<int, array{text: string, start: int, end: int}>}>
     */
    private function wordGroups(string $content, int $offsetMs, Clip $clip): array
    {
        $words = $this->jsonWords($content, $offsetMs);

        if ($words === []) {
            return [];
        }

        $maxWords = match ($clip->aspect_ratio) {
            ClipAspectRatio::Vertical => 3,
            ClipAspectRatio::Square => 4,
            default => 6,
        };
        $maxCharacters = match ($clip->aspect_ratio) {
            ClipAspectRatio::Vertical => 26,
            ClipAspectRatio::Square => 34,
            default => 52,
        };
        $groups = [];
        $current = [];

        foreach ($words as $word) {
            $currentCharacters = mb_strlen(collect($current)->pluck('text')->implode(' '));
            $nextCharacters = $currentCharacters + ($current === [] ? 0 : 1) + mb_strlen($word['text']);
            $gap = $current === [] ? 0 : $word['start'] - $current[array_key_last($current)]['end'];

            if ($current !== [] && (count($current) >= $maxWords || $nextCharacters > $maxCharacters || $gap > 900)) {
                $groups[] = $this->wordGroup($current);
                $current = [];
            }

            $current[] = $word;

            if (count($current) >= 2 && $this->endsSentence($word['text'])) {
                $groups[] = $this->wordGroup($current);
                $current = [];
            }
        }

        if ($current !== []) {
            $groups[] = $this->wordGroup($current);
        }

        return $this->withoutOverlappingGroups($groups);
    }

    /**
     * @return array{start: int, end: int, words: array<int, array{text: string, start: int, end: int}>}
     */
    private function wordGroup(array $words): array
    {
        return [
            'start' => max(0, $words[0]['start'] - 80),
            'end' => $words[array_key_last($words)]['end'] + 160,
            'words' => $words,
        ];
    }

    /**
     * @param  array<int, array{start: int, end: int, words: array<int, array{text: string, start: int, end: int}>}>  $groups
     * @return array<int, array{start: int, end: int, words: array<int, array{text: string, start: int, end: int}>}>
     */
    private function withoutOverlappingGroups(array $groups): array
    {
        foreach ($groups as $index => $group) {
            $next = $groups[$index + 1] ?? null;

            if ($next !== null) {
                $nextFirstWord = $next['words'][0]['start'];
                $groups[$index]['end'] = min($group['end'], $nextFirstWord);
            }

            if ($index > 0 && $groups[$index]['start'] < $groups[$index - 1]['end']) {
                $groups[$index]['start'] = $groups[$index - 1]['end'];
            }
        }

        return $groups;
    }

    private function endsSentence(string $text): bool
    {
        return preg_match('/[.!?]$/u', $text) === 1;
    }

    /**
     * @return array<int, array{text: string, start: int, end: int}>
     */
    private function jsonWords(string $content, int $offsetMs): array
    {
        try {
            $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $events = data_get($data, 'events');

        if (! is_array($events)) {
            return [];
        }

        $words = [];

        foreach ($events as $event) {
            if (! is_array($event) || ! is_numeric(data_get($event, 'tStartMs'))) {
                continue;
            }

            $segments = data_get($event, 'segs');

            if (! is_array($segments)) {
                continue;
            }

            $eventStart = (int) $event['tStartMs'];

            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $text = $this->wordText(data_get($segment, 'utf8'));

                if ($text === null) {
                    continue;
                }

                $words[] = [
                    'text' => $text,
                    'start' => $eventStart + (int) data_get($segment, 'tOffsetMs', 0) + $offsetMs,
                    'end' => 0,
                ];
            }
        }

        $words = collect($words)
            ->filter(fn (array $word): bool => $word['start'] >= 0)
            ->sortBy('start')
            ->values()
            ->all();

        $words = $this->deduplicateWords($words);

        foreach ($words as $index => $word) {
            $next = $words[$index + 1]['start'] ?? null;
            $duration = is_int($next) ? $next - $word['start'] : 520;
            $duration = max(180, min($duration, 850));
            $words[$index]['end'] = $word['start'] + $duration;
        }

        return $words;
    }

    /**
     * @param  array<int, array{text: string, start: int, end: int}>  $words
     * @return array<int, array{text: string, start: int, end: int}>
     */
    private function deduplicateWords(array $words): array
    {
        $unique = [];

        foreach ($words as $word) {
            $previous = $unique[array_key_last($unique)] ?? null;

            if (
                $previous !== null
                && abs($word['start'] - $previous['start']) <= 120
                && $this->normalizedWord($word['text']) === $this->normalizedWord($previous['text'])
            ) {
                continue;
            }

            $unique[] = $word;
        }

        return $unique;
    }

    private function normalizedWord(string $text): string
    {
        return str($text)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', '')
            ->toString();
    }

    private function wordText(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $clean = trim($text);

        if ($clean === '' || $clean === "\n") {
            return null;
        }

        return $this->cleanAssText($clean);
    }

    private function styledAss(Clip $clip, string $content, int $offsetMs): ?string
    {
        $cues = $this->shiftedCues($content, $offsetMs);

        if ($cues === []) {
            return null;
        }

        $canvas = $this->subtitleCanvas($clip);
        $style = $this->subtitleStyle($clip);
        $events = collect($cues)
            ->map(fn (array $cue): string => implode(',', [
                'Dialogue: 0',
                $this->msToAss($cue['start']),
                $this->msToAss($cue['end']),
                'FreeKlipingBase',
                '',
                '0000',
                '0000',
                '0000',
                '',
                $this->assText($cue['text']),
            ]))
            ->implode("\n");

        return <<<ASS
[Script Info]
ScriptType: v4.00+
PlayResX: {$canvas['width']}
PlayResY: {$canvas['height']}
ScaledBorderAndShadow: yes
WrapStyle: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: FreeKlipingBase,{$style['font']},{$style['size']},&H00FFFFFF,&H00FFFFFF,&H00000000,&H7A000000,-1,0,0,0,100,100,0,0,1,{$style['outline']},{$style['shadow']},2,{$style['marginX']},{$style['marginX']},{$style['marginBottom']},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
{$events}
ASS;
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    private function shiftedCues(string $content, int $offsetMs): array
    {
        $blocks = preg_split('/\r?\n\r?\n/', trim($content)) ?: [];
        $cues = [];

        foreach ($blocks as $block) {
            $lines = preg_split('/\r?\n/', trim($block)) ?: [];
            $timeLineIndex = null;

            foreach ($lines as $index => $line) {
                if (str_contains($line, '-->')) {
                    $timeLineIndex = $index;

                    break;
                }
            }

            if ($timeLineIndex === null) {
                continue;
            }

            if (! preg_match('/(\d{2}:\d{2}:\d{2},\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2},\d{3})/', $lines[$timeLineIndex], $matches)) {
                continue;
            }

            $text = implode("\n", array_slice($lines, $timeLineIndex + 1));

            $cue = [
                'start' => $this->srtToMs($matches[1]),
                'end' => $this->srtToMs($matches[2]),
                'text' => $text,
            ];
            $newStart = max(0, $cue['start'] + $offsetMs);
            $newEnd = $cue['end'] + $offsetMs;

            if ($newEnd <= 0 || trim($cue['text']) === '') {
                continue;
            }

            $cues[] = [
                'start' => $newStart,
                'end' => $newEnd,
                'text' => $cue['text'],
            ];
        }

        return $cues;
    }

    private function srtToMs(string $timestamp): int
    {
        sscanf($timestamp, '%d:%d:%d,%d', $hours, $minutes, $seconds, $milliseconds);

        return (($hours * 60 + $minutes) * 60 + $seconds) * 1000 + $milliseconds;
    }

    private function msToAss(int $milliseconds): string
    {
        $milliseconds = max(0, $milliseconds);
        $hours = intdiv($milliseconds, 3600000);
        $minutes = intdiv($milliseconds % 3600000, 60000);
        $seconds = intdiv($milliseconds % 60000, 1000);
        $centiseconds = intdiv($milliseconds % 1000, 10);

        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $seconds, $centiseconds);
    }

    /**
     * @return array{width: int, height: int}
     */
    private function subtitleCanvas(Clip $clip): array
    {
        return match ($clip->aspect_ratio) {
            ClipAspectRatio::Vertical => ['width' => 1080, 'height' => 1920],
            ClipAspectRatio::Square => ['width' => 1080, 'height' => 1080],
            default => ['width' => 1920, 'height' => 1080],
        };
    }

    /**
     * @return array{font: string, size: int, outline: int, shadow: int, marginX: int, marginBottom: int}
     */
    private function subtitleStyle(Clip $clip): array
    {
        return match ($clip->aspect_ratio) {
            ClipAspectRatio::Vertical => [
                'font' => 'DejaVu Sans',
                'size' => 70,
                'outline' => 5,
                'shadow' => 1,
                'marginX' => 86,
                'marginBottom' => 260,
            ],
            ClipAspectRatio::Square => [
                'font' => 'DejaVu Sans',
                'size' => 62,
                'outline' => 4,
                'shadow' => 1,
                'marginX' => 88,
                'marginBottom' => 150,
            ],
            default => [
                'font' => 'DejaVu Sans',
                'size' => 58,
                'outline' => 4,
                'shadow' => 1,
                'marginX' => 140,
                'marginBottom' => 90,
            ],
        };
    }

    private function assText(string $text): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = collect(explode("\n", $clean))
            ->map(fn (string $line): string => $this->cleanAssText($line))
            ->filter()
            ->values();

        return $lines->implode('\\N');
    }

    /**
     * @param  array{start: int, end: int, words: array<int, array{text: string, start: int, end: int}>}  $group
     */
    private function animatedAssLine(array $group): string
    {
        return collect($group['words'])
            ->map(function (array $word) use ($group): string {
                $highlightStart = max(0, $word['start'] - $group['start']);
                $highlightEnd = max(
                    $highlightStart + 1,
                    min($word['end'], $group['end']) - $group['start'],
                );

                return sprintf(
                    '{\\c&H00FFFFFF&\\t(%d,%d,\\c&H005AE1FF&)\\t(%d,%d,\\c&H00FFFFFF&)}%s',
                    $highlightStart,
                    $highlightStart + 1,
                    $highlightEnd,
                    $highlightEnd + 1,
                    $word['text'],
                );
            })
            ->implode(' ');
    }

    private function cleanAssText(string $text): string
    {
        $clean = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/<[^>]+>/', '', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        return str($clean)
            ->replace(['{', '}'], '')
            ->toString();
    }

    private function ytDlpBinary(): string
    {
        $binary = config('freekliping.yt_dlp_binary');

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Binary yt-dlp belum dikonfigurasi.');
        }

        return $binary;
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

    private function timeout(): int
    {
        $timeout = config('freekliping.metadata_timeout');

        return is_numeric($timeout) ? (int) $timeout : 20;
    }
}
