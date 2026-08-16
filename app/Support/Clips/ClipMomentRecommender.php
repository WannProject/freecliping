<?php

namespace App\Support\Clips;

use Illuminate\Support\Str;
use JsonException;

final class ClipMomentRecommender
{
    public const RECOMMENDATION_VERSION = 2;

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
        $maxRecommendations = $this->maxRecommendations($durationSeconds);

        $scored = collect($candidates)
            ->map(fn (array $candidate): array => $this->scoreCandidate($candidate))
            ->filter(fn (array $candidate): bool => $candidate['score'] >= 52)
            ->sortByDesc('score')
            ->values()
            ->all();

        return $this->selectRecommendations($scored, $durationSeconds, $maxRecommendations);
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
        $targets = $this->targetDurations($durationSeconds);

        for ($index = 0; $index < $count; $index++) {
            foreach ($targets as $targetDuration) {
                $candidate = $this->candidateWindowFromIndex(
                    $blocks,
                    $index,
                    $durationSeconds,
                    $targetDuration,
                );

                if ($candidate === null) {
                    continue;
                }

                $candidates[] = $candidate;
            }
        }

        return collect($candidates)
            ->unique(fn (array $candidate): string => implode(':', [
                $candidate['startSeconds'],
                $candidate['endSeconds'],
                md5($candidate['transcriptExcerpt']),
            ]))
            ->values()
            ->all();
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
        $hook = $this->hook($text);
        $viralSignalCount = $this->viralSignalCount($signals);
        $score = 22;

        $score += min(24, $signals['hook'] * 5);
        $score += min(18, $signals['debate'] * 5);
        $score += min(14, $signals['emotion'] * 4);
        $score += min(14, $signals['solution'] * 4);
        $score += min(10, $signals['humor'] * 5);
        $score += min(10, $signals['curiosity'] * 5);
        $score += min(10, $signals['authority'] * 4);
        $score += min(10, $signals['story'] * 4);
        $score += min(10, $signals['payoff'] * 4);
        $score += min(8, $signals['contrast'] * 4);
        $score += min(8, $signals['urgency'] * 4);
        $score += min(6, $signals['numbers'] * 2);
        $score += min(10, $viralSignalCount * 2);
        $score += $this->durationScore($duration);
        $score += $this->pacingScore($wordsPerSecond);
        $score += $this->hookStrength($hook);

        if ($viralSignalCount < 2) {
            $score -= 18;
        }

        if ($this->startsWithoutContext($text)) {
            $score -= 14;
        }

        if ($signals['filler'] > 0) {
            $score -= min(18, $signals['filler'] * 5);
        }

        $score = max(0, min(100, $score));
        $category = $this->category($signals);
        $emotion = $this->emotion($signals);

