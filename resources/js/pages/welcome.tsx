import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    ClipboardPaste,
    Clock3,
    Crop,
    Download,
    FilePenLine,
    Gauge,
    Heart,
    Link2,
    LoaderCircle,
    Play,
    RotateCcw,
    Save,
    Scissors,
    SlidersHorizontal,
    Sparkles,
    Timer,
    User2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import {
    metadata,
    store as storeClip,
    updateFilename,
} from '@/actions/App/Http/Controllers/ClipController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { dashboard, privacy, terms } from '@/routes';

type FlowStage = 'idle' | 'loading' | 'ready' | 'generating' | 'done';

interface ClipRange {
    start: number;
    end: number;
}

type ClipAspectRatio = 'original' | '16:9' | '9:16' | '1:1';
type ClipQuality = 'source' | '480p' | '720p' | '1080p';

interface ExportOptions {
    aspectRatio: ClipAspectRatio;
    quality: ClipQuality;
}

interface VideoMeta {
    channel: string;
    duration: number;
    hue: number;
    id: string;
    thumbnailUrl: string | null;
    title: string;
}

interface ClipResult {
    aspectRatio: ClipAspectRatio;
    downloadUrl: string;
    duration: number;
    fileName: string;
    quality: ClipQuality;
    sizeMb: number | null;
    uuid: string;
}

interface MetadataResponse {
    limits: {
        maxClipLength: number;
        retentionHours: number;
    };
    video: {
        channel: string;
        duration: number;
        id: string;
        thumbnailUrl: string | null;
        title: string;
    };
}

interface ErrorResponse {
    errors?: Record<string, string[]>;
    message?: string;
}

interface ClipPayload {
    aspectRatio: ClipAspectRatio;
    downloadUrl: string | null;
    duration: number;
    errorMessage: string | null;
    fileName: string;
    progress: number;
    quality: ClipQuality;
    sizeMb: number | null;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    statusUrl: string;
    uuid: string;
}

interface ClipResponse {
    clip: ClipPayload;
}

const defaultMaxClipLength = 180;

