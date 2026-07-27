<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $donor_name
 * @property int $amount_idr
 * @property string $platform
 * @property string|null $provider_event_id
 * @property string|null $message
 * @property bool $anonymous
 * @property bool $public_visible
 * @property CarbonImmutable $donated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    protected $fillable = [
        'donor_name',
        'amount_idr',
        'platform',
        'provider_event_id',
        'message',
        'anonymous',
        'public_visible',
        'donated_at',
    ];

    protected $attributes = [
        'platform' => 'manual',
        'anonymous' => false,
        'public_visible' => true,
    ];

    /**
     * @param  Builder<Donation>  $query
     * @return Builder<Donation>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('public_visible', true);
    }

    /**
     * @param  Builder<Donation>  $query
     * @return Builder<Donation>
     */
    public function scopeDonatedBetween(Builder $query, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->whereBetween('donated_at', [$start, $end]);
    }

    public function publicDisplayName(): string
    {
        if ($this->anonymous || ! is_string($this->donor_name) || trim($this->donor_name) === '') {
            return 'Anonim';
        }

        return trim($this->donor_name);
    }

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'anonymous' => 'boolean',
            'public_visible' => 'boolean',
            'donated_at' => 'immutable_datetime',
        ];
    }
}
