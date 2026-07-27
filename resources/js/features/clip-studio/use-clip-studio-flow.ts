import { useEffect, useRef, useState } from 'react';

import {
    cancel as cancelAnalysis,
    store as analyzeVideo,
} from '@/actions/App/Http/Controllers/ClipAnalysisController';
import {
    store as storeClip,
    updateFilename,
} from '@/actions/App/Http/Controllers/ClipController';
import { store as storeLocalWorkerJob } from '@/actions/App/Http/Controllers/LocalWorkerJobController';
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
    hashString,
    isLikelyYoutubeUrl,
    jsonHeaders,
} from '@/features/clip-editor/clip-editor.utils';

import type { ClipWorkspaceTab } from './types';

async function jsonErrorPayload(
    response: Response,
): Promise<ErrorResponse | null> {
    return (await response.json().catch(() => null)) as ErrorResponse | null;
}

export function useClipStudioFlow(pageMaxClipLength?: number) {
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
    const [generationStatus, setGenerationStatus] =
        useState<ClipPayload['status']>('queued');
    const [queuedSeconds, setQueuedSeconds] = useState(0);
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
        setGenerationStatus('queued');
        setQueuedSeconds(0);
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
        setGenerationStatus('queued');
        setQueuedSeconds(0);

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
            setGenerationStatus(clip.status);
            setQueuedSeconds(clip.queuedSeconds);

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

    return {
        activeClipTab,
        analysisCancelling,
        analysisProgress,
        clipLength,
        downloadProgressOpen,
        exportOptions,
        forceHours,
        generationError,
        generationStatus,
        handleCancelAnalysis,
        handleGenerateClip,
        handleLoadVideo,
        handlePrepareLocalWorkerJob,
        handleRenameClip,
        handleSelectRecommendation,
        handleUrlChange,
        isClipTooLong,
        localWorkerError,
        localWorkerLoadingId,
        localWorkerManifest,
        localWorkerModalOpen,
        maxClipLength,
        metadataError,
        progress,
        queuedSeconds,
        range,
        recommendations,
        resetState,
        result,
        resultModalOpen,
        setActiveClipTab,
        setExportOptions,
        setLocalWorkerModalOpen,
        setRange,
        setResultModalOpen,
        stage,
        url,
        video,
    };
}