        return [
            'id' => Str::uuid()->toString(),
            'startSeconds' => $candidate['startSeconds'],
            'endSeconds' => $candidate['endSeconds'],
            'duration' => $duration,
            'score' => $score,
            'modelVersion' => self::RECOMMENDATION_VERSION,
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
     * @return array{hook: int, debate: int, emotion: int, solution: int, humor: int, curiosity: int, authority: int, story: int, payoff: int, contrast: int, urgency: int, numbers: int, filler: int}
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
            'curiosity' => $this->countSignals($lower, ['rahasia', 'ternyata', 'faktanya', 'bayangin', 'coba pikir', 'tau gak', 'tahu gak']),
            'authority' => $this->countSignals($lower, ['data', 'angka', 'riset', 'bukti', 'fakta', 'laporan', 'statistik']),
            'story' => $this->countSignals($lower, ['waktu itu', 'suatu hari', 'awalnya', 'akhirnya', 'pas', 'ketika']),
            'payoff' => $this->countSignals($lower, ['artinya', 'hasilnya', 'ujungnya', 'makanya', 'kesimpulannya', 'intinya']),
            'contrast' => $this->countSignals($lower, ['tapi', 'padahal', 'justru', 'malah', 'sementara']),
            'urgency' => $this->countSignals($lower, ['sekarang', 'hari ini', 'segera', 'darurat', 'krisis', 'langsung']),
            'numbers' => preg_match_all('/\b\d+\b/u', $text),
            'filler' => $this->countSignals($lower, ['eee', 'emm', 'anu', 'gitu ya', 'dan segala macam', 'teman-teman']),
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
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int, curiosity: int, authority: int, story: int, payoff: int, contrast: int, urgency: int, numbers: int, filler: int}  $signals
     */
    private function category(array $signals): string
    {
        $categories = [
            'Pernyataan kontroversial' => $signals['debate'],
            'Argumen kuat' => $signals['hook'] + $signals['solution'],
            'Momen emosional' => $signals['emotion'],
            'Momen lucu atau sarkastis' => $signals['humor'],
            'Solusi untuk Indonesia' => $signals['solution'],
            'Cerita yang bikin penasaran' => $signals['story'] + $signals['curiosity'],
            'Data yang bikin mikir' => $signals['authority'] + $signals['numbers'],
        ];

        arsort($categories);

        $key = array_key_first($categories);

        return $key;
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int, curiosity: int, authority: int, story: int, payoff: int, contrast: int, urgency: int, numbers: int, filler: int}  $signals
     */
    private function emotion(array $signals): string
    {
        if ($signals['debate'] >= 2) {
            return 'ingin berdebat';
        }

        if ($signals['emotion'] >= 1) {
            return 'terkejut';
        }

        if ($signals['curiosity'] >= 1 || $signals['story'] >= 1) {
            return 'penasaran';
        }

        if ($signals['solution'] >= 1) {
            return 'setuju';
        }

        return 'penasaran';
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int, curiosity: int, authority: int, story: int, payoff: int, contrast: int, urgency: int, numbers: int, filler: int}  $signals
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

        if ($signals['curiosity'] > 0 || $signals['story'] > 0) {
            $reasons[] = 'pembuka dan alurnya bikin orang ingin lanjut nonton';
        }

        if ($signals['authority'] > 0 || $signals['numbers'] > 0) {
            $reasons[] = 'ada data atau detail konkret yang menguatkan opini';
        }