const aspectRatioOptions: Array<{
    description: string;
    label: string;
    value: ClipAspectRatio;
}> = [
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

const qualityOptions: Array<{
    description: string;
    label: string;
    value: ClipQuality;
}> = [
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

export default function Welcome() {
    const { auth, currentTeam, maxClipLength: pageMaxClipLength } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const maxClipLength =
        typeof pageMaxClipLength === 'number'
            ? pageMaxClipLength
            : defaultMaxClipLength;
    const [url, setUrl] = useState('');
    const [stage, setStage] = useState<FlowStage>('idle');
    const [error, setError] = useState<string | null>(null);
    const [video, setVideo] = useState<VideoMeta | null>(null);
    const [range, setRange] = useState<ClipRange>({ start: 38, end: 58 });
    const [exportOptions, setExportOptions] = useState<ExportOptions>({
        aspectRatio: 'original',
        quality: '720p',
    });
    const [progress, setProgress] = useState(0);
    const [result, setResult] = useState<ClipResult | null>(null);
    const progressTimer = useRef<number | null>(null);

    const clipLength = range.end - range.start;
    const isClipTooLong = clipLength > maxClipLength;

    useEffect(() => {
        return () => {
            if (progressTimer.current) {
                window.clearInterval(progressTimer.current);
            }
        };
    }, []);

    function handleUrlChange(value: string) {
        setUrl(value);
        setResult(null);

        if (error) {
            setError(null);
        }
    }

    async function handleSubmit() {
        const trimmedUrl = url.trim();

        if (!trimmedUrl) {
            return;
        }

        if (!isLikelyYoutubeUrl(trimmedUrl)) {
            setError(
                'Gunakan link YouTube lengkap dari youtube.com atau youtu.be.',
            );

            return;
        }

        setError(null);
        setStage('loading');
        setProgress(0);

        try {
            const route = metadata();
            const response = await fetch(route.url, {
                body: JSON.stringify({ url: trimmedUrl }),
                headers: jsonHeaders(),
                method: route.method.toUpperCase(),
            });

            if (!response.ok) {
                throw new Error(
                    await errorMessage(
                        response,
                        'Video metadata could not be loaded.',
                    ),
                );
            }

            const payload = (await response.json()) as MetadataResponse;
            const nextVideo = {
                ...payload.video,
                hue: hashString(payload.video.id) % 360,
            };
            const start = Math.min(
                nextVideo.duration * 0.16,
                nextVideo.duration - 35,
            );
            const end = Math.min(
                nextVideo.duration,
                start + Math.min(20, payload.limits.maxClipLength),
            );

            setVideo(nextVideo);
            setRange({ start: Math.max(0, start), end });

            setStage('ready');
        } catch (caughtError) {
            setError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Video metadata could not be loaded.',
            );
            setStage(video ? 'ready' : 'idle');
        }
    }

    function handleReset() {
        if (progressTimer.current) {
            window.clearInterval(progressTimer.current);
            progressTimer.current = null;
        }

        setUrl('');
        setError(null);
        setVideo(null);
        setResult(null);
        setStage('idle');
        setProgress(0);
    }

    async function handleGenerateClip() {
        if (!video || isClipTooLong) {
            return;
        }

        if (progressTimer.current) {
            window.clearInterval(progressTimer.current);
            progressTimer.current = null;
        }

        setError(null);
        setStage('generating');
        setProgress(5);

        try {
            const route = storeClip();
            const response = await fetch(route.url, {
                body: JSON.stringify({
                    url: url.trim(),
                    start_seconds: Math.floor(range.start),
                    end_seconds: Math.ceil(range.end),
                    aspect_ratio: exportOptions.aspectRatio,
                    quality: exportOptions.quality,
                }),
                headers: jsonHeaders(),
                method: route.method.toUpperCase(),
            });

            if (!response.ok) {
                throw new Error(
                    await errorMessage(response, 'Clip could not be started.'),
                );
            }

            const payload = (await response.json()) as ClipResponse;

            await pollClipStatus(payload.clip.statusUrl);

            progressTimer.current = window.setInterval(() => {
                void pollClipStatus(payload.clip.statusUrl);
            }, 1200);
        } catch (caughtError) {
            setError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Clip could not be started.',
            );
            setStage('ready');
        }
    }

    async function pollClipStatus(statusUrl: string) {
        const response = await fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(
                await errorMessage(response, 'Clip status could not be read.'),
            );
        }

        const payload = (await response.json()) as ClipResponse;
        const clip = payload.clip;

        setProgress(clip.progress);

        if (clip.status === 'completed' && clip.downloadUrl) {
            if (progressTimer.current) {
                window.clearInterval(progressTimer.current);
                progressTimer.current = null;
            }

            setResult({
                aspectRatio: clip.aspectRatio,
                downloadUrl: clip.downloadUrl,
                duration: clip.duration,
                fileName: clip.fileName,
                quality: clip.quality,
                sizeMb: clip.sizeMb,
                uuid: clip.uuid,
            });
            setStage('done');
        }

        if (clip.status === 'failed') {
            if (progressTimer.current) {
                window.clearInterval(progressTimer.current);
                progressTimer.current = null;
            }

            setError(clip.errorMessage || 'Clip failed to process.');
            setStage('ready');
        }
    }

    async function handleRenameClip(fileName: string): Promise<string> {
        if (!result) {
            return fileName;
        }

        const route = updateFilename({ clip: result.uuid });
        const response = await fetch(route.url, {
            body: JSON.stringify({ file_name: fileName }),
            headers: jsonHeaders(),
            method: route.method.toUpperCase(),
        });

        if (!response.ok) {
            throw new Error(
                await errorMessage(response, 'File name could not be updated.'),
            );
        }

        const payload = (await response.json()) as ClipResponse;
        const clip = payload.clip;

        setResult({
            aspectRatio: clip.aspectRatio,
            downloadUrl: clip.downloadUrl ?? result.downloadUrl,
            duration: clip.duration,
            fileName: clip.fileName,
            quality: clip.quality,
            sizeMb: clip.sizeMb,
            uuid: clip.uuid,
        });

        return clip.fileName;
    }

    return (
        <>
            <Head title="FreeKliping" />
            <main className="min-h-screen bg-[#0b0d10] text-[#f3f4f1] selection:bg-[#f2a93b] selection:text-[#1a1204]">
                <header className="sticky top-0 z-40 border-b border-[#23282e]/80 bg-[#0b0d10]/82 backdrop-blur-xl">
                    <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                        <Link
                            href="/"
                            className="flex min-w-0 items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                        >
                            <Logomark />
                            <span className="truncate text-[17px] font-semibold tracking-tight">
                                FreeKliping
                            </span>
                        </Link>

                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={dashboardUrl}
                                    className="inline-flex h-9 items-center justify-center rounded-md border border-[#323942] px-3 text-sm font-medium text-[#f3f4f1] transition-colors hover:bg-[#1a1f25]"
                                >
                                    Dashboard
                                </Link>
                            ) : null}
                            <a
                                href="https://saweria.co/freekliping"
                                target="_blank"
                                rel="noreferrer"
                                className="hidden h-9 items-center justify-center gap-2 rounded-md bg-[#1a1f25] px-3 text-sm font-medium text-[#f3f4f1] transition-colors hover:bg-[#23282e] sm:inline-flex"
                            >
                                <Heart className="size-3.5 fill-current text-[#ef6a5f]" />
                                Support
                            </a>
                        </div>
                    </nav>
                </header>

                <section className="px-5 pt-16 pb-14 sm:px-8 sm:pt-24 sm:pb-16">
                    <div className="mx-auto flex max-w-[760px] flex-col items-center text-center">
                        <p className="mb-5 font-mono text-[12px] font-medium tracking-[0.18em] text-[#5a6067] uppercase">
                            Paste <span className="text-[#3fd9c7]">-&gt;</span>{' '}
                            Mark <span className="text-[#3fd9c7]">-&gt;</span>{' '}
                            Clip
                        </p>
                        <h1 className="max-w-[680px] text-[40px] leading-[1.08] font-semibold tracking-normal text-[#f3f4f1] sm:text-[56px]">
                            Cut the good part out.
                        </h1>
                        <p className="mt-4 max-w-[500px] text-[16px] leading-relaxed text-[#8b9198] sm:text-[17px]">
                            Paste a YouTube link, mark the start and end, and
                            export a clean MP4 without an account.
                        </p>

                        <UrlInput
                            error={error}
                            loading={stage === 'loading'}
                            onChange={handleUrlChange}
                            onSubmit={handleSubmit}
                            value={url}
                        />

                        <p className="mt-4 font-mono text-[11.5px] text-[#5a6067]">
                            Free · No login · No watermark · MP4 out
                        </p>
                    </div>
                </section>

                <section className="px-5 pb-20 sm:px-8">
                    <div className="mx-auto max-w-[760px]">
                        {stage === 'idle' || stage === 'loading' ? (
                            <EmptyState loading={stage === 'loading'} />
                        ) : (
                            video && (
                                <div className="flex flex-col gap-5">
                                    <VideoPreview
                                        onReset={handleReset}
                                        video={video}
                                    />

                                    <EditorPanel
                                        clipLength={clipLength}
                                        isGenerating={stage === 'generating'}
                                        isClipTooLong={isClipTooLong}
                                        maxClipLength={maxClipLength}
                                        onGenerate={handleGenerateClip}
                                        onOptionsChange={setExportOptions}
                                        onRangeChange={setRange}
                                        options={exportOptions}
                                        progress={progress}
                                        range={range}
                                        video={video}
                                    />

                                    {stage === 'done' && result && (
                                        <ResultCard
                                            onRename={handleRenameClip}
                                            onReset={handleReset}
                                            result={result}
                                        />
                                    )}
                                </div>
                            )
                        )}
                    </div>
                </section>

                <SupportCard />
            </main>
        </>
    );
}

