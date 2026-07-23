import type {
    ClipAspectRatio,
    ClipQuality,
    SubtitleStyle,
} from './clip-editor.types';

export const defaultMaxClipLength = 180;

export interface SelectOption<TValue extends string> {
    description: string;
    label: string;
    value: TValue;
}

export const aspectRatioOptions: SelectOption<ClipAspectRatio>[] = [
    {
        description: 'No crop',
        label: 'Original',
        value: 'original',
    },
    {
        description: 'YouTube',
        label: '16:9',
        value: '16:9',
    },
    {
        description: 'Shorts',
        label: '9:16',
        value: '9:16',
    },
    {
        description: 'Feed',
        label: '1:1',
        value: '1:1',
    },
];

export const qualityOptions: SelectOption<ClipQuality>[] = [
    {
        description: 'Keep source',
        label: 'Source',
        value: 'source',
    },
    {
        description: 'Small file',
        label: '480p',
        value: '480p',
    },
    {
        description: 'Balanced',
        label: '720p',
        value: '720p',
    },
    {
        description: 'High quality',
        label: '1080p',
        value: '1080p',
    },
];

export const subtitleStyleOptions: SelectOption<SubtitleStyle>[] = [
    {
        description: 'Active word pops in amber',
        label: 'Highlight',
        value: 'word-highlight',
    },
    {
        description: 'Clean full-line caption',
        label: 'Classic',
        value: 'classic',
    },
    {
        description: 'Boxed text, neon accent',
        label: 'Neon box',
        value: 'neon-box',
    },
];

export const timelineFrameCount = 14;
export const timelineTickCount = 7;

export function aspectRatioLabel(aspectRatio: ClipAspectRatio): string {
    return (
        aspectRatioOptions.find((option) => option.value === aspectRatio)
            ?.label ?? 'Original'
    );
}

export function qualityLabel(quality: ClipQuality): string {
    return (
        qualityOptions.find((option) => option.value === quality)?.label ??
        'Source'
    );
}

export function subtitleStyleLabel(style: SubtitleStyle): string {
    return (
        subtitleStyleOptions.find((option) => option.value === style)?.label ??
        'Highlight'
    );
}
