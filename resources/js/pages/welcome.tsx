import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Copy,
    Cpu,
    Download,
    Github,
    Heart,
    LoaderCircle,
    Menu,
    Minus,
    SlidersHorizontal,
    Sparkles,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import {
    cancel as cancelAnalysis,
    store as analyzeVideo,
} from '@/actions/App/Http/Controllers/ClipAnalysisController';
import {
    store as storeClip,
    updateFilename,
} from '@/actions/App/Http/Controllers/ClipController';
import { store as storeLocalWorkerJob } from '@/actions/App/Http/Controllers/LocalWorkerJobController';
import { BrandLogo } from '@/components/brand-logo';
import { Progress } from '@/components/ui/progress';
import { defaultMaxClipLength } from '@/features/clip-editor/clip-editor.constants';
import type {
    ClipAnalysisPayload,
    ClipAnalysisResponse,
    ClipAnalysisStatus,
    ClipPayload,
    ClipRange,
    ClipRecommendation,
    ClipResponse,
    ClipResult,
    ErrorResponse,
    ExportOptions,
    FlowStage,
    LocalWorkerJobResponse,
    LocalWorkerManifest,
    VideoMeta,
} from '@/features/clip-editor/clip-editor.types';
import {
    errorMessage,
    formatTimecode,
    hashString,
    isLikelyYoutubeUrl,
    jsonHeaders,
} from '@/features/clip-editor/clip-editor.utils';
import { ClipResultCard } from '@/features/clip-editor/components/clip-result-card';
import { EditorPanel } from '@/features/clip-editor/components/editor-panel';
import { EmptyState } from '@/features/clip-editor/components/empty-state';
import { RecommendationGallery } from '@/features/clip-editor/components/recommendation-gallery';
import { UrlInput } from '@/features/clip-editor/components/url-input';
import { VideoPreview } from '@/features/clip-editor/components/video-preview';
import { GITHUB_REPOSITORY_URL } from '@/lib/links';
import { privacy, terms } from '@/routes';

type ClipWorkspaceTab = 'recommended' | 'manual';

type SupportTransparency = {
    caption: string;
    currency: string;
    monthlyTarget: number;
    totalSupported: number;
    progressPercent: number;
    costItems: {
        label: string;
        amount: number;
    }[];
    supporters: {
        name: string;
        amount: number;
    }[];
};

const defaultSupportTransparency: SupportTransparency = {
    caption: 'Bantu bayar sewa server agar FreeKliping tetap aktif.',
    currency: 'IDR',
    monthlyTarget: 500000,
    totalSupported: 0,
    progressPercent: 0,
    costItems: [],
    supporters: [],
};

async function jsonErrorPayload(
    response: Response,
): Promise<ErrorResponse | null> {
    return (await response.json().catch(() => null)) as ErrorResponse | null;
}

