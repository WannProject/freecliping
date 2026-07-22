import {
    AlertCircle,
    LoaderCircle,
    Scissors,
    SlidersHorizontal,
    Timer,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { aspectRatioLabel, qualityLabel } from '../clip-editor.constants';
import type { ClipRange, ExportOptions, VideoMeta } from '../clip-editor.types';
import { formatTimecode } from '../clip-editor.utils';
import { ClipTimeline } from './clip-timeline';
import { ExportOptionsControls } from './export-options';
import { GenerationProgress } from './generation-progress';
import { TimeRangeControls } from './time-range-controls';

export function EditorPanel({
    clipLength,
    generationError,
    isClipTooLong,
    isGenerating,
    maxClipLength,
    onGenerate,
    onOptionsChange,
    onRangeChange,
    options,
    progress,
    range,
    video,
}: {
    clipLength: number;
    generationError: string | null;
    isClipTooLong: boolean;
    isGenerating: boolean;
    maxClipLength: number;
    onGenerate: () => void;
    onOptionsChange: (options: ExportOptions) => void;
    onRangeChange: (range: ClipRange) => void;
    options: ExportOptions;
    progress: number;
    range: ClipRange;
    video: VideoMeta;
}) {
    const forceHours = video.duration >= 3600;

    return (
        <Card
            aria-busy={isGenerating}
            className="relative gap-0 overflow-hidden rounded-lg border-border bg-surface-2 px-0 py-0 shadow-[0_18px_60px_-36px_rgba(0,0,0,0.8)]"
        >
            <CardHeader className="flex flex-row items-center justify-between gap-3 rounded-t-lg border-b border-border bg-card px-4 py-4 sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand/12 text-brand">
                        <SlidersHorizontal className="size-4" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-[15px] font-semibold text-foreground">
                            Clip editor
                        </p>
                        <p className="mt-0.5 font-mono text-[11px] text-muted-foreground tabular-nums">
                            {formatTimecode(video.duration, forceHours)} total
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant="outline"
                        className={cn(
                            'border-ring bg-muted font-mono text-text-secondary',
                            isClipTooLong &&
                                'border-destructive/60 text-destructive',
                        )}
                    >
                        <Timer className="size-3" />
                        {formatTimecode(clipLength, forceHours)}
                    </Badge>
                    <Badge
                        variant="outline"
                        className="border-ring bg-muted font-mono text-text-secondary"
                    >
                        {aspectRatioLabel(options.aspectRatio)} ·{' '}
                        {qualityLabel(options.quality)}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="grid gap-5 p-4 sm:p-5">
                <ClipTimeline
                    duration={video.duration}
                    hue={video.hue}
                    onRangeChange={onRangeChange}
                    range={range}
                />

                <TimeRangeControls
                    clipLength={clipLength}
                    duration={video.duration}
                    isClipTooLong={isClipTooLong}
                    maxClipLength={maxClipLength}
                    onRangeChange={onRangeChange}
                    range={range}
                />

                <Separator />

                <ExportOptionsControls
                    disabled={isGenerating}
                    onChange={onOptionsChange}
                    options={options}
                />

                {isClipTooLong ? (
                    <Alert
                        variant="destructive"
                        className="border-destructive/30 bg-destructive/10"
                    >
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            Keep clips under{' '}
                            {formatTimecode(
                                maxClipLength,
                                maxClipLength >= 3600,
                            )}
                            .
                        </AlertDescription>
                    </Alert>
                ) : null}

                {generationError ? (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertTitle>Clip could not be generated</AlertTitle>
                        <AlertDescription className="flex flex-wrap items-center gap-3">
                            <span>{generationError}</span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onGenerate}
                                disabled={isGenerating || isClipTooLong}
                                className="h-7 border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                Try again
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}
            </CardContent>

            <CardFooter className="justify-end gap-3 border-t border-border px-4 py-4 sm:px-5">
                <Button
                    type="button"
                    onClick={onGenerate}
                    disabled={isClipTooLong || isGenerating}
                    className="h-10 font-semibold"
                >
                    {isGenerating ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Scissors className="size-4" />
                    )}
                    {isGenerating ? 'Generating' : 'Generate clip'}
                </Button>
            </CardFooter>

            {isGenerating ? <GenerationProgress progress={progress} /> : null}
        </Card>
    );
}
