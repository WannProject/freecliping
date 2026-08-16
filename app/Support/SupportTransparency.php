<?php

namespace App\Support;

use App\Models\Donation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class SupportTransparency
{
    /**
     * @return array{
     *     caption: string,
     *     currency: string,
     *     monthlyTarget: int,
     *     totalSupported: int,
     *     progressPercent: int,
     *     costItems: array<int, array{label: string, amount: int}>,
     *     supporters: array<int, array{name: string, amount: int}>
     * }
     */
    public function forHomepage(): array
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();
        $monthlyTarget = $this->monthlyTarget();
        $totalSupported = $this->totalSupported($start, $end);

        return [
            'caption' => $this->caption(),
            'currency' => $this->currency(),
            'monthlyTarget' => $monthlyTarget,
            'totalSupported' => $totalSupported,
            'progressPercent' => $this->progressPercent($totalSupported, $monthlyTarget),
            'costItems' => $this->costItems(),
            'supporters' => $this->supporters($start, $end),
        ];
    }

    private function totalSupported(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) Donation::query()
            ->publiclyVisible()
            ->donatedBetween($start, $end)
            ->sum('amount_idr');
    }

    /**
     * @return array<int, array{name: string, amount: int}>
     */
    private function supporters(CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var Collection<int, Donation> $donations */
        $donations = Donation::query()
            ->publiclyVisible()
            ->donatedBetween($start, $end)
            ->get(['donor_name', 'anonymous', 'amount_idr']);

        return $donations
            ->groupBy(fn (Donation $donation): string => $donation->publicDisplayName())
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'amount' => (int) $group->sum('amount_idr'),
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, amount: int}>
     */
    private function costItems(): array
    {
        $costs = config('freekliping.support.costs', []);

        if (! is_array($costs)) {
            return [];
        }

        return collect($costs)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'label' => is_string($item['label'] ?? null) ? $item['label'] : 'Biaya',
                'amount' => max(0, (int) ($item['amount'] ?? 0)),
            ])
            ->filter(fn (array $item): bool => $item['amount'] > 0)
            ->values()
            ->all();
    }

    private function caption(): string
    {
        $caption = config('freekliping.support.caption');

        return is_string($caption) && $caption !== ''
            ? $caption
            : 'Bantu bayar sewa server agar FreeKliping tetap aktif.';
    }

    private function currency(): string
    {
        $currency = config('freekliping.support.currency');

        return is_string($currency) && $currency !== '' ? $currency : 'IDR';
    }

    private function monthlyTarget(): int
    {
        $target = config('freekliping.support.monthly_target');

        return max(0, (int) $target);
    }

    private function progressPercent(int $totalSupported, int $monthlyTarget): int
    {
        if ($monthlyTarget <= 0) {
            return 0;
        }

        return min(100, (int) round(($totalSupported / $monthlyTarget) * 100));
    }
}
