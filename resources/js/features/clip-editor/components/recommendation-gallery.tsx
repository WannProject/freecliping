import {
    Clock3,
    Cpu,
    Download,
    Eye,
    Flame,
    LoaderCircle,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    aspectRatioLabel,
    aspectRatioOptions,
    qualityLabel,
    qualityOptions,
} from '../clip-editor.constants';
import type {
    ClipAspectRatio,
    ClipQuality,
    ClipRecommendation,
    ExportOptions,
    VideoMeta,
} from '../clip-editor.types';
import { formatTimecode } from '../clip-editor.utils';
import { Thumbnail } from './thumbnail';

export function RecommendationGallery({
    disabled,
    forceHours,
    loading,
    localWorkerLoadingId,
    onGenerate,
    onPrepareLocal,
    onSelect,
    progress,
    recommendations,
    video,
}: {
    disabled: boolean;
    forceHours: boolean;
    loading: boolean;
    onGenerate: (
        recommendation: ClipRecommendation,
        options: ExportOptions,
    ) => void;
    localWorkerLoadingId: string | null;
    onPrepareLocal: (
        recommendation: ClipRecommendation,
        options: ExportOptions,
    ) => void;
    onSelect: (recommendation: ClipRecommendation) => void;
    progress: number;
    recommendations: ClipRecommendation[];
    video: VideoMeta;
}) {
    if (loading) {
        return (
            <Card className="gap-0 rounded-lg border-border bg-surface-2">
                <CardHeader className="border-b border-border px-4 py-4 sm:px-5">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-md bg-brand/12 text-brand">
                            <LoaderCircle className="size-4 animate-spin" />
                        </div>
                        <div>
                            <p className="text-[15px] font-semibold text-foreground">
                                Finding recommended clips
                            </p>
                            <p className="mt-0.5 text-[12.5px] text-muted-foreground">
                                Reading transcript, scoring hooks, and checking
                                context.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-4 sm:p-5">
                    <Progress value={progress} className="h-2" />
                </CardContent>
            </Card>
        );
    }

    if (recommendations.length === 0) {
        return null;
    }

    const visibleRecommendations = recommendations.slice(0, 6);

    return (
        <section className="grid gap-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="flex items-center gap-2 text-[15px] font-semibold text-foreground">
                        <Sparkles className="size-4 text-brand" />
                        Recommended Clips
                    </p>
                    <p className="mt-1 text-[13px] text-text-secondary">
                        Review the top 6 moments, adjust export settings, then
                        render only the clip you want.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    className="w-fit border-ring bg-muted font-mono text-muted-foreground"
                >
                    {visibleRecommendations.length}/6 picks
                </Badge>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {visibleRecommendations.map((recommendation, index) => (
                    <RecommendationCard
                        disabled={disabled}
                        forceHours={forceHours}
                        index={index}
                        key={recommendation.id}
                        localWorkerLoadingId={localWorkerLoadingId}
                        onGenerate={onGenerate}
                        onPrepareLocal={onPrepareLocal}
                        onSelect={onSelect}
                        recommendation={recommendation}
                        video={video}
                    />
                ))}
            </div>
        </section>
    );
}

