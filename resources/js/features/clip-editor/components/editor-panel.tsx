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
import { useFocusOnMount } from '../use-focus-on-mount';
import { ClipTimeline } from './clip-timeline';
import { ExportOptionsControls } from './export-options';
import { ExportSummary } from './export-summary';
import { GenerationProgress } from './generation-progress';
import { TimeRangeControls } from './time-range-controls';

export function EditorPanel({
    analysisError,
    canGenerate,
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
    analysisError: string | null;
    canGenerate: boolean;
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
    const headingRef = useFocusOnMount<HTMLParagraphElement>();

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
                        <p
                            ref={headingRef}
                            tabIndex={-1}
                            className="rounded-sm text-[15px] font-semibold text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
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
                {analysisError ? (
                    <Alert className="border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-100">
                        <AlertCircle className="size-4" />
                        <AlertTitle>Recommended clips unavailable</AlertTitle>
                        <AlertDescription>{analysisError}</AlertDescription>
                    </Alert>
                ) : null}

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
                    captions={video.captions}
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
                    <GenerationErrorAlert
                        canGenerate={canGenerate}
                        isClipTooLong={isClipTooLong}
                        isGenerating={isGenerating}
                        message={generationError}
                        onRetry={onGenerate}
                    />
                ) : null}
            </CardContent>

            <CardFooter className="flex flex-col items-stretch gap-3 border-t border-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <ExportSummary
                    clipLength={clipLength}
                    duration={video.duration}
                    isClipTooLong={isClipTooLong}
                    options={options}
                    range={range}
                />
                <Button
                    type="button"
                    onClick={onGenerate}
                    disabled={!canGenerate || isClipTooLong || isGenerating}
                    className="h-10 w-full font-semibold sm:w-auto"
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

function GenerationErrorAlert({
    canGenerate,
    isClipTooLong,
    isGenerating,
    message,
    onRetry,
}: {
    canGenerate: boolean;
    isClipTooLong: boolean;
    isGenerating: boolean;
    message: string;
    onRetry: () => void;
}) {
    const ref = useFocusOnMount<HTMLDivElement>();

    return (
        <Alert
            ref={ref}
            variant="destructive"
            tabIndex={-1}
            className="rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
            <AlertCircle className="size-4" />
            <AlertTitle>Clip could not be generated</AlertTitle>
            <AlertDescription className="flex flex-wrap items-center gap-3">
                <span>{message}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onRetry}
                    disabled={!canGenerate || isGenerating || isClipTooLong}
                    className="h-7 border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    Try again
                </Button>
            </AlertDescription>
        </Alert>
    );
}
