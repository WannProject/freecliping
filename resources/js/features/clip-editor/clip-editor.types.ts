export type FlowStage =
    'idle' | 'loading' | 'analyzing' | 'ready' | 'generating' | 'done';

export interface ClipRange {
    start: number;
    end: number;
}

export type ClipAspectRatio = 'original' | '16:9' | '9:16' | '1:1';
export type ClipQuality = 'source' | '480p' | '720p' | '1080p';
export type CaptionKind = 'none' | 'manual' | 'auto';
export type SubtitleStyle = 'word-highlight' | 'classic' | 'neon-box';
export type SubtitleFontFamily = 'dejavu-sans' | 'arial' | 'impact';
export type SubtitleFontSize = 'small' | 'medium' | 'large';
export type SubtitlePosition = 'bottom' | 'center' | 'top';
export type SubtitleColor = 'white' | 'yellow' | 'cyan';

export interface CaptionAvailability {
    available: boolean;
    kind: CaptionKind;
    language: string | null;
}

export interface ExportOptions {
    aspectRatio: ClipAspectRatio;
    quality: ClipQuality;
    subtitlesEnabled: boolean;
    subtitleStyle: SubtitleStyle;
    subtitleFontFamily: SubtitleFontFamily;
    subtitleFontSize: SubtitleFontSize;
    subtitlePosition: SubtitlePosition;
    subtitleColor: SubtitleColor;
}

export interface VideoMeta {
    channel: string;
    duration: number;
    hue: number;
    id: string;
    thumbnailUrl: string | null;
    title: string;
    captions: CaptionAvailability;
}

export interface ClipResult {
    aspectRatio: ClipAspectRatio;
    downloadUrl: string;
    duration: number;
    fileName: string;
    quality: ClipQuality;
    previewUrl: string | null;
    sizeMb: number | null;
    subtitleStatus: SubtitleStatusValue;
    uuid: string;
}

export type SubtitleStatusValue = 'burned' | 'unavailable' | 'failed' | null;

export interface MetadataResponse {
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
        captions: CaptionAvailability;
    };
}

export interface ErrorResponse {
    analysis?: ClipAnalysisPayload | null;
    errors?: Record<string, string[]>;
    message?: string;
}

export interface ClipPayload {
    aspectRatio: ClipAspectRatio;
    downloadUrl: string | null;
    duration: number;
    errorMessage: string | null;
    fileName: string;
    progress: number;
    quality: ClipQuality;
    previewUrl: string | null;
    sizeMb: number | null;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    statusUrl: string;
    subtitleColor: SubtitleColor;
    subtitleFontFamily: SubtitleFontFamily;
    subtitleFontSize: SubtitleFontSize;
    subtitlePosition: SubtitlePosition;
    subtitleStatus: SubtitleStatusValue;
    subtitleStyle: SubtitleStyle;
    uuid: string;
}

export interface ClipResponse {
    clip: ClipPayload;
}

export type ClipAnalysisStatus =
    'queued' | 'processing' | 'completed' | 'failed' | 'cancelled';

export interface ClipRecommendation {
    caption: string;
    category: string;
    duration: number;
    emotion: string;
    endSeconds: number;
    hook: string;
    id: string;
    openingText: string;
    reason: string;
    score: number;
    startSeconds: number;
    title: string;
    transcriptExcerpt: string;
}

export interface ClipAnalysisPayload {
    cancelUrl: string;
    errorMessage: string | null;
    progress: number;
    recommendations: ClipRecommendation[];
    sourceUrl: string;
    status: ClipAnalysisStatus;
    statusUrl: string;
    transcriptLanguage: string | null;
    uuid: string;
    video: {
        captions: CaptionAvailability;
        channel: string;
        duration: number;
        id: string;
        thumbnailUrl: string | null;
        title: string;
    };
}

export interface ClipAnalysisResponse {
    analysis: ClipAnalysisPayload;
}

export interface LocalWorkerJobPayload {
    errorMessage: string | null;
    localOutputPath: string | null;
    manifest: LocalWorkerManifest;
    progress: number;
    status: 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled';
    statusUrl: string;
    uuid: string;
}

export interface LocalWorkerJobResponse {
    localWorkerJob: LocalWorkerJobPayload;
}

export interface LocalWorkerManifest {
    callbacks?: {
        method?: string;
        statusUrl?: string;
        token?: string;
    };
    clip?: {
        durationSeconds?: number;
        endSeconds?: number;
        startSeconds?: number;
    };
    disclaimer?: string;
    export?: {
        aspectRatio?: ClipAspectRatio;
        format?: string;
        quality?: ClipQuality;
        subtitleColor?: SubtitleColor;
        subtitleFontFamily?: SubtitleFontFamily;
        subtitleFontSize?: SubtitleFontSize;
        subtitlePosition?: SubtitlePosition;
        subtitleStyle?: string;
        subtitlesEnabled?: boolean;
    };
    jobId?: string;
    output?: {
        defaultFileName?: string;
        syncOutput?: boolean;
    };
    requirements?: {
        credentials?: string;
        ffmpeg?: string;
        ytDlp?: string;
    };
    runner?: string;
    source?: {
        cookiePolicy?: string;
        type?: string;
        url?: string;
        youtubeVideoId?: string | null;
    };
    version?: number;
}