        if ($signals['contrast'] > 0 || $signals['payoff'] > 0) {
            $reasons[] = 'punya perubahan sudut pandang atau payoff yang jelas';
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
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $pool = collect($sentences)
            ->take(3)
            ->filter()
            ->map(fn (string $sentence): string => $this->cleanText($sentence))
            ->filter();
        $best = $pool
            ->sortByDesc(fn (string $sentence): int => $this->sentenceHookScore($sentence))
            ->first();

        return Str::of($best ?? $text)
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
     * @param  array<int, array{startSeconds: int, endSeconds: int, score: int, title: string, hook: string, category: string, emotion: string, reason: string, openingText: string, caption: string, transcriptExcerpt: string, duration: int, id: string}>  $scored
     * @return array<int, array{startSeconds: int, endSeconds: int, score: int, title: string, hook: string, category: string, emotion: string, reason: string, openingText: string, caption: string, transcriptExcerpt: string, duration: int, id: string}>
     */
    private function selectRecommendations(array $scored, int $durationSeconds, int $maxRecommendations): array
    {
        $selected = [];
        $bucketSeconds = max(60, (int) ceil($durationSeconds / $maxRecommendations));

        collect($scored)
            ->groupBy(fn (array $candidate): int => intdiv($candidate['startSeconds'], $bucketSeconds))
            ->sortKeys()
            ->each(function ($bucket) use (&$selected, $maxRecommendations): void {
                if (count($selected) >= $maxRecommendations) {
                    return;
                }

                $candidate = $bucket->first(
                    fn (array $candidate): bool => $this->canSelectCandidate($selected, $candidate),
                );

                if (is_array($candidate)) {
                    $selected[] = $candidate;
                }
            });

        foreach ($scored as $candidate) {
            if (count($selected) >= $maxRecommendations) {
                break;
            }

            if ($this->canSelectCandidate($selected, $candidate)) {
                $selected[] = $candidate;
            }
        }

        return collect($selected)
            ->unique('id')
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @param  array{hook: int, debate: int, emotion: int, solution: int, humor: int, curiosity: int, authority: int, story: int, payoff: int, contrast: int, urgency: int, numbers: int, filler: int}  $signals
     */
    private function viralSignalCount(array $signals): int
    {
        return collect($signals)
            ->except(['numbers', 'filler'])
            ->filter(fn (int $count): bool => $count > 0)
            ->count();
    }

    /**
     * @param  array<int, array{startSeconds: int, endSeconds: int}|array<string, mixed>>  $selected
     * @param  array{startSeconds: int, endSeconds: int}|array<string, mixed>  $candidate
     */
    private function canSelectCandidate(array $selected, array $candidate): bool
    {
        foreach ($selected as $existing) {
            if ($this->overlapRatio($existing, $candidate) > 0.55) {
                return false;
            }
        }

        return true;
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

    private function maxRecommendations(int $durationSeconds): int
    {
        return max(6, min(30, (int) ceil($durationSeconds / 60)));
    }

    /**
     * @return array<int, int>
     */
    private function targetDurations(int $durationSeconds): array
    {
        if ($durationSeconds <= 180) {
            return [18, 26, 34];
        }

        if ($durationSeconds <= 900) {
            return [20, 30, 42, 55];
        }

        return [22, 34, 48, 65];
    }

    /**
     * @param  array<int, array{start: int, end: int, text: string}>  $blocks
     * @return array{startSeconds: int, endSeconds: int, transcriptExcerpt: string}|null
     */
    private function candidateWindowFromIndex(array $blocks, int $index, int $durationSeconds, int $targetDuration): ?array
    {
        $start = $blocks[$index]['start'];
        $end = $blocks[$index]['end'];
        $texts = [];
        $count = count($blocks);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $end = max($end, $blocks[$cursor]['end']);
            $texts[] = $blocks[$cursor]['text'];
            $duration = $end - $start;
            $text = $this->cleanText(implode(' ', $texts));

            if ($duration >= 16 && $duration >= $targetDuration && $this->endsSentence($text)) {
                break;
            }

            if ($duration >= $targetDuration + 8 || mb_strlen($text) >= 760 || $duration >= 85) {
                break;
            }
        }

        $end = min($durationSeconds, $end);
        $duration = $end - $start;
        $excerpt = $this->cleanText(implode(' ', $texts));

        if ($duration < 12 || $excerpt === '') {
            return null;
        }

        return [
            'startSeconds' => $start,
            'endSeconds' => $end,
            'transcriptExcerpt' => $excerpt,
        ];
    }

    private function durationScore(int $duration): int
    {
        if ($duration >= 18 && $duration <= 45) {
            return 12;
        }

        if ($duration >= 12 && $duration <= 60) {
            return 8;
        }

        return 3;
    }

    private function pacingScore(float $wordsPerSecond): int
    {
        if ($wordsPerSecond >= 1.7 && $wordsPerSecond <= 3.4) {
            return 10;
        }

        if ($wordsPerSecond >= 1.3 && $wordsPerSecond <= 4.0) {
            return 6;
        }

        return 2;
    }

    private function hookStrength(string $hook): int
    {
        $score = 0;
        $lower = Str::lower($hook);

        if (preg_match('/^(kenapa|kalau|bayangin|gini|masalahnya|faktanya|justru|ini yang)/iu', $hook) === 1) {
            $score += 6;
        }

        if (str_contains($hook, '?')) {
            $score += 4;
        }

        if (preg_match('/\b\d+\b/u', $hook) === 1) {
            $score += 3;
        }

        if ($this->countSignals($lower, ['tapi', 'padahal', 'justru', 'ternyata']) > 0) {
            $score += 4;
        }

        return min(12, $score);
    }

    private function sentenceHookScore(string $sentence): int
    {
        $lower = Str::lower($sentence);
        $score = 0;

        $score += $this->countSignals($lower, [
            'kenapa', 'kalau', 'bayangin', 'masalahnya', 'faktanya',
            'ternyata', 'justru', 'harus', 'jangan',
        ]) * 3;
        $score += str_contains($sentence, '?') ? 4 : 0;
        $score += preg_match('/\b\d+\b/u', $sentence) === 1 ? 2 : 0;
        $score += mb_strlen($sentence) >= 28 && mb_strlen($sentence) <= 90 ? 2 : 0;

        return $score;
    }
}