function RecommendationCard({
    disabled,
    forceHours,
    index,
    localWorkerLoadingId,
    onGenerate,
    onPrepareLocal,
    onSelect,
    recommendation,
    video,
}: {
    disabled: boolean;
    forceHours: boolean;
    index: number;
    localWorkerLoadingId: string | null;
    onGenerate: (
        recommendation: ClipRecommendation,
        options: ExportOptions,
    ) => void;
    onPrepareLocal: (
        recommendation: ClipRecommendation,
        options: ExportOptions,
    ) => void;
    onSelect: (recommendation: ClipRecommendation) => void;
    recommendation: ClipRecommendation;
    video: VideoMeta;
}) {
    const hasCaptions = video.captions.available;
    const [quality, setQuality] = useState<ClipQuality>('720p');
    const [aspectRatio, setAspectRatio] = useState<ClipAspectRatio>('9:16');
    const [subtitlesEnabled, setSubtitlesEnabled] = useState(hasCaptions);
    const localWorkerLoading = localWorkerLoadingId === recommendation.id;

    const exportOptions: ExportOptions = {
        aspectRatio,
        quality,
        subtitlesEnabled,
        subtitleStyle: 'word-highlight',
    };

    return (
        <Card className="gap-0 overflow-hidden rounded-lg border-border bg-card shadow-[0_16px_48px_-36px_rgba(0,0,0,0.9)]">
            <Thumbnail
                className="aspect-video rounded-none"
                hue={video.hue}
                offset={index * 19}
                showPlay
                thumbnailUrl={video.thumbnailUrl}
            />
            <CardContent className="grid gap-3 p-4">
                <div className="grid gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className="bg-brand text-brand-foreground">
                            #{index + 1}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="border-success/40 bg-success/10 text-success"
                        >
                            <Flame className="size-3" />
                            {recommendation.score}/100
                        </Badge>
                        <Badge
                            variant="outline"
                            className="border-ring bg-muted text-muted-foreground"
                        >
                            {recommendation.category}
                        </Badge>
                    </div>
                    <div className="grid gap-1.5">
                        <h3 className="line-clamp-2 min-h-[2.6rem] text-[15.5px] leading-snug font-semibold text-foreground">
                            {recommendation.title}
                        </h3>
                        <div className="flex flex-wrap items-center gap-2 font-mono text-[11.5px] text-muted-foreground">
                            <span className="inline-flex items-center gap-1">
                                <Clock3 className="size-3.5" />
                                {formatTimecode(
                                    recommendation.duration,
                                    forceHours,
                                )}
                            </span>
                            <span className="rounded-sm border border-border bg-muted px-1.5 py-0.5 text-[10.5px] font-medium text-text-secondary">
                                MP4
                            </span>
                            <span>
                                {formatTimecode(
                                    recommendation.startSeconds,
                                    forceHours,
                                )}{' '}
                                -{' '}
                                {formatTimecode(
                                    recommendation.endSeconds,
                                    forceHours,
                                )}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-2 rounded-md border border-border bg-surface-2 p-2.5">
                    <div className="grid grid-cols-2 gap-2">
                        <ControlSelect
                            label="Resolution"
                            onValueChange={(value) =>
                                setQuality(value as ClipQuality)
                            }
                            value={quality}
                        >
                            {qualityOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {qualityLabel(option.value)}
                                </SelectItem>
                            ))}
                        </ControlSelect>

                        <ControlSelect
                            label="Aspect ratio"
                            onValueChange={(value) =>
                                setAspectRatio(value as ClipAspectRatio)
                            }
                            value={aspectRatio}
                        >
                            {aspectRatioOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {aspectRatioLabel(option.value)}
                                </SelectItem>
                            ))}
                        </ControlSelect>
                    </div>

                    <label className="flex items-center justify-between gap-3 rounded-md border border-border bg-background px-3 py-2">
                        <span className="min-w-0">
                            <span className="block text-[11px] font-medium text-muted-foreground">
                                Subtitle
                            </span>
                            <span className="block truncate text-[12.5px] font-medium text-foreground">
                                {hasCaptions
                                    ? subtitlesEnabled
                                        ? 'Word highlight'
                                        : 'Off'
                                    : 'Unavailable'}
                            </span>
                        </span>
                        <input
                            type="checkbox"
                            checked={subtitlesEnabled}
                            disabled={!hasCaptions}
                            onChange={(event) =>
                                setSubtitlesEnabled(event.currentTarget.checked)
                            }
                            className="size-4 rounded border-border bg-background accent-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-45"
                        />
                    </label>
                </div>

                <div className="grid grid-cols-3 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onSelect(recommendation)}
                        className="h-9 border-border px-2 text-[12.5px]"
                    >
                        <Eye className="size-3.5" />
                        Preview
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() =>
                            onPrepareLocal(recommendation, exportOptions)
                        }
                        disabled={disabled || localWorkerLoading}
                        className="h-9 px-2 text-[12.5px]"
                    >
                        {localWorkerLoading ? (
                            <LoaderCircle className="size-3.5 animate-spin" />
                        ) : (
                            <Cpu className="size-3.5" />
                        )}
                        Local
                    </Button>
                    <Button
                        type="button"
                        onClick={() =>
                            onGenerate(recommendation, exportOptions)
                        }
                        disabled={disabled}
                        className="h-9 px-2 text-[12.5px]"
                    >
                        <Download className="size-3.5" />
                        Download
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function ControlSelect({
    children,
    label,
    onValueChange,
    value,
}: {
    children: ReactNode;
    label: string;
    onValueChange: (value: string) => void;
    value: string;
}) {
    return (
        <div className="grid gap-1.5">
            <span className="text-[11.5px] font-medium text-muted-foreground">
                {label}
            </span>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger className="h-8 w-full border-border bg-background text-[12.5px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>{children}</SelectContent>
            </Select>
        </div>
    );
}