export default function Welcome({
    maxClipLength: pageMaxClipLength,
    supportUrl = 'https://saweria.co/freekliping',
    supportTransparency = defaultSupportTransparency,
}: {
    maxClipLength?: number;
    supportUrl?: string;
    supportTransparency?: SupportTransparency;
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
        subtitlesEnabled: false,
        subtitleStyle: 'word-highlight',
        subtitleFontFamily: 'dejavu-sans',
        subtitleFontSize: 'medium',
        subtitlePosition: 'bottom',
        subtitleColor: 'white',
    });
    const [progress, setProgress] = useState(0);
    const [analysisProgress, setAnalysisProgress] = useState(0);
    const [activeAnalysisUuid, setActiveAnalysisUuid] = useState<string | null>(
        null,
    );
    const [analysisCancelling, setAnalysisCancelling] = useState(false);
    const [recommendations, setRecommendations] = useState<
        ClipRecommendation[]
    >([]);
    const [result, setResult] = useState<ClipResult | null>(null);
    const [resultModalOpen, setResultModalOpen] = useState(false);
    const [downloadProgressOpen, setDownloadProgressOpen] = useState(false);
    const [localWorkerManifest, setLocalWorkerManifest] =
        useState<LocalWorkerManifest | null>(null);
    const [localWorkerModalOpen, setLocalWorkerModalOpen] = useState(false);
    const [localWorkerError, setLocalWorkerError] = useState<string | null>(
        null,
    );
    const [localWorkerLoadingId, setLocalWorkerLoadingId] = useState<
        string | null
    >(null);
    const [activeClipTab, setActiveClipTab] =
        useState<ClipWorkspaceTab>('recommended');
    const progressTimer = useRef<number | null>(null);
    const analysisTimer = useRef<number | null>(null);
    const autoDownloadWhenReady = useRef(false);

    const clipLength = range.end - range.start;
    const isClipTooLong = clipLength > maxClipLength;
    const forceHours = video ? video.duration >= 3600 : false;

    useEffect(() => {
        return () => {
            if (progressTimer.current) {
                window.clearInterval(progressTimer.current);
            }

            if (analysisTimer.current) {
                window.clearInterval(analysisTimer.current);
            }
        };
    }, []);

    function handleUrlChange(value: string) {
        setUrl(value);
        setResult(null);
        setDownloadProgressOpen(false);
        setLocalWorkerManifest(null);
        setLocalWorkerModalOpen(false);
        setLocalWorkerError(null);
        setLocalWorkerLoadingId(null);
        setRecommendations([]);
        setActiveClipTab('recommended');
        setActiveAnalysisUuid(null);

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

    function clearAnalysisTimer() {
        if (analysisTimer.current) {
            window.clearInterval(analysisTimer.current);
            analysisTimer.current = null;
        }
    }

    function resetState() {
        clearAnalysisTimer();
        clearProgressTimer();
        setUrl('');
        setMetadataError(null);
        setGenerationError(null);
        setVideo(null);
        setResult(null);
        setResultModalOpen(false);
        setDownloadProgressOpen(false);
        setLocalWorkerManifest(null);
        setLocalWorkerModalOpen(false);
        setLocalWorkerError(null);
        setLocalWorkerLoadingId(null);
        autoDownloadWhenReady.current = false;
        setRecommendations([]);
        setActiveClipTab('recommended');
        setActiveAnalysisUuid(null);
        setAnalysisCancelling(false);
        setStage('idle');
        setProgress(0);
        setAnalysisProgress(0);
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
        setAnalysisProgress(5);
        setRecommendations([]);
        setResult(null);
        setResultModalOpen(false);
        setDownloadProgressOpen(false);
        setLocalWorkerManifest(null);
        setLocalWorkerModalOpen(false);
        setLocalWorkerError(null);
        setLocalWorkerLoadingId(null);
        autoDownloadWhenReady.current = false;
        setActiveClipTab('recommended');
        clearAnalysisTimer();

        try {
            const route = analyzeVideo();
            const response = await fetch(route.url, {
                body: JSON.stringify({ url: trimmedUrl }),
                headers: jsonHeaders(),
                method: route.method.toUpperCase(),
            });

            if (!response.ok) {
                const payload = await jsonErrorPayload(response);

                if (response.status === 429 && payload?.analysis) {
                    setMetadataError(
                        payload.message ||
                            'Analisis yang masih berjalan ditampilkan di bawah.',
                    );
                    await startAnalysisPolling(payload.analysis);

                    return;
                }

                throw new Error(
                    payload?.message || 'Video metadata could not be loaded.',
                );
            }

            const payload = (await response.json()) as ClipAnalysisResponse;

            if (payload.analysis.status === 'completed') {
                completeAnalysis(payload.analysis);

                return;
            }

            await startAnalysisPolling(payload.analysis);
        } catch (caughtError) {
            setMetadataError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Video could not be analyzed.',
            );
            setStage(video ? 'ready' : 'idle');
        }
    }

    function applyAnalysisPayload(analysis: ClipAnalysisPayload) {
        const nextVideo: VideoMeta = {
            ...analysis.video,
            hue: hashString(analysis.video.id) % 360,
        };

        setUrl(analysis.sourceUrl);
        setVideo(nextVideo);
        setAnalysisProgress(analysis.progress);
        setActiveAnalysisUuid(analysis.uuid);
    }

    async function startAnalysisPolling(analysis: ClipAnalysisPayload) {
        clearAnalysisTimer();
        applyAnalysisPayload(analysis);
        setRecommendations(analysis.recommendations);
        setActiveClipTab('recommended');
        setStage('analyzing');

        const status = await pollAnalysisStatus(analysis.statusUrl);

        if (status !== 'queued' && status !== 'processing') {
            return;
        }

        analysisTimer.current = window.setInterval(() => {
            void pollAnalysisStatus(analysis.statusUrl);
        }, 1600);
    }

    function completeAnalysis(analysis: ClipAnalysisPayload) {
        clearAnalysisTimer();
        applyAnalysisPayload(analysis);
        setRecommendations(analysis.recommendations);

        const firstRecommendation = analysis.recommendations[0];

        if (firstRecommendation) {
            setRange({
                start: firstRecommendation.startSeconds,
                end: firstRecommendation.endSeconds,
            });
        }

        setExportOptions((current) => ({
            ...current,
            aspectRatio: '9:16',
            subtitlesEnabled: true,
        }));
        setActiveClipTab(
            analysis.recommendations.length > 0 ? 'recommended' : 'manual',
        );
        setActiveAnalysisUuid(null);
        setStage('ready');
    }

    async function pollAnalysisStatus(
        statusUrl: string,
    ): Promise<ClipAnalysisStatus | null> {
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
                        'Video analysis status could not be read.',
                    ),
                );
            }

            const payload = (await response.json()) as ClipAnalysisResponse;
            const analysis = payload.analysis;

            applyAnalysisPayload(analysis);

            if (analysis.status === 'completed') {
                completeAnalysis(analysis);
            }

            if (analysis.status === 'failed') {
                clearAnalysisTimer();
                setMetadataError(
                    analysis.errorMessage || 'Video analysis failed.',
                );
                setStage(video ? 'ready' : 'idle');
            }

            if (analysis.status === 'cancelled') {
                clearAnalysisTimer();
                setMetadataError('Analisis video dibatalkan.');
                setStage('idle');
                setVideo(null);
                setRecommendations([]);
                setActiveAnalysisUuid(null);
            }

            return analysis.status;
        } catch (caughtError) {
            clearAnalysisTimer();
            setMetadataError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Video analysis status could not be read.',
            );
            setStage(video ? 'ready' : 'idle');

            return null;
        }
    }

    async function handleCancelAnalysis() {
        if (!activeAnalysisUuid || analysisCancelling) {
            return;
        }

        setAnalysisCancelling(true);

        try {
            const route = cancelAnalysis({ analysis: activeAnalysisUuid });
            const response = await fetch(route.url, {
                headers: jsonHeaders(),
                method: route.method.toUpperCase(),
            });

            if (!response.ok) {
                throw new Error(
                    await errorMessage(
                        response,
                        'Video analysis could not be cancelled.',
                    ),
                );
            }

            clearAnalysisTimer();
            setMetadataError(null);
            setStage('idle');
            setVideo(null);
            setRecommendations([]);
            setActiveAnalysisUuid(null);
            setAnalysisProgress(0);
            setActiveClipTab('recommended');
        } catch (caughtError) {
            setMetadataError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Video analysis could not be cancelled.',
            );
        } finally {
            setAnalysisCancelling(false);
        }
    }

    function handleSelectRecommendation(recommendation: ClipRecommendation) {
        setRange({
            start: recommendation.startSeconds,
            end: recommendation.endSeconds,
        });
        setActiveClipTab('manual');
    }

    async function handlePrepareLocalWorkerJob(
        recommendation: ClipRecommendation,
        optionOverrides: Partial<ExportOptions>,
    ) {
        if (!video) {
            return;
        }

        const rangeToUse = {
            start: recommendation.startSeconds,
            end: recommendation.endSeconds,
        };
        const optionsToUse = {
            ...exportOptions,
            ...optionOverrides,
            subtitlesEnabled: optionOverrides.subtitlesEnabled ?? true,
        };
        const lengthToUse = rangeToUse.end - rangeToUse.start;

        if (lengthToUse > maxClipLength) {
            return;
        }

        setRange(rangeToUse);
        setExportOptions(optionsToUse);
        setLocalWorkerManifest(null);
        setLocalWorkerError(null);
        setLocalWorkerLoadingId(recommendation.id);

        try {
            const route = storeLocalWorkerJob();
            const response = await fetch(route.url, {
                body: JSON.stringify({
                    url: url.trim(),
                    title: video.title,
                    channel: video.channel,
                    duration_seconds: Math.floor(video.duration),
                    start_seconds: Math.floor(rangeToUse.start),
                    end_seconds: Math.ceil(rangeToUse.end),
                    aspect_ratio: optionsToUse.aspectRatio,
                    quality: optionsToUse.quality,
                    subtitles_enabled: optionsToUse.subtitlesEnabled,
                    subtitle_style: optionsToUse.subtitleStyle,
                    subtitle_font_family: optionsToUse.subtitleFontFamily,
                    subtitle_font_size: optionsToUse.subtitleFontSize,
                    subtitle_position: optionsToUse.subtitlePosition,
                    subtitle_color: optionsToUse.subtitleColor,
                    sync_output: false,
                }),
                headers: jsonHeaders(),
                method: route.method.toUpperCase(),
            });

            if (!response.ok) {
                throw new Error(
                    await errorMessage(
                        response,
                        'Local worker job could not be prepared.',
                    ),
                );
            }

            const payload = (await response.json()) as LocalWorkerJobResponse;

            setLocalWorkerManifest(payload.localWorkerJob.manifest);
            setLocalWorkerModalOpen(true);
        } catch (caughtError) {
            setLocalWorkerError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Local worker job could not be prepared.',
            );
            setLocalWorkerModalOpen(true);
        } finally {
            setLocalWorkerLoadingId(null);
        }
    }

    async function handleGenerateClip(
        recommendation?: ClipRecommendation,
        optionOverrides?: Partial<ExportOptions>,
    ) {
        if (!video) {
            return;
        }

        const rangeToUse = recommendation
            ? {
                  start: recommendation.startSeconds,
                  end: recommendation.endSeconds,
              }
            : range;
        const optionsToUse = {
            ...exportOptions,
            ...optionOverrides,
            subtitlesEnabled:
                optionOverrides?.subtitlesEnabled ??
                (recommendation ? true : exportOptions.subtitlesEnabled),
        };
        const lengthToUse = rangeToUse.end - rangeToUse.start;

        if (lengthToUse > maxClipLength) {
            return;
        }

        setRange(rangeToUse);
        setExportOptions(optionsToUse);
        clearProgressTimer();
        setGenerationError(null);
        autoDownloadWhenReady.current = !!recommendation;
        setDownloadProgressOpen(!!recommendation);
        setStage('generating');
        setProgress(5);

        try {
            const route = storeClip();
            const response = await fetch(route.url, {
                body: JSON.stringify({
                    url: url.trim(),
                    start_seconds: Math.floor(rangeToUse.start),
                    end_seconds: Math.ceil(rangeToUse.end),
                    aspect_ratio: optionsToUse.aspectRatio,
                    quality: optionsToUse.quality,
                    rights_confirmed: true,
                    subtitles_enabled: optionsToUse.subtitlesEnabled,
                    subtitle_style: optionsToUse.subtitleStyle,
                    subtitle_font_family: optionsToUse.subtitleFontFamily,
                    subtitle_font_size: optionsToUse.subtitleFontSize,
                    subtitle_position: optionsToUse.subtitlePosition,
                    subtitle_color: optionsToUse.subtitleColor,
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
            setDownloadProgressOpen(false);
            autoDownloadWhenReady.current = false;
            setActiveClipTab('manual');
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
                    subtitleStatus: clip.subtitleStatus,
                    previewUrl: clip.previewUrl,
                    uuid: clip.uuid,
                });
                setResultModalOpen(true);
                setDownloadProgressOpen(false);
                setStage('done');

                if (autoDownloadWhenReady.current) {
                    autoDownloadWhenReady.current = false;
                    window.location.href = clip.downloadUrl;
                }
            }

            if (clip.status === 'failed') {
                clearProgressTimer();
                setGenerationError(
                    clip.errorMessage || 'Clip failed to process.',
                );
                setDownloadProgressOpen(false);
                autoDownloadWhenReady.current = false;
                setStage('ready');
            }
        } catch (caughtError) {
            clearProgressTimer();
            setGenerationError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Clip status could not be read.',
            );
            setDownloadProgressOpen(false);
            autoDownloadWhenReady.current = false;
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
            subtitleStatus: clip.subtitleStatus ?? result.subtitleStatus,
            previewUrl: clip.previewUrl ?? result.previewUrl,
            uuid: clip.uuid,
        });
        setResultModalOpen(true);

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
                            className="flex min-w-0 items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            <BrandLogo />
                        </Link>

                        <div className="flex items-center gap-2">
                            <a
                                href={GITHUB_REPOSITORY_URL}
                                target="_blank"
                                rel="noreferrer"
                                className="hidden h-9 items-center justify-center gap-2 rounded-md bg-secondary px-3 text-sm font-medium text-foreground transition-colors hover:bg-border sm:inline-flex"
                            >
                                <Github className="size-3.5" />
                                GitHub
                            </a>
                            <a
                                href={supportUrl}
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
                            loadingLabel="Starting analysis"
                            onChange={handleUrlChange}
                            onSubmit={handleLoadVideo}
                            submitLabel="Find clips"
                            value={url}
                        />

                        <p className="mt-4 font-mono text-[11.5px] text-muted-foreground">
                            Free · No login · No watermark · MP4 out
                        </p>
                    </div>
                </section>

                <section className="px-5 pb-20 sm:px-8">
                    <div className="mx-auto max-w-6xl">
                        {stage === 'idle' || stage === 'loading' ? (
                            <EmptyState loading={stage === 'loading'} />
                        ) : (
                            video && (
                                <div className="flex flex-col gap-5">
                                    <VideoPreview
                                        onReset={resetState}
                                        video={video}
                                    />

                                    {stage === 'done' &&
                                    result &&
                                    !resultModalOpen ? (
                                        <ClipReadyBanner
                                            onOpen={() =>
                                                setResultModalOpen(true)
                                            }
                                            result={result}
                                        />
                                    ) : null}

                                    <ClipWorkspaceTabs
                                        activeTab={activeClipTab}
                                        onChange={setActiveClipTab}
                                        recommendationCount={
                                            recommendations.length
                                        }
                                    />

                                    {activeClipTab === 'recommended' ? (
                                        <RecommendationGallery
                                            disabled={stage === 'generating'}
                                            forceHours={forceHours}
                                            loading={stage === 'analyzing'}
                                            localWorkerLoadingId={
                                                localWorkerLoadingId
                                            }
                                            onCancel={
                                                analysisCancelling
                                                    ? undefined
                                                    : handleCancelAnalysis
                                            }
                                            onGenerate={(
                                                recommendation,
                                                options,
                                            ) => {
                                                void handleGenerateClip(
                                                    recommendation,
                                                    options,
                                                );
                                            }}
                                            onPrepareLocal={(
                                                recommendation,
                                                options,
                                            ) => {
                                                void handlePrepareLocalWorkerJob(
                                                    recommendation,
                                                    options,
                                                );
                                            }}
                                            onSelect={
                                                handleSelectRecommendation
                                            }
                                            progress={analysisProgress}
                                            recommendations={recommendations}
                                            video={video}
                                        />
                                    ) : (
                                        <EditorPanel
                                            canGenerate
                                            clipLength={clipLength}
                                            generationError={generationError}
                                            isClipTooLong={isClipTooLong}
                                            isGenerating={
                                                stage === 'generating'
                                            }
                                            maxClipLength={maxClipLength}
                                            onGenerate={() => {
                                                void handleGenerateClip();
                                            }}
                                            onOptionsChange={setExportOptions}
                                            onRangeChange={setRange}
                                            options={exportOptions}
                                            progress={progress}
                                            range={range}
                                            video={video}
                                        />
                                    )}

                                    <ClipResultModal
                                        onClose={() =>
                                            setResultModalOpen(false)
                                        }
                                        open={!!result && resultModalOpen}
                                    >
                                        {result ? (
                                            <ClipResultCard
                                                onRename={handleRenameClip}
                                                onReset={resetState}
                                                result={result}
                                            />
                                        ) : null}
                                    </ClipResultModal>

                                    <DownloadProgressModal
                                        open={
                                            downloadProgressOpen &&
                                            stage === 'generating'
                                        }
                                        progress={progress}
                                    />

                                    <LocalWorkerManifestModal
                                        error={localWorkerError}
                                        manifest={localWorkerManifest}
                                        onClose={() =>
                                            setLocalWorkerModalOpen(false)
                                        }
                                        open={localWorkerModalOpen}
                                    />
                                </div>
                            )
                        )}
                    </div>
                </section>

                <SupportCard
                    support={supportTransparency}
                    supportUrl={supportUrl}
                />
            </main>
        </>
    );
}

