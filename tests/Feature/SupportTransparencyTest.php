<?php

use App\Models\Donation;
use Inertia\Testing\AssertableInertia as Assert;

test('home page exposes server costs and supporter leaderboard', function () {
    config([
        'freekliping.support_url' => 'https://saweria.co/freekliping',
        'freekliping.support.caption' => 'Bantu bayar sewa server.',
        'freekliping.support.currency' => 'IDR',
        'freekliping.support.monthly_target' => 500000,
        'freekliping.support.costs' => [
            ['label' => 'Sewa server', 'amount' => 350000],
            ['label' => 'Storage & bandwidth', 'amount' => 100000],
        ],
    ]);

    Donation::factory()->create([
        'donor_name' => 'Ari',
        'amount_idr' => 20000,
        'donated_at' => now(),
    ]);
    Donation::factory()->create([
        'donor_name' => 'Bima',
        'amount_idr' => 75000,
        'donated_at' => now(),
    ]);
    Donation::factory()->create([
        'donor_name' => 'Ari',
        'amount_idr' => 40000,
        'donated_at' => now(),
    ]);
    Donation::factory()->anonymous()->create([
        'amount_idr' => 10000,
        'donated_at' => now(),
    ]);
    Donation::factory()->hidden()->create([
        'donor_name' => 'Hidden',
        'amount_idr' => 999000,
        'donated_at' => now(),
    ]);
    Donation::factory()->create([
        'donor_name' => 'Old',
        'amount_idr' => 500000,
        'donated_at' => now()->subMonthNoOverflow(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clip-studio')
            ->where('supportUrl', 'https://saweria.co/freekliping')
            ->where('supportTransparency.caption', 'Bantu bayar sewa server.')
            ->where('supportTransparency.currency', 'IDR')
            ->where('supportTransparency.monthlyTarget', 500000)
            ->where('supportTransparency.totalSupported', 145000)
            ->where('supportTransparency.progressPercent', 29)
            ->has('supportTransparency.costItems', 2)
            ->where('supportTransparency.costItems.0.label', 'Sewa server')
            ->where('supportTransparency.costItems.0.amount', 350000)
            ->has('supportTransparency.supporters', 3)
            ->where('supportTransparency.supporters.0.name', 'Bima')
            ->where('supportTransparency.supporters.0.amount', 75000)
            ->where('supportTransparency.supporters.1.name', 'Ari')
            ->where('supportTransparency.supporters.1.amount', 60000)
            ->where('supportTransparency.supporters.2.name', 'Anonim')
            ->where('supportTransparency.supporters.2.amount', 10000));
});
