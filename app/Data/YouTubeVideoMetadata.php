<?php

namespace App\Data;

use App\Enums\CaptionKind;

final readonly class YouTubeVideoMetadata
{
    public function __construct(
        public string $id,
        public string $title,
        public string $channel,
        public int $durationSeconds,
        public ?string $thumbnailUrl = null,
        public CaptionAvailability $captions = new CaptionAvailability(CaptionKind::None),
    ) {}

    /**
     * @return array{id: string, title: string, channel: string, duration: int, thumbnailUrl: string|null, captions: array{available: bool, kind: string, language: string|null}}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'channel' => $this->channel,
            'duration' => $this->durationSeconds,
            'thumbnailUrl' => $this->thumbnailUrl,
            'captions' => $this->captions->toArray(),
        ];
    }
}