function LocalWorkerManifestModal({
    error,
    manifest,
    onClose,
    open,
}: {
    error: string | null;
    manifest: LocalWorkerManifest | null;
    onClose: () => void;
    open: boolean;
}) {
    const [copied, setCopied] = useState(false);
    const manifestJson = manifest ? JSON.stringify(manifest, null, 2) : '';

    function handleClose() {
        setCopied(false);
        onClose();
    }

    async function handleCopy() {
        if (!manifestJson) {
            return;
        }

        await navigator.clipboard.writeText(manifestJson);
        setCopied(true);
    }

    if (!open) {
        return null;
    }

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Local worker job"
            className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-background/72 px-4 py-6 backdrop-blur-md"
        >
            <button
                type="button"
                aria-label="Close local worker dialog"
                onClick={handleClose}
                className="absolute inset-0 cursor-default"
            />
            <div className="relative max-h-[calc(100dvh-3rem)] w-full max-w-[680px] overflow-y-auto rounded-lg border border-border bg-card shadow-[0_24px_80px_-44px_rgba(0,0,0,0.95)]">
                <button
                    type="button"
                    aria-label="Close"
                    onClick={handleClose}
                    className="absolute top-3 right-3 z-10 flex size-8 items-center justify-center rounded-md bg-background/85 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    <X className="size-4" />
                </button>

                <div className="border-b border-border p-5 pr-14">
                    <div className="flex items-start gap-4">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-md bg-brand/12 text-brand ring-1 ring-brand/20">
                            <Cpu className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[17px] font-semibold text-foreground">
                                Local worker job
                            </p>
                            <p className="mt-1 text-[13px] leading-relaxed text-text-secondary">
                                Render instruction is ready for a local worker.
                                Cookies and platform credentials must stay on
                                this device.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 p-5">
                    {error ? (
                        <div className="rounded-md border border-destructive/35 bg-destructive/10 px-3 py-2 text-[13px] text-destructive">
                            {error}
                        </div>
                    ) : null}

                    {manifest ? (
                        <>
                            <div className="grid gap-3 rounded-md border border-border bg-surface-2 p-3 sm:grid-cols-3">
                                <LocalWorkerMetric
                                    label="Runner"
                                    value={manifest.runner ?? 'local'}
                                />
                                <LocalWorkerMetric
                                    label="Output"
                                    value={
                                        manifest.output?.defaultFileName ??
                                        'clip.mp4'
                                    }
                                />
                                <LocalWorkerMetric
                                    label="Callback"
                                    value={
                                        manifest.callbacks?.method ?? 'PATCH'
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-[13px] font-medium text-foreground">
                                        Manifest JSON
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            void handleCopy();
                                        }}
                                        className="inline-flex h-8 items-center justify-center gap-2 rounded-md border border-border bg-background px-3 text-[12px] font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                    >
                                        <Copy className="size-3.5" />
                                        {copied ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                                <pre className="max-h-[320px] overflow-auto rounded-md border border-border bg-background p-3 text-[11px] leading-relaxed text-muted-foreground">
                                    {manifestJson}
                                </pre>
                            </div>
                        </>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function LocalWorkerMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="font-mono text-[10.5px] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 truncate text-[12.5px] font-medium text-foreground">
                {value}
            </p>
        </div>
    );
}

function ClipReadyBanner({
    onOpen,
    result,
}: {
    onOpen: () => void;
    result: ClipResult;
}) {
    function handleDownload() {
        window.location.href = result.downloadUrl;
    }

    return (
        <section className="flex flex-col gap-3 rounded-lg border border-success/30 bg-success/10 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-success/12 text-success ring-1 ring-success/20">
                    <CheckCircle2 className="size-4" />
                </div>
                <div className="min-w-0">
                    <p className="text-[14px] font-semibold text-foreground">
                        Clip ready
                    </p>
                    <p className="mt-1 truncate text-[13px] text-text-secondary">
                        {result.fileName} · {formatTimecode(result.duration)}
                    </p>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-2 sm:flex">
                <button
                    type="button"
                    onClick={onOpen}
                    className="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    Open
                </button>
                <button
                    type="button"
                    onClick={handleDownload}
                    className="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    <Download className="size-4" />
                    Download
                </button>
            </div>
        </section>
    );
}

function DownloadProgressModal({
    open,
    progress,
}: {
    open: boolean;
    progress: number;
}) {
    if (!open) {
        return null;
    }

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Preparing download"
            className="fixed inset-0 z-50 grid place-items-center bg-background/72 px-4 py-6 backdrop-blur-md"
        >
            <div className="w-full max-w-[460px] rounded-lg border border-border bg-card p-5 shadow-[0_24px_80px_-44px_rgba(0,0,0,0.95)]">
                <div className="flex items-start gap-4">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-md bg-brand/12 text-brand ring-1 ring-brand/20">
                        <LoaderCircle className="size-5 animate-spin" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-[17px] font-semibold text-foreground">
                            Preparing download
                        </p>
                        <p className="mt-1 text-[13px] leading-relaxed text-text-secondary">
                            Rendering your clip now. The download will start
                            automatically when the file is ready.
                        </p>
                    </div>
                </div>

                <div className="mt-5 grid gap-2">
                    <div className="flex items-center justify-between font-mono text-[11px] text-muted-foreground">
                        <span>Rendering</span>
                        <span>{Math.round(progress)}%</span>
                    </div>
                    <Progress value={progress} className="h-2" />
                </div>
            </div>
        </div>
    );
}

