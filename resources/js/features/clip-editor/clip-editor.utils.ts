import type { ClipRange, ErrorResponse } from './clip-editor.types';

export function formatTimecode(
    totalSeconds: number,
    forceHours = false,
): string {
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

export function parseTimecode(value: string): number | null {
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

export function clampRange(range: ClipRange, duration: number): ClipRange {
    const start = Math.max(0, Math.min(range.start, duration - 1));
    const end = Math.max(start + 1, Math.min(range.end, duration));

    return { start, end };
}

export function secondsToMinuteParts(totalSeconds: number): {
    minutes: number;
    seconds: number;
} {
    const seconds = Math.max(0, Math.floor(totalSeconds));

    return {
        minutes: Math.floor(seconds / 60),
        seconds: seconds % 60,
    };
}

export function hashString(value: string): number {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = (hash << 5) - hash + value.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
}

export function isLikelyYoutubeUrl(value: string): boolean {
    return /^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|m\.youtube\.com\/watch\?v=)[\w-]+/i.test(
        value.trim(),
    );
}

export function fileBaseName(fileName: string): string {
    return fileName.replace(/\.mp4$/i, '');
}

export function jsonHeaders(): HeadersInit {
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

export async function errorMessage(
    response: Response,
    fallback: string,
): Promise<string> {
    const payload = (await response
        .json()
        .catch(() => null)) as ErrorResponse | null;

    if (payload?.errors) {
        const firstError = Object.values(payload.errors)[0]?.[0];

        if (firstError) {
            return firstError;
        }
    }

    return payload?.message || fallback;
}
