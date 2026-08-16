import type {
    ClipAspectRatio,
    ClipQuality,
    SubtitleColor,
    SubtitleFontFamily,
    SubtitleFontSize,
    SubtitlePosition,
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

export const subtitleFontFamilyOptions: SelectOption<SubtitleFontFamily>[] = [
    {
        description: 'Clean sans',
        label: 'DejaVu',
        value: 'dejavu-sans',
    },
    {
        description: 'Compact',
        label: 'Arial',
        value: 'arial',
    },
    {
        description: 'Bold hooks',
        label: 'Impact',
        value: 'impact',
    },
];

export const subtitleFontSizeOptions: SelectOption<SubtitleFontSize>[] = [
    {
        description: 'Less cover',
        label: 'Small',
        value: 'small',
    },
    {
        description: 'Default',
        label: 'Medium',
        value: 'medium',
    },
    {
        description: 'Shorts style',
        label: 'Large',
        value: 'large',
    },
];

export const subtitlePositionOptions: SelectOption<SubtitlePosition>[] = [
    {
        description: 'Above UI',
        label: 'Bottom',
        value: 'bottom',
    },
    {
        description: 'Middle frame',
        label: 'Center',
        value: 'center',
    },
    {
        description: 'Upper frame',
        label: 'Top',
        value: 'top',
    },
];

export const subtitleColorOptions: SelectOption<SubtitleColor>[] = [
    {
        description: 'Neutral',
        label: 'White',
        value: 'white',
    },
    {
        description: 'Warm hook',
        label: 'Yellow',
        value: 'yellow',
    },
    {
        description: 'Cool accent',
        label: 'Cyan',
        value: 'cyan',
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

export function subtitleFontFamilyLabel(font: SubtitleFontFamily): string {
    return (
        subtitleFontFamilyOptions.find((option) => option.value === font)
            ?.label ?? 'DejaVu'
    );
}

export function subtitleFontSizeLabel(size: SubtitleFontSize): string {
    return (
        subtitleFontSizeOptions.find((option) => option.value === size)
            ?.label ?? 'Medium'
    );
}

export function subtitlePositionLabel(position: SubtitlePosition): string {
    return (
        subtitlePositionOptions.find((option) => option.value === position)
            ?.label ?? 'Bottom'
    );
}

export function subtitleColorLabel(color: SubtitleColor): string {
    return (
        subtitleColorOptions.find((option) => option.value === color)?.label ??
        'White'
    );
}
