import { Head, Link } from '@inertiajs/react';
import { Heart, Scissors } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import {
    metadata,
    store as storeClip,
    updateFilename,
} from '@/actions/App/Http/Controllers/ClipController';
import { defaultMaxClipLength } from '@/features/clip-editor/clip-editor.constants';
import type {
    ClipPayload,
    ClipRange,
    ClipResponse,
    ClipResult,
    ExportOptions,
    FlowStage,
    MetadataResponse,
    VideoMeta,
} from '@/features/clip-editor/clip-editor.types';
import {
    errorMessage,
    hashString,
    isLikelyYoutubeUrl,
    jsonHeaders,
} from '@/features/clip-editor/clip-editor.utils';
import { ClipResultCard } from '@/features/clip-editor/components/clip-result-card';
import { EditorPanel } from '@/features/clip-editor/components/editor-panel';
import { EmptyState } from '@/features/clip-editor/components/empty-state';
import { UrlInput } from '@/features/clip-editor/components/url-input';
import { VideoPreview } from '@/features/clip-editor/components/video-preview';
import { privacy, terms } from '@/routes';

export default function Welcome({
    maxClipLength: pageMaxClipLength,
}: {
    maxClipLength?: number;
}) {
    const maxClipLength =
        typeof pageMaxClipLength === 'number'
            ? pageMaxClipLength
            : defaultMaxClipLength;
    const [url, setUrl] = useState('');
    const [stage, setStage] = useState<FlowStage>('idle');
    const [metadataError, setMetadataError] = useState<string | null>(null);
    const [generationError, setGenerationError] = useState<string | null>(null);
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

        if (metadataError) {
            setMetadataError(null);
        }
    }

    function clearProgressTimer() {
        if (progressTimer.current) {
            window.clearInterval(progressTimer.current);
            progressTimer.current = null;
        }
    }

    function resetState() {
        clearProgressTimer();
        setUrl('');
        setMetadataError(null);
        setGenerationError(null);
        setVideo(null);
        setResult(null);
        setStage('idle');
        setProgress(0);
    }

    async function handleLoadVideo() {
        const trimmedUrl = url.trim();

        if (!trimmedUrl) {
            return;
        }

        if (!isLikelyYoutubeUrl(trimmedUrl)) {
            setMetadataError(
                'Gunakan link YouTube lengkap dari youtube.com atau youtu.be.',
            );

            return;
        }

        setMetadataError(null);
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
            const nextVideo: VideoMeta = {
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
            setMetadataError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Video metadata could not be loaded.',
            );
            setStage(video ? 'ready' : 'idle');
        }
    }

    async function handleGenerateClip() {
        if (!video || isClipTooLong) {
            return;
        }

        clearProgressTimer();
        setGenerationError(null);
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
            setGenerationError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Clip could not be started.',
            );
            setStage('ready');
        }
    }

    async function pollClipStatus(statusUrl: string) {
        try {
            const response = await fetch(statusUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(
                    await errorMessage(
                        response,
                        'Clip status could not be read.',
                    ),
                );
            }

            const payload = (await response.json()) as ClipResponse;
            const clip: ClipPayload = payload.clip;

            setProgress(clip.progress);

            if (clip.status === 'completed' && clip.downloadUrl) {
                clearProgressTimer();
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
                clearProgressTimer();
                setGenerationError(
                    clip.errorMessage || 'Clip failed to process.',
                );
                setStage('ready');
            }
        } catch (caughtError) {
            clearProgressTimer();
            setGenerationError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Clip status could not be read.',
            );
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
            <main className="min-h-screen bg-background text-foreground selection:bg-brand selection:text-brand-foreground">
                <header className="sticky top-0 z-40 border-b border-border/80 bg-background/82 backdrop-blur-xl">
                    <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                        <Link
                            href="/"
                            className="flex min-w-0 items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            <Logomark />
                            <span className="truncate text-[17px] font-semibold tracking-tight">
                                FreeKliping
                            </span>
                        </Link>

                        <div className="flex items-center gap-2">
                            <a
                                href="https://saweria.co/freekliping"
                                target="_blank"
                                rel="noreferrer"
                                className="hidden h-9 items-center justify-center gap-2 rounded-md bg-secondary px-3 text-sm font-medium text-foreground transition-colors hover:bg-border sm:inline-flex"
                            >
                                <Heart className="size-3.5 fill-current text-destructive" />
                                Support
                            </a>
                        </div>
                    </nav>
                </header>

                <section className="px-5 pt-16 pb-14 sm:px-8 sm:pt-24 sm:pb-16">
                    <div className="mx-auto flex max-w-[760px] flex-col items-center text-center">
                        <p className="mb-5 font-mono text-[12px] font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            Paste <span className="text-success">-&gt;</span>{' '}
                            Mark <span className="text-success">-&gt;</span>{' '}
                            Clip
                        </p>
                        <h1 className="max-w-[680px] text-[40px] leading-[1.08] font-semibold tracking-normal text-foreground sm:text-[56px]">
                            Cut the good part out.
                        </h1>
                        <p className="mt-4 max-w-[500px] text-[16px] leading-relaxed text-text-secondary sm:text-[17px]">
                            Paste a YouTube link, mark the start and end, and
                            export a clean MP4 without an account.
                        </p>

                        <UrlInput
                            error={metadataError}
                            loading={stage === 'loading'}
                            onChange={handleUrlChange}
                            onSubmit={handleLoadVideo}
                            value={url}
                        />

                        <p className="mt-4 font-mono text-[11.5px] text-muted-foreground">
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
                                        onReset={resetState}
                                        video={video}
                                    />

                                    <EditorPanel
                                        clipLength={clipLength}
                                        generationError={generationError}
                                        isClipTooLong={isClipTooLong}
                                        isGenerating={stage === 'generating'}
                                        maxClipLength={maxClipLength}
                                        onGenerate={handleGenerateClip}
                                        onOptionsChange={setExportOptions}
                                        onRangeChange={setRange}
                                        options={exportOptions}
                                        progress={progress}
                                        range={range}
                                        video={video}
                                    />

                                    {stage === 'done' && result ? (
                                        <ClipResultCard
                                            onRename={handleRenameClip}
                                            onReset={resetState}
                                            result={result}
                                        />
                                    ) : null}
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

function Logomark() {
    return (
        <span className="relative flex size-8 items-center justify-center rounded-md bg-brand text-brand-foreground shadow-[0_0_0_1px_rgba(242,169,59,0.16),0_8px_28px_-8px_rgba(242,169,59,0.35)]">
            <Scissors className="size-4" />
        </span>
    );
}

function SupportCard() {
    return (
        <section className="px-5 py-6 sm:px-8">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 rounded-lg border border-border bg-card px-6 py-6 text-center sm:flex-row sm:text-left">
                <div>
                    <p className="flex items-center justify-center gap-2 text-[14.5px] font-medium text-foreground sm:justify-start">
                        <Heart className="size-4 fill-current text-destructive" />
                        FreeKliping stays free and ad-free
                    </p>
                    <p className="mt-1 text-[13.5px] text-text-secondary">
                        No plans, no accounts to maintain. A small tip keeps it
                        running.
                    </p>
                </div>
                <a
                    href="https://saweria.co/freekliping"
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex h-10 shrink-0 items-center justify-center rounded-md bg-secondary px-4 text-sm font-medium text-foreground transition-colors hover:bg-border"
                >
                    Support on Saweria
                </a>
            </div>
            <nav className="mx-auto mt-5 flex max-w-6xl justify-center gap-5 font-mono text-[11px] text-muted-foreground">
                <Link
                    href={privacy()}
                    className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                >
                    Privacy
                </Link>
                <Link
                    href={terms()}
                    className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                >
                    Terms
                </Link>
            </nav>
        </section>
    );
}