function UrlInput({
    error,
    loading,
    onChange,
    onSubmit,
    value,
}: {
    error: string | null;
    loading: boolean;
    onChange: (value: string) => void;
    onSubmit: () => void;
    value: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);

    async function handlePaste() {
        try {
            const clipboardText = await navigator.clipboard.readText();

            if (clipboardText) {
                onChange(clipboardText);
            }
        } finally {
            inputRef.current?.focus();
        }
    }

    function handleFormSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        onSubmit();
    }

    return (
        <div className="mt-9 w-full max-w-[600px]">
            <form
                onSubmit={handleFormSubmit}
                className={cn(
                    'flex flex-col items-stretch gap-2.5 rounded-lg border bg-[#14181d] p-2 shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_-12px_rgba(0,0,0,0.55)] transition-colors sm:flex-row sm:items-center',
                    error
                        ? 'border-[#ef6a5f]/60'
                        : 'border-[#23282e] focus-within:border-[#323942]',
                )}
            >
                <div className="flex flex-1 items-center gap-2.5 px-3 py-2 sm:py-0">
                    <Link2 className="size-[18px] shrink-0 text-[#5a6067]" />
                    <input
                        ref={inputRef}
                        type="url"
                        inputMode="url"
                        autoComplete="off"
                        spellCheck={false}
                        disabled={loading}
                        placeholder="Paste a YouTube link"
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        aria-invalid={!!error}
                        aria-describedby={error ? 'url-error' : undefined}
                        className="w-full min-w-0 bg-transparent font-mono text-[14.5px] text-[#f3f4f1] outline-none placeholder:font-sans placeholder:text-[#5a6067] disabled:opacity-50"
                    />
                    {value && !loading ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange('')}
                            aria-label="Clear link"
                            className="size-7 rounded-full text-[#5a6067] hover:bg-[#1a1f25] hover:text-[#8b9198]"
                        >
                            <X className="size-4" />
                        </Button>
                    ) : null}
                    {!value ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handlePaste}
                            className="hidden shrink-0 border-[#23282e] bg-transparent text-xs text-[#8b9198] hover:border-[#323942] hover:bg-[#1a1f25] hover:text-[#f3f4f1] sm:inline-flex"
                        >
                            <ClipboardPaste className="size-3.5" />
                            Paste
                        </Button>
                    ) : null}
                </div>

                <Button
                    type="submit"
                    disabled={!value.trim() || loading}
                    className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#f2a93b] px-4 text-sm font-semibold text-[#1a1204] transition-colors hover:bg-[#ffbe5c] disabled:pointer-events-none disabled:opacity-55 sm:w-auto"
                >
                    {loading ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Sparkles className="size-4" />
                    )}
                    {loading ? 'Loading video' : 'Load video'}
                </Button>
            </form>

            {error ? (
                <p
                    id="url-error"
                    role="alert"
                    className="mt-2.5 flex items-center gap-1.5 px-1 text-[13px] text-[#ef6a5f]"
                >
                    <AlertCircle className="size-3.5 shrink-0" />
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function EmptyState({ loading }: { loading: boolean }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-[#23282e] px-6 py-16 text-center">
            {loading ? (
                <LoaderCircle className="size-5 animate-spin text-[#f2a93b]" />
            ) : (
                <Scissors className="size-5 text-[#5a6067]" />
            )}
            <p className="text-[14px] text-[#8b9198]">
                {loading
                    ? 'Reading the video...'
                    : 'Paste a link above to start marking your clip.'}
            </p>
        </div>
    );
}

function VideoPreview({
    onReset,
    video,
}: {
    onReset: () => void;
    video: VideoMeta;
}) {
    return (
        <div className="flex flex-col gap-5 overflow-hidden rounded-lg border border-[#23282e] bg-[#14181d] p-3 sm:flex-row sm:p-4">
            <Thumbnail
                className="aspect-video w-full shrink-0 rounded-md sm:w-56"
                hue={video.hue}
                showPlay
                thumbnailUrl={video.thumbnailUrl}
            />

            <div className="flex min-w-0 flex-1 flex-col justify-center gap-2 py-1">
                <h2
                    className="truncate text-[15.5px] leading-snug font-semibold text-[#f3f4f1]"
                    title={video.title}
                >
                    {video.title}
                </h2>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-[#8b9198]">
                    <span className="flex items-center gap-1.5">
                        <User2 className="size-3.5 text-[#5a6067]" />
                        {video.channel}
                    </span>
                    <span className="font-mono text-[#5a6067] tabular-nums">
                        {formatTimecode(video.duration, video.duration >= 3600)}{' '}
                        runtime
                    </span>
                </div>
            </div>

            <div className="flex items-start justify-end sm:items-center">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onReset}
                    className="h-8 shrink-0 text-[#8b9198] hover:bg-[#1a1f25] hover:text-[#f3f4f1]"
                >
                    <RotateCcw className="size-3.5" />
                    Change video
                </Button>
            </div>
        </div>
    );
}

function EditorPanel({
    clipLength,
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
    return (
        <div className="relative overflow-hidden rounded-lg border border-[#23282e] bg-[#11151a] shadow-[0_18px_60px_-36px_rgba(0,0,0,0.8)]">
            <div className="flex flex-col gap-3 border-b border-[#23282e] bg-[#14181d] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#f2a93b]/12 text-[#f2a93b]">
                        <SlidersHorizontal className="size-4" />
                    </div>
                    <div className="min-w-0">
                        <h2 className="text-[15px] font-semibold text-[#f3f4f1]">
                            Clip editor
                        </h2>
                        <p className="mt-0.5 font-mono text-[11px] text-[#5a6067] tabular-nums">
                            {formatTimecode(video.duration, video.duration >= 3600)} total
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant="outline"
                        className={cn(
                            'border-[#323942] bg-[#101317] font-mono text-[#8b9198]',
                            isClipTooLong &&
                                'border-[#ef6a5f]/60 text-[#ef6a5f]',
                        )}
                    >
                        <Timer className="size-3" />
                        {formatTimecode(clipLength, video.duration >= 3600)}
                    </Badge>
                    <Badge
                        variant="outline"
                        className="border-[#323942] bg-[#101317] font-mono text-[#8b9198]"
                    >
                        {aspectRatioLabel(options.aspectRatio)} ·{' '}
                        {qualityLabel(options.quality)}
                    </Badge>
                </div>
            </div>

            <div className="grid gap-5 p-4 sm:p-5">
                <Timeline
                    duration={video.duration}
                    hue={video.hue}
                    onRangeChange={onRangeChange}
                    range={range}
                />

                <TimecodeControls
                    clipLength={clipLength}
                    duration={video.duration}
                    isClipTooLong={isClipTooLong}
                    maxClipLength={maxClipLength}
                    onRangeChange={onRangeChange}
                    range={range}
                />

                <ExportOptionsControls
                    disabled={isGenerating}
                    onChange={onOptionsChange}
                    options={options}
                />

                {isClipTooLong ? (
                    <p className="flex items-center gap-1.5 rounded-md border border-[#ef6a5f]/30 bg-[#ef6a5f]/10 px-3 py-2 text-[13px] text-[#ef6a5f]">
                        <AlertCircle className="size-3.5 shrink-0" />
                        Keep clips under {formatTimecode(maxClipLength)}.
                    </p>
                ) : null}

                <div className="flex flex-col justify-between gap-3 border-t border-[#23282e] pt-4 sm:flex-row sm:items-center">
                    <div className="flex flex-wrap gap-2 text-[12px] text-[#5a6067]">
                        <span className="font-mono tabular-nums">
                            Start {formatTimecode(range.start, video.duration >= 3600)}
                        </span>
                        <span className="text-[#323942]">/</span>
                        <span className="font-mono tabular-nums">
                            End {formatTimecode(range.end, video.duration >= 3600)}
                        </span>
                    </div>
                    <Button
                    type="button"
                    onClick={onGenerate}
                    disabled={isClipTooLong || isGenerating}
                        className="h-10 bg-[#f2a93b] px-4 font-semibold text-[#1a1204] hover:bg-[#ffbe5c]"
                >
                    {isGenerating ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Scissors className="size-4" />
                    )}
                    {isGenerating ? 'Generating' : 'Generate clip'}
                    </Button>
                </div>
            </div>

            {isGenerating ? <LoadingOverlay progress={progress} /> : null}
        </div>
    );
}

function ExportOptionsControls({
    disabled,
    onChange,
    options,
}: {
    disabled: boolean;
    onChange: (options: ExportOptions) => void;
    options: ExportOptions;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <OptionSelector
                disabled={disabled}
                icon={<Crop className="size-4" />}
                label="Ratio"
                onChange={(aspectRatio) =>
                    onChange({ ...options, aspectRatio })
                }
                options={aspectRatioOptions}
                value={options.aspectRatio}
            />
            <OptionSelector
                disabled={disabled}
                icon={<Gauge className="size-4" />}
                label="Quality"
                onChange={(quality) => onChange({ ...options, quality })}
                options={qualityOptions}
                value={options.quality}
            />
        </div>
    );
}

function OptionSelector<TValue extends string>({
    disabled,
    icon,
    label,
    onChange,
    options,
    value,
}: {
    disabled: boolean;
    icon: ReactNode;
    label: string;
    onChange: (value: TValue) => void;
    options: Array<{
        description: string;
        label: string;
        value: TValue;
    }>;
    value: TValue;
}) {
    const selected = options.find((option) => option.value === value);

    return (
        <div className="rounded-md border border-[#23282e] bg-[#101317] p-3">
            <div className="mb-3 flex items-center justify-between gap-3">
                <Label className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                    {icon}
                    {label}
                </Label>
                <span className="text-[12px] text-[#5a6067]">
                    {selected?.description}
                </span>
            </div>
            <ToggleGroup
                type="single"
                disabled={disabled}
                value={value}
                onValueChange={(nextValue) => {
                    if (nextValue) {
                        onChange(nextValue as TValue);
                    }
                }}
                variant="outline"
                className="hidden w-full items-stretch overflow-hidden rounded-md border border-[#23282e] bg-[#14181d] sm:grid sm:grid-cols-4"
            >
                {options.map((option) => {
                    return (
                        <ToggleGroupItem
                            key={option.value}
                            value={option.value}
                            aria-label={option.label}
                            className="h-10 border-0 border-l border-[#23282e] bg-transparent text-[12px] text-[#8b9198] first:border-l-0 hover:bg-[#1a1f25] hover:text-[#f3f4f1] data-[state=on]:bg-[#f2a93b] data-[state=on]:text-[#1a1204]"
                        >
                            {option.label}
                        </ToggleGroupItem>
                    );
                })}
            </ToggleGroup>
            <Select
                disabled={disabled}
                value={value}
                onValueChange={(nextValue) => onChange(nextValue as TValue)}
            >
                <SelectTrigger className="h-10 w-full border-[#23282e] bg-[#14181d] text-[#f3f4f1] sm:hidden">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent className="border-[#23282e] bg-[#14181d] text-[#f3f4f1]">
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function Timeline({
    duration,
    hue,
    onRangeChange,
    range,
}: {
    duration: number;
    hue: number;
    onRangeChange: (range: ClipRange) => void;
    range: ClipRange;
}) {
    const frames = useMemo(
        () => Array.from({ length: 14 }, (_, index) => index),
        [],
    );
    const ticks = useMemo(
        () => Array.from({ length: 7 }, (_, index) => (duration / 6) * index),
        [duration],
    );
    const startPercent = (range.start / duration) * 100;
    const endPercent = (range.end / duration) * 100;

    function handleStartChange(value: string) {
        const nextStart = Number(value);
        onRangeChange(
            clampRange(
                {
                    start: Math.min(nextStart, range.end - 1),
                    end: range.end,
                },
                duration,
            ),
        );
    }

    function handleEndChange(value: string) {
        const nextEnd = Number(value);
        onRangeChange(
            clampRange(
                {
                    start: range.start,
                    end: Math.max(nextEnd, range.start + 1),
                },
                duration,
            ),
        );
    }

    return (
        <div className="select-none">
            <div className="relative h-20 w-full overflow-hidden rounded-md border border-[#23282e] bg-[#101317]">
                <div className="absolute inset-0 flex">
                    {frames.map((frame) => (
                        <Thumbnail
                            key={frame}
                            className="h-full flex-1 border-r border-[#0b0d10]/40 last:border-r-0"
                            hue={hue}
                            offset={frame * 11}
                        />
                    ))}
                </div>
                <div
                    className="absolute inset-y-0 left-0 bg-[#0b0d10]/75"
                    style={{ width: `${startPercent}%` }}
                />
                <div
                    className="absolute inset-y-0 right-0 bg-[#0b0d10]/75"
                    style={{ width: `${100 - endPercent}%` }}
                />
                <div
                    className="absolute inset-y-0 border-y-2 border-[#f2a93b] bg-[#f2a93b]/10 shadow-[0_0_0_1px_rgba(242,169,59,0.16),0_8px_28px_-8px_rgba(242,169,59,0.35)]"
                    style={{
                        left: `${startPercent}%`,
                        width: `${endPercent - startPercent}%`,
                    }}
                />
                <TimelineHandle position={startPercent} side="start" />
                <TimelineHandle position={endPercent} side="end" />
            </div>

            <div className="relative mt-2 h-4">
                {ticks.map((tick, index) => (
                    <span
                        key={index}
                        className="absolute -translate-x-1/2 font-mono text-[10.5px] text-[#5a6067] tabular-nums"
                        style={{ left: `${(tick / duration) * 100}%` }}
                    >
                        {formatTimecode(tick, duration >= 3600)}
                    </span>
                ))}
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <label className="grid gap-2 text-left">
                    <span className="font-mono text-[11px] tracking-[0.14em] text-[#5a6067] uppercase">
                        Start marker
                    </span>
                    <input
                        type="range"
                        min={0}
                        max={Math.max(1, Math.floor(duration - 1))}
                        value={Math.floor(range.start)}
                        onChange={(event) =>
                            handleStartChange(event.target.value)
                        }
                        className="accent-[#f2a93b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f2a93b]"
                        aria-label="Clip start marker"
                    />
                </label>
                <label className="grid gap-2 text-left">
                    <span className="font-mono text-[11px] tracking-[0.14em] text-[#5a6067] uppercase">
                        End marker
                    </span>
                    <input
                        type="range"
                        min={1}
                        max={Math.floor(duration)}
                        value={Math.ceil(range.end)}
                        onChange={(event) =>
                            handleEndChange(event.target.value)
                        }
                        className="accent-[#f2a93b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f2a93b]"
                        aria-label="Clip end marker"
                    />
                </label>
            </div>
        </div>
    );
}

function TimecodeControls({
    clipLength,
    duration,
    isClipTooLong,
    maxClipLength,
    onRangeChange,
    range,
}: {
    clipLength: number;
    duration: number;
    isClipTooLong: boolean;
    maxClipLength: number;
    onRangeChange: (range: ClipRange) => void;
    range: ClipRange;
}) {
    const forceHours = duration >= 3600;
    const quickDurations = [15, 30, 60, Math.min(maxClipLength, 180)].filter(
        (value, index, values) =>
            value <= duration && values.indexOf(value) === index,
    );

    function handleStartCommit(value: string) {
        const nextStart = parseTimecode(value);

        if (nextStart === null) {
            return false;
        }

        onRangeChange(
            clampRange(
                {
                    start: Math.min(nextStart, range.end - 1),
                    end: range.end,
                },
                duration,
            ),
        );

        return true;
    }

    function handleEndCommit(value: string) {
        const nextEnd = parseTimecode(value);

        if (nextEnd === null) {
            return false;
        }

        onRangeChange(
            clampRange(
                {
                    start: range.start,
                    end: Math.max(nextEnd, range.start + 1),
                },
                duration,
            ),
        );

        return true;
    }

    function updateStart(nextStart: number) {
        onRangeChange(
            clampRange(
                {
                    start: Math.min(nextStart, range.end - 1),
                    end: range.end,
                },
                duration,
            ),
        );
    }

    function updateEnd(nextEnd: number) {
        onRangeChange(
            clampRange(
                {
                    start: range.start,
                    end: Math.max(nextEnd, range.start + 1),
                },
                duration,
            ),
        );
    }

    function setClipDuration(seconds: number) {
        const nextEnd = Math.min(duration, range.start + seconds);

        onRangeChange(
            clampRange(
                {
                    start: Math.max(0, nextEnd - seconds),
                    end: nextEnd,
                },
                duration,
            ),
        );
    }

    return (
        <div className="grid gap-3">
            <div className="grid gap-3 lg:grid-cols-[1fr_1fr_150px]">
                <ManualTimePoint
                    label="Start"
                    maxSeconds={Math.max(0, duration - 1)}
                    onChange={updateStart}
                    value={range.start}
                />
                <ManualTimePoint
                    label="End"
                    maxSeconds={duration}
                    onChange={updateEnd}
                    value={range.end}
                />
                <TimeStat
                    label="Length"
                    value={formatTimecode(clipLength, forceHours)}
                    warning={isClipTooLong}
                />
            </div>

            <div className="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                <div className="grid gap-2 sm:grid-cols-2">
                    <TimecodeField
                        forceHours={forceHours}
                        label="Start timecode"
                        onCommit={handleStartCommit}
                        value={range.start}
                    />
                    <TimecodeField
                        forceHours={forceHours}
                        label="End timecode"
                        onCommit={handleEndCommit}
                        value={range.end}
                    />
                </div>
                <div className="grid gap-2">
                    <Label className="font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                        Quick length
                    </Label>
                    <div className="flex flex-wrap gap-2">
                        {quickDurations.map((seconds) => (
                            <Button
                                key={seconds}
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setClipDuration(seconds)}
                                className="h-9 border-[#23282e] bg-[#101317] font-mono text-[12px] text-[#8b9198] hover:bg-[#1a1f25] hover:text-[#f3f4f1]"
                            >
                                {formatTimecode(seconds)}
                            </Button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function ManualTimePoint({
    label,
    maxSeconds,
    onChange,
    value,
}: {
    label: string;
    maxSeconds: number;
    onChange: (seconds: number) => void;
    value: number;
}) {
    const parts = secondsToMinuteParts(value);
    const maxParts = secondsToMinuteParts(maxSeconds);

    function updatePart(part: 'minutes' | 'seconds', nextValue: string) {
        const parsedValue = Number(nextValue);
        const safeValue = Number.isFinite(parsedValue) ? parsedValue : 0;
        const nextParts = {
            ...parts,
            [part]:
                part === 'seconds'
                    ? Math.max(0, Math.min(59, safeValue))
                    : Math.max(0, safeValue),
        };

        onChange(
            Math.min(
                maxSeconds,
                Math.floor(nextParts.minutes) * 60 +
                    Math.floor(nextParts.seconds),
            ),
        );
    }

    return (
        <div className="rounded-md border border-[#23282e] bg-[#101317] p-3">
            <div className="mb-3 flex items-center justify-between gap-2">
                <Label className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                    <Clock3 className="size-3.5" />
                    {label}
                </Label>
                <span className="font-mono text-[11px] text-[#5a6067] tabular-nums">
                    {formatTimecode(value, maxSeconds >= 3600)}
                </span>
            </div>
            <div className="grid grid-cols-[1fr_1fr] gap-2">
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${label}-minutes`}
                        className="text-[12px] text-[#8b9198]"
                    >
                        Minutes
                    </Label>
                    <Input
                        id={`${label}-minutes`}
                        type="number"
                        min={0}
                        max={maxParts.minutes}
                        inputMode="numeric"
                        value={parts.minutes}
                        onChange={(event) =>
                            updatePart('minutes', event.target.value)
                        }
                        className="h-10 border-[#23282e] bg-[#14181d] font-mono text-[#f3f4f1] tabular-nums"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${label}-seconds`}
                        className="text-[12px] text-[#8b9198]"
                    >
                        Seconds
                    </Label>
                    <Input
                        id={`${label}-seconds`}
                        type="number"
                        min={0}
                        max={59}
                        inputMode="numeric"
                        value={parts.seconds}
                        onChange={(event) =>
                            updatePart('seconds', event.target.value)
                        }
                        className="h-10 border-[#23282e] bg-[#14181d] font-mono text-[#f3f4f1] tabular-nums"
                    />
                </div>
            </div>
        </div>
    );
}

function TimecodeField({
    forceHours,
    label,
    onCommit,
    value,
}: {
    forceHours: boolean;
    label: string;
    onCommit: (value: string) => boolean;
    value: number;
}) {
    const formattedValue = formatTimecode(value, forceHours);

    function resetInput(input: HTMLInputElement) {
        input.value = formattedValue;
        input.setCustomValidity('');
    }

    function handleCommit(input: HTMLInputElement) {
        if (onCommit(input.value)) {
            input.setCustomValidity('');

            return;
        }

        input.setCustomValidity('Use MM:SS or HH:MM:SS.');
        input.reportValidity();
        resetInput(input);
    }

    return (
        <div className="grid gap-2 rounded-md border border-[#23282e] bg-[#101317] p-3 text-left">
            <Label className="font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                {label}
            </Label>
            <Input
                key={`${label}-${formattedValue}`}
                type="text"
                inputMode="numeric"
                defaultValue={formattedValue}
                onBlur={(event) => handleCommit(event.currentTarget)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        handleCommit(event.currentTarget);
                    }

                    if (event.key === 'Escape') {
                        resetInput(event.currentTarget);
                        event.currentTarget.blur();
                    }
                }}
                aria-label={`${label} timecode`}
                className="h-10 border-[#23282e] bg-[#14181d] font-mono text-[15px] text-[#f3f4f1] tabular-nums"
            />
        </div>
    );
}

function TimelineHandle({
    position,
    side,
}: {
    position: number;
    side: 'start' | 'end';
}) {
    return (
        <div
            className={cn(
                'absolute top-0 z-10 flex h-full w-4 -translate-x-1/2 items-center justify-center',
                side === 'end' && 'translate-x-1/2',
            )}
            style={{ left: `${position}%` }}
        >
            <div className="flex h-full w-2.5 items-center justify-center rounded-sm bg-[#f2a93b]">
                <div className="h-6 w-[3px] rounded-full bg-[#1a1204]/70" />
            </div>
        </div>
    );
}

function TimeStat({
    label,
    value,
    warning = false,
}: {
    label: string;
    value: string;
    warning?: boolean;
}) {
    return (
        <div className="rounded-md border border-[#23282e] bg-[#101317] p-3">
            <p className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                <Timer className="size-3.5" />
                {label}
            </p>
            <p
                className={cn(
                    'mt-2 font-mono text-[18px] font-semibold tabular-nums',
                    warning ? 'text-[#ef6a5f]' : 'text-[#f3f4f1]',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function LoadingOverlay({ progress }: { progress: number }) {
    return (
        <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-[#0b0d10]/78 p-6 backdrop-blur-sm">
            <div className="w-full max-w-[360px] rounded-lg border border-[#23282e] bg-[#14181d] p-5 shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_-12px_rgba(0,0,0,0.55)]">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <span className="text-sm font-medium text-[#f3f4f1]">
                        Preparing clip
                    </span>
                    <span className="font-mono text-xs text-[#8b9198] tabular-nums">
                        {Math.round(progress)}%
                    </span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-[#23282e]">
                    <div
                        className="h-full rounded-full bg-[#f2a93b] transition-[width]"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>
        </div>
    );
}

function ResultCard({
    onRename,
    onReset,
    result,
}: {
    onRename: (fileName: string) => Promise<string>;
    onReset: () => void;
    result: ClipResult;
}) {
    const [draftFileName, setDraftFileName] = useState(
        fileBaseName(result.fileName),
    );
    const [renameError, setRenameError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const cleanDraft = draftFileName.trim();
    const currentBaseName = fileBaseName(result.fileName);
    const canSave =
        cleanDraft.length > 0 && cleanDraft !== currentBaseName && !saving;

    function handleDownload() {
        window.location.href = result.downloadUrl;
    }

    async function handleSaveFileName() {
        if (!canSave) {
            return;
        }

        setSaving(true);
        setRenameError(null);

        try {
            const updatedFileName = await onRename(cleanDraft);
            setDraftFileName(fileBaseName(updatedFileName));
        } catch (caughtError) {
            setRenameError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'File name could not be updated.',
            );
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="rounded-lg border border-[#23282e] bg-[#14181d] p-5">
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#3fd9c7]/12 text-[#3fd9c7]">
                        <Check className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="mb-3 flex items-center gap-2">
                            <p className="text-[15px] font-semibold text-[#f3f4f1]">
                                Clip ready
                            </p>
                            <FilePenLine className="size-4 text-[#5a6067]" />
                        </div>
                        <label className="grid max-w-[420px] gap-2 text-left">
                            <span className="font-mono text-[10.5px] tracking-[0.14em] text-[#5a6067] uppercase">
                                File name
                            </span>
                            <div
                                className={cn(
                                    'flex h-10 items-center rounded-md border bg-[#101317] transition-colors',
                                    renameError
                                        ? 'border-[#ef6a5f]/60'
                                        : 'border-[#23282e] focus-within:border-[#323942]',
                                )}
                            >
                                <input
                                    type="text"
                                    value={draftFileName}
                                    onChange={(event) => {
                                        setDraftFileName(event.target.value);
                                        setRenameError(null);
                                    }}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            void handleSaveFileName();
                                        }
                                    }}
                                    className="min-w-0 flex-1 bg-transparent px-3 text-[14px] text-[#f3f4f1] outline-none placeholder:text-[#5a6067]"
                                    aria-invalid={!!renameError}
                                    aria-describedby={
                                        renameError
                                            ? 'file-name-error'
                                            : undefined
                                    }
                                />
                                <span className="border-l border-[#23282e] px-3 font-mono text-xs text-[#5a6067]">
                                    .mp4
                                </span>
                            </div>
                        </label>
                        {renameError ? (
                            <p
                                id="file-name-error"
                                role="alert"
                                className="mt-2 flex items-center gap-1.5 text-[13px] text-[#ef6a5f]"
                            >
                                <AlertCircle className="size-3.5 shrink-0" />
                                {renameError}
                            </p>
                        ) : null}
                        <p className="mt-2 text-[13px] text-[#8b9198]">
                            {formatTimecode(result.duration)}
                            {' · '}
                            {aspectRatioLabel(result.aspectRatio)}
                            {' · '}
                            {qualityLabel(result.quality)}
                            {result.sizeMb ? ` · ${result.sizeMb} MB` : ''}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => void handleSaveFileName()}
                        disabled={!canSave}
                        className="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-[#323942] px-3 text-sm font-medium text-[#f3f4f1] transition-colors hover:bg-[#1a1f25] disabled:pointer-events-none disabled:opacity-55"
                    >
                        {saving ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Save className="size-4" />
                        )}
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={handleDownload}
                        className="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-[#f2a93b] px-3 text-sm font-semibold text-[#1a1204] transition-colors hover:bg-[#ffbe5c]"
                    >
                        <Download className="size-4" />
                        Download
                    </button>
                    <button
                        type="button"
                        onClick={onReset}
                        className="inline-flex h-9 items-center justify-center rounded-md border border-[#323942] px-3 text-sm font-medium text-[#f3f4f1] transition-colors hover:bg-[#1a1f25]"
                    >
                        New clip
                    </button>
                </div>
            </div>
        </div>
    );
}

function SupportCard() {
    return (
        <section className="px-5 py-6 sm:px-8">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 rounded-lg border border-[#23282e] bg-[#14181d] px-6 py-6 text-center sm:flex-row sm:text-left">
                <div>
                    <p className="flex items-center justify-center gap-2 text-[14.5px] font-medium text-[#f3f4f1] sm:justify-start">
                        <Heart className="size-4 fill-current text-[#ef6a5f]" />
                        FreeKliping stays free and ad-free
                    </p>
                    <p className="mt-1 text-[13.5px] text-[#8b9198]">
                        No plans, no accounts to maintain. A small tip keeps it
                        running.
                    </p>
                </div>
                <a
                    href="https://saweria.co/freekliping"
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex h-10 shrink-0 items-center justify-center rounded-md bg-[#1a1f25] px-4 text-sm font-medium text-[#f3f4f1] transition-colors hover:bg-[#23282e]"
                >
                    Support on Saweria
                </a>
            </div>
            <nav className="mx-auto mt-5 flex max-w-6xl justify-center gap-5 font-mono text-[11px] text-[#5a6067]">
                <Link
                    href={privacy()}
                    className="rounded-sm transition-colors hover:text-[#f3f4f1] focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                >
                    Privacy
                </Link>
                <Link
                    href={terms()}
                    className="rounded-sm transition-colors hover:text-[#f3f4f1] focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                >
                    Terms
                </Link>
            </nav>
        </section>
    );
}

function Thumbnail({
    className,
    hue,
    offset = 0,
    showPlay = false,
    thumbnailUrl = null,
}: {
    className?: string;
    hue: number;
    offset?: number;
    showPlay?: boolean;
    thumbnailUrl?: string | null;
}) {
    const firstHue = (hue + offset) % 360;
    const secondHue = (hue + offset + 44) % 360;

    return (
        <div
            className={cn('relative overflow-hidden bg-[#101317]', className)}
            style={
                thumbnailUrl
                    ? undefined
                    : {
                          backgroundImage: `linear-gradient(135deg, hsl(${firstHue} 55% 22%), hsl(${secondHue} 60% 12%) 60%, #0b0d10 100%)`,
                      }
            }
        >
            {thumbnailUrl ? (
                <img
                    src={thumbnailUrl}
                    alt=""
                    className="absolute inset-0 h-full w-full object-cover"
                    loading="lazy"
                />
            ) : (
                <div
                    className="absolute inset-0 opacity-40 mix-blend-screen"
                    style={{
                        backgroundImage: `radial-gradient(circle at 30% 30%, hsl(${firstHue} 70% 45% / 0.5), transparent 55%)`,
                    }}
                />
            )}
            {showPlay ? (
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-[#0b0d10]/50 ring-1 ring-white/10 backdrop-blur-sm">
                        <Play className="ml-0.5 size-5 fill-[#f3f4f1] text-[#f3f4f1]" />
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function Logomark() {
    return (
        <span className="relative flex size-8 items-center justify-center rounded-md bg-[#f2a93b] text-[#1a1204] shadow-[0_0_0_1px_rgba(242,169,59,0.16),0_8px_28px_-8px_rgba(242,169,59,0.35)]">
            <Scissors className="size-4" />
        </span>
    );
}

function formatTimecode(totalSeconds: number, forceHours = false): string {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    const pad = (value: number) => value.toString().padStart(2, '0');

    if (hours > 0 || forceHours) {
        return `${pad(hours)}:${pad(minutes)}:${pad(remainingSeconds)}`;
    }

    return `${pad(minutes)}:${pad(remainingSeconds)}`;
}

function parseTimecode(value: string): number | null {
    const parts = value
        .trim()
        .split(':')
        .map((part) => part.trim());

    if (
        parts.length < 2 ||
        parts.length > 3 ||
        parts.some((part) => part === '' || !/^\d+$/.test(part))
    ) {
        return null;
    }

    const numbers = parts.map(Number);

    if (numbers.length === 2) {
        const [minutes, seconds] = numbers;

        if (seconds >= 60) {
            return null;
        }

        return minutes * 60 + seconds;
    }

    const [hours, minutes, seconds] = numbers;

    if (minutes >= 60 || seconds >= 60) {
        return null;
    }

    return hours * 3600 + minutes * 60 + seconds;
}

function clampRange(range: ClipRange, duration: number): ClipRange {
    const start = Math.max(0, Math.min(range.start, duration - 1));
    const end = Math.max(start + 1, Math.min(range.end, duration));

    return { start, end };
}

function secondsToMinuteParts(totalSeconds: number): {
    minutes: number;
    seconds: number;
} {
    const seconds = Math.max(0, Math.floor(totalSeconds));

    return {
        minutes: Math.floor(seconds / 60),
        seconds: seconds % 60,
    };
}

function hashString(value: string): number {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = (hash << 5) - hash + value.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
}

function isLikelyYoutubeUrl(value: string): boolean {
    return /^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|m\.youtube\.com\/watch\?v=)[\w-]+/i.test(
        value.trim(),
    );
}

function fileBaseName(fileName: string): string {
    return fileName.replace(/\.mp4$/i, '');
}

function aspectRatioLabel(aspectRatio: ClipAspectRatio): string {
    return (
        aspectRatioOptions.find((option) => option.value === aspectRatio)
            ?.label ?? 'Original'
    );
}

function qualityLabel(quality: ClipQuality): string {
    return (
        qualityOptions.find((option) => option.value === quality)?.label ??
        'Source'
    );
}

function jsonHeaders(): HeadersInit {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

async function errorMessage(
    response: Response,
    fallback: string,
): Promise<string> {
    const payload = (await response.json().catch(() => null)) as
        | ErrorResponse
        | null;

    if (payload?.errors) {
        const firstError = Object.values(payload.errors)[0]?.[0];

        if (firstError) {
            return firstError;
        }
    }

    return payload?.message || fallback;
}
