<?php

namespace App\Support\Clips;

use Illuminate\Support\Str;
use JsonException;

final class ClipMomentRecommender
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommendFromJson3(string $content, int $durationSeconds): array
    {
        $blocks = $this->blocksFromJson3($content, $durationSeconds);

        if ($blocks === []) {
            return [];
        }

        $candidates = $this->candidateWindows($blocks, $durationSeconds);

        return collect($candidates)
            ->map(fn (array $candidate): array => $this->scoreCandidate($candidate))
            ->sortByDesc('score')
            ->reduce(
                /**
                 * @param  array<int, array{startSeconds: int, endSeconds: int, score: int, title: string, hook: string, category: string, emotion: string, reason: string, openingText: string, caption: string, transcriptExcerpt: string, duration: int, id: string}>  $selected
                 * @param  array{startSeconds: int, endSeconds: int, score: int, title: string, hook: string, category: string, emotion: string, reason: string, openingText: string, caption: string, transcriptExcerpt: string, duration: int, id: string}  $candidate
                 * @return array<int, array{startSeconds: int, endSeconds: int, score: int, title: string, hook: string, category: string, emotion: string, reason: string, openingText: string, caption: string, transcriptExcerpt: string, duration: int, id: string}>
                 */
                function (array $selected, array $candidate): array {
                    if (count($selected) >= 6) {
                        return $selected;
                    }

                    foreach ($selected as $existing) {
                        if ($this->overlapRatio($existing, $candidate) > 0.55) {
                            return $selected;
                        }
                    }

                    $selected[] = $candidate;

                    return $selected;
                },
                []
            );
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>
     */
    private function blocksFromJson3(string $content, int $durationSeconds): array
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

        $blocks = [];

        foreach ($events as $event) {
            if (! is_array($event) || ! is_numeric(data_get($event, 'tStartMs'))) {
                continue;
            }

            $segments = data_get($event, 'segs');

            if (! is_array($segments)) {
                continue;
            }

            $text = collect($segments)
                ->map(fn (mixed $segment): ?string => is_array($segment) ? data_get($segment, 'utf8') : null)
                ->filter(fn (mixed $text): bool => is_string($text))
                ->implode('');
            $text = $this->cleanText($text);

            if ($text === '') {
                continue;
            }

            $start = (int) floor(((int) data_get($event, 'tStartMs')) / 1000);
            $duration = max(1, (int) ceil(((int) data_get($event, 'dDurationMs', 2500)) / 1000));
            $end = min($durationSeconds, $start + $duration);

            if ($end <= $start) {
                continue;
            }

            $blocks[] = compact('start', 'end', 'text');
        }

        return collect($blocks)
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{start: int, end: int, text: string}>  $blocks
     * @return array<int, array{startSeconds: int, endSeconds: int, transcriptExcerpt: string}>
     */
    private function candidateWindows(array $blocks, int $durationSeconds): array
    {
        $candidates = [];
        $count = count($blocks);

        for ($index = 0; $index < $count; $index += 2) {
            $start = $blocks[$index]['start'];
            $end = $blocks[$index]['end'];
            $texts = [];

            for ($cursor = $index; $cursor < $count; $cursor++) {
                $end = max($end, $blocks[$cursor]['end']);
                $texts[] = $blocks[$cursor]['text'];

                $duration = $end - $start;
                $text = $this->cleanText(implode(' ', $texts));

                if ($duration >= 20 && ($duration >= 35 || $this->endsSentence($text) || mb_strlen($text) >= 360)) {
                    break;
                }

                if (mb_strlen($text) >= 720) {
                    break;
                }
            }

            $end = min($durationSeconds, $end);

            if (($end - $start) < 12) {
                continue;
            }

            $candidates[] = [
                'startSeconds' => $start,
                'endSeconds' => $end,
                'transcriptExcerpt' => $this->cleanText(implode(' ', $texts)),
            ];
        }

        return $candidates;
    }

    /**
     * @param  array{startSeconds: int, endSeconds: int, transcriptExcerpt: string}  $candidate
     * @return array<string, mixed>
     */
    private function scoreCandidate(array $candidate): array
    {
        $text = $candidate['transcriptExcerpt'];
        $duration = $candidate['endSeconds'] - $candidate['startSeconds'];
        $wordCount = str($text)->split('/\s+/u')->filter()->count();
        $wordsPerSecond = $duration > 0 ? $wordCount / $duration : 0.0;
        $signals = $this->signals($text);
        $score = 42;

        $score += min(24, $signals['hook'] * 5);
        $score += min(16, $signals['debate'] * 4);
        $score += min(12, $signals['emotion'] * 3);
        $score += min(10, $signals['solution'] * 3);
        $score += min(8, $signals['humor'] * 4);
        $score += $duration >= 20 && $duration <= 60 ? 10 : 4;
        $score += $wordsPerSecond >= 1.4 && $wordsPerSecond <= 3.9 ? 8 : 2;

        if ($this->startsWithoutContext($text)) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));
        $category = $this->category($signals);
        $emotion = $this->emotion($signals);
        $hook = $this->hook($text);

        return [
            'id' => Str::uuid()->toString(),
            'startSeconds' => $candidate['startSeconds'],
            'endSeconds' => $candidate['endSeconds'],
            'duration' => $duration,
            'score' => $score,
            'title' => $this->title($hook, $category),
            'hook' => $hook,
            'category' => $category,
            'emotion' => $emotion,
            'reason' => $this->reason($signals, $duration, $wordsPerSecond),
            'openingText' => Str::of($hook)->limit(64, '')->toString(),
            'caption' => $this->caption($hook, $emotion),
            'transcriptExcerpt' => Str::of($text)->limit(420)->toString(),
        ];
    }

    /**
     * @return array{hook: int, debate: int, emotion: int, solution: int, humor: int}
     */
    private function signals(string $text): array
    {
        $lower = Str::lower($text);

        return [
            'hook' => $this->countSignals($lower, ['kalau', 'kenapa', 'gak', 'nggak', 'harus', 'jangan', 'bayangin', 'masalah', '?']),
            'debate' => $this->countSignals($lower, ['korupsi', 'pejabat', 'presiden', 'politik', 'salah', 'bohong', 'reset', 'rakyat', 'negara']),
            'emotion' => $this->countSignals($lower, ['takut', 'marah', 'sedih', 'gila', 'parah', 'hancur', 'berani', 'jujur']),
            'solution' => $this->countSignals($lower, ['solusi', 'caranya', 'harusnya', 'maka', 'sistem', 'ubah', 'benahi', 'mulai']),
            'humor' => $this->countSignals($lower, ['lucu', 'ketawa', 'anjir', 'kok bisa', 'masa', 'sarkas']),
        ];
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function countSignals(string $text, array $needles): int
    {
        return collect($needles)
            ->filter(fn (string $needle): bool => str_contains($text, $needle))
            ->count();
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int}  $signals
     */
    private function category(array $signals): string
    {
        $categories = [
            'Pernyataan kontroversial' => $signals['debate'],
            'Argumen kuat' => $signals['hook'] + $signals['solution'],
            'Momen emosional' => $signals['emotion'],
            'Momen lucu atau sarkastis' => $signals['humor'],
            'Solusi untuk Indonesia' => $signals['solution'],
        ];

        arsort($categories);

        $key = array_key_first($categories);

        return $key;
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int}  $signals
     */
    private function emotion(array $signals): string
    {
        if ($signals['debate'] >= 2) {
            return 'ingin berdebat';
        }

        if ($signals['emotion'] >= 1) {
            return 'terkejut';
        }

        if ($signals['solution'] >= 1) {
            return 'setuju';
        }

        return 'penasaran';
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int}  $signals
     */
    private function reason(array $signals, int $duration, float $wordsPerSecond): string
    {
        $reasons = [];

        if ($signals['hook'] > 0) {
            $reasons[] = 'punya hook cepat';
        }

        if ($signals['debate'] > 0) {
            $reasons[] = 'memuat sudut pandang yang memancing respons';
        }

        if ($signals['solution'] > 0) {
            $reasons[] = 'memberi gagasan yang bisa diperdebatkan';
        }

        if ($wordsPerSecond >= 1.4 && $wordsPerSecond <= 3.9) {
            $reasons[] = 'pacing-nya cukup padat untuk short video';
        }

        $reasons[] = "durasi {$duration} detik";

        return Str::ucfirst(implode(', ', $reasons)).'.';
    }

    private function title(string $hook, string $category): string
    {
        return Str::of($hook)
            ->replaceMatches('/[.!?]+$/u', '')
            ->limit(58, '')
            ->whenEmpty(fn () => Str::of($category))
            ->toString();
    }

    private function hook(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, 2) ?: [];
        $first = $sentences[0] ?? $text;

        return Str::of($first)
            ->squish()
            ->limit(86, '')
            ->toString();
    }

    private function caption(string $hook, string $emotion): string
    {
        return Str::of($hook)
            ->replaceMatches('/[.!?]+$/u', '')
            ->append("? Kamu {$emotion} atau beda pendapat?")
            ->limit(140)
            ->toString();
    }

    private function cleanText(string $text): string
    {
        $clean = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/<[^>]+>/', '', $clean) ?? $clean;

        return Str::of($clean)
            ->replace(["\r", "\n"], ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    private function endsSentence(string $text): bool
    {
        return preg_match('/[.!?]$/u', $text) === 1;
    }

    private function startsWithoutContext(string $text): bool
    {
        return preg_match('/^(dan|lalu|terus|kemudian|setelah itu)\b/iu', $text) === 1;
    }

    /**
     * @param  array{startSeconds: int, endSeconds: int}|array<string, mixed>  $first
     * @param  array{startSeconds: int, endSeconds: int}|array<string, mixed>  $second
     */
    private function overlapRatio(array $first, array $second): float
    {
        $overlap = max(0, min($first['endSeconds'], $second['endSeconds']) - max($first['startSeconds'], $second['startSeconds']));
        $shorter = max(1, min(
            $first['endSeconds'] - $first['startSeconds'],
            $second['endSeconds'] - $second['startSeconds'],
        ));

        return $overlap / $shorter;
    }
}
