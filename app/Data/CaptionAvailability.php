<?php

namespace App\Data;

use App\Enums\CaptionKind;

final readonly class CaptionAvailability
{
    public function __construct(
        public CaptionKind $kind,
        public ?string $language = null,
    ) {}

    public function available(): bool
    {
        return $this->kind !== CaptionKind::None;
    }

    /**
     * @return array{available: bool, kind: string, language: string|null}
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available(),
            'kind' => $this->kind->value,
            'language' => $this->language,
        ];
    }
}
