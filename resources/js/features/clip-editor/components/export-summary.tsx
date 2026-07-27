import { cn } from '@/lib/utils';
import {
    aspectRatioLabel,
    qualityLabel,
    subtitleFontFamilyLabel,
    subtitleFontSizeLabel,
    subtitlePositionLabel,
    subtitleStyleLabel,
} from '../clip-editor.constants';
import type { ClipRange, ExportOptions } from '../clip-editor.types';
import { formatTimecode } from '../clip-editor.utils';

export function ExportSummary({
    clipLength,
    duration,
    isClipTooLong,
    options,
    range,
}: {
    clipLength: number;
    duration: number;
    isClipTooLong: boolean;
    options: ExportOptions;
    range: ClipRange;
}) {
    const forceHours = duration >= 3600;

    return (
        <p className="flex flex-wrap items-center gap-x-1.5 gap-y-1 font-mono text-[12px] tabular-nums">
            <span className="text-foreground">
                {formatTimecode(range.start, forceHours)}
                <span className="mx-1 text-muted-foreground">&rarr;</span>
                {formatTimecode(range.end, forceHours)}
            </span>
            <span className="text-muted-foreground">&middot;</span>
            <span
                className={cn(
                    isClipTooLong ? 'text-destructive' : 'text-foreground',
                )}
            >
                {formatTimecode(clipLength, forceHours)}
            </span>
            <span className="text-muted-foreground">&middot;</span>
            <span className="text-text-secondary">
                {aspectRatioLabel(options.aspectRatio)}
            </span>
            <span className="text-muted-foreground">&middot;</span>
            <span className="text-text-secondary">
                {qualityLabel(options.quality)}
            </span>
            <span className="text-muted-foreground">&middot;</span>
            <span className="text-text-secondary">
                {options.subtitlesEnabled
                    ? `Subtitles · ${subtitleStyleLabel(options.subtitleStyle)} · ${subtitleFontFamilyLabel(options.subtitleFontFamily)} · ${subtitleFontSizeLabel(options.subtitleFontSize)} · ${subtitlePositionLabel(options.subtitlePosition)}`
                    : 'No subtitles'}
            </span>
        </p>
    );
}
