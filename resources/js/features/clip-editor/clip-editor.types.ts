export type FlowStage = 'idle' | 'loading' | 'ready' | 'generating' | 'done';

export interface ClipRange {
    start: number;
    end: number;
}

export type ClipAspectRatio = 'original' | '16:9' | '9:16' | '1:1';
export type ClipQuality = 'source' | '480p' | '720p' | '1080p';

export interface ExportOptions {
    aspectRatio: ClipAspectRatio;
    quality: ClipQuality;
}

export interface VideoMeta {
    channel: string;
    duration: number;
    hue: number;
    id: string;
    thumbnailUrl: string | null;
    title: string;
}

export interface ClipResult {
    aspectRatio: ClipAspectRatio;
    downloadUrl: string;
    duration: number;
    fileName: string;
    quality: ClipQuality;
    sizeMb: number | null;
    uuid: string;
}

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
    };
}

export interface ErrorResponse {
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
    sizeMb: number | null;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    statusUrl: string;
    uuid: string;
}

export interface ClipResponse {
    clip: ClipPayload;
}