function ClipResultModal({
    children,
    onClose,
    open,
}: {
    children: ReactNode;
    onClose: () => void;
    open: boolean;
}) {
    useEffect(() => {
        if (!open) {
            return;
        }

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [onClose, open]);

    if (!open) {
        return null;
    }

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Clip ready"
            className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-background/72 px-4 py-6 backdrop-blur-md"
        >
            <button
                type="button"
                aria-label="Close clip ready dialog"
                onClick={onClose}
                className="absolute inset-0 cursor-default"
            />
            <div className="relative max-h-[calc(100dvh-3rem)] w-full max-w-[620px] overflow-y-auto rounded-lg">
                <button
                    type="button"
                    aria-label="Close"
                    onClick={onClose}
                    className="absolute top-3 right-3 z-10 flex size-8 items-center justify-center rounded-md bg-background/85 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    <X className="size-4" />
                </button>
                {children}
            </div>
        </div>
    );
}

function ClipWorkspaceTabs({
    activeTab,
    onChange,
    recommendationCount,
}: {
    activeTab: ClipWorkspaceTab;
    onChange: (tab: ClipWorkspaceTab) => void;
    recommendationCount: number;
}) {
    const tabs: Array<{
        count?: number;
        icon: ReactNode;
        label: string;
        value: ClipWorkspaceTab;
    }> = [
        {
            count: Math.min(recommendationCount, 6),
            icon: <Sparkles className="size-4" />,
            label: 'Recommended Clips',
            value: 'recommended',
        },
        {
            icon: <SlidersHorizontal className="size-4" />,
            label: 'Manual Clips',
            value: 'manual',
        },
    ];

    return (
        <div
            role="tablist"
            aria-label="Clip workflow"
            className="grid gap-2 rounded-lg border border-border bg-card p-1.5 sm:grid-cols-2"
        >
            {tabs.map((tab) => {
                const selected = activeTab === tab.value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(tab.value)}
                        className={[
                            'flex h-11 items-center justify-center gap-2 rounded-md px-3 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                            selected
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        ].join(' ')}
                    >
                        {tab.icon}
                        <span>{tab.label}</span>
                        {typeof tab.count === 'number' ? (
                            <span
                                className={[
                                    'rounded-md px-1.5 py-0.5 font-mono text-[11px]',
                                    selected
                                        ? 'bg-primary-foreground/18 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ].join(' ')}
                            >
                                {tab.count}/6
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}

function SupportCard({
    support,
    supportUrl,
}: {
    support: SupportTransparency;
    supportUrl: string;
}) {
    const progress = Math.max(0, Math.min(100, support.progressPercent));
    const [minimized, setMinimized] = useState(false);

    return (
        <aside className="fixed inset-x-4 bottom-4 z-30 sm:inset-x-auto sm:right-5 sm:bottom-5 sm:w-[360px] lg:right-8">
            {minimized ? (
                <button
                    type="button"
                    onClick={() => setMinimized(false)}
                    className="ml-auto flex h-12 max-w-full items-center gap-2 rounded-lg border border-amber-300/55 bg-background/62 px-3 text-sm font-semibold text-foreground shadow-xl shadow-black/12 backdrop-blur-xl transition-colors hover:bg-background/82 focus-visible:ring-2 focus-visible:ring-brand"
                    aria-label="Buka card dukungan"
                >
                    <Menu className="size-4 text-amber-500" />
                    <span className="truncate">Bantu server</span>
                    <span className="rounded-md bg-amber-300/70 px-2 py-0.5 font-mono text-xs text-stone-950">
                        {progress}%
                    </span>
                </button>
            ) : (
                <div className="max-h-[72dvh] overflow-y-auto rounded-lg border border-amber-300/45 bg-background/58 shadow-2xl shadow-black/18 backdrop-blur-xl">
                    <div className="border-b border-amber-200/45 bg-amber-50/62 px-4 py-4 text-stone-950 backdrop-blur-xl">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="flex items-center gap-2 text-sm font-semibold">
                                    <Heart className="size-4 fill-current text-amber-500" />
                                    FreeKliping gratis
                                </p>
                                <p className="mt-1 text-xs leading-5 text-stone-700">
                                    {support.caption}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <div className="rounded-md bg-white/72 px-2.5 py-1 text-sm font-bold text-stone-950 shadow-xs">
                                    {progress}%
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setMinimized(true)}
                                    className="flex size-8 items-center justify-center rounded-md bg-white/64 text-stone-700 transition-colors hover:bg-white/88 hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-brand"
                                    aria-label="Minimize card dukungan"
                                >
                                    <Minus className="size-4" />
                                </button>
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="mb-1 flex items-center justify-between text-[11px] font-semibold tracking-normal text-stone-700 uppercase">
                                <span>Progress sewa server bulan ini</span>
                                <span>{progress}%</span>
                            </div>
                            <Progress
                                value={progress}
                                className="h-2 bg-white"
                            />
                        </div>
                    </div>

                    <div className="space-y-4 px-4 py-4">
                        <a
                            href={supportUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex h-10 w-full items-center justify-center rounded-md bg-[#f6c945]/88 px-4 text-sm font-bold text-stone-950 transition-colors hover:bg-[#f6c945] focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Kirim Dukungan via Saweria
                        </a>

                        <div>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        Top dukungan bulan ini
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Dari terbanyak ke terendah.
                                    </p>
                                </div>
                            </div>

                            {support.supporters.length > 0 ? (
                                <ol className="mt-3 space-y-2">
                                    {support.supporters.map(
                                        (supporter, index) => (
                                            <li
                                                key={`${supporter.name}-${index}`}
                                                className="flex items-center justify-between gap-3 rounded-md bg-background/62 px-3 py-2 backdrop-blur"
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-100/86 font-mono text-xs font-semibold text-stone-800">
                                                        {index + 1}
                                                    </span>
                                                    <span className="truncate text-sm font-medium text-foreground">
                                                        {supporter.name}
                                                    </span>
                                                </div>
                                                <span className="shrink-0 text-sm font-semibold text-foreground">
                                                    {formatMoney(
                                                        supporter.amount,
                                                        support.currency,
                                                    )}
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ol>
                            ) : (
                                <p className="mt-3 rounded-md bg-background/62 px-3 py-3 text-sm text-muted-foreground backdrop-blur">
                                    Belum ada supporter bulan ini.
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between border-t border-border/70 pt-3 font-mono text-[11px] text-muted-foreground">
                            <div className="flex gap-3">
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
                            </div>
                            <a
                                href={GITHUB_REPOSITORY_URL}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                GitHub
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </aside>
    );
}

function formatMoney(amount: number, currency: string) {
    try {
        return new Intl.NumberFormat('id-ID', {
            currency,
            maximumFractionDigits: 0,
            style: 'currency',
        }).format(amount);
    } catch {
        return `Rp${Math.round(amount).toLocaleString('id-ID')}`;
    }
}
