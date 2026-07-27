import { CheckCircle2, Download, LoaderCircle, X } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';

import { Progress } from '@/components/ui/progress';
import type {
    ClipPayload,
    ClipResult,
} from '@/features/clip-editor/clip-editor.types';
import { formatTimecode } from '@/features/clip-editor/clip-editor.utils';

export function ClipReadyBanner({
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

export function DownloadProgressModal({
    open,
    progress,
    queuedSeconds,
    status,
}: {
    open: boolean;
    progress: number;
    queuedSeconds: number;
    status: ClipPayload['status'];
}) {
    if (!open) {
        return null;
    }

    const isQueued = status === 'queued';
    const isStaleQueue = isQueued && queuedSeconds >= 45;
    const title = isQueued ? 'Menunggu antrian render' : 'Preparing download';
    const description = isQueued
        ? 'Clip sudah masuk antrian. Render akan mulai setelah worker server mengambil job ini.'
        : 'Rendering your clip now. The download will start automatically when the file is ready.';
    const progressLabel = isQueued ? 'Queued' : 'Rendering';

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-50 grid place-items-center bg-background/72 px-4 py-6 backdrop-blur-md"
        >
            <div className="w-full max-w-[460px] rounded-lg border border-border bg-card p-5 shadow-[0_24px_80px_-44px_rgba(0,0,0,0.95)]">
                <div className="flex items-start gap-4">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-md bg-brand/12 text-brand ring-1 ring-brand/20">
                        <LoaderCircle className="size-5 animate-spin" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-[17px] font-semibold text-foreground">
                            {title}
                        </p>
                        <p className="mt-1 text-[13px] leading-relaxed text-text-secondary">
                            {description}
                        </p>
                        {isStaleQueue ? (
                            <p className="mt-3 rounded-md border border-amber-300/45 bg-amber-50/80 px-3 py-2 text-[12.5px] leading-relaxed text-stone-800">
                                Render belum mulai setelah {queuedSeconds}{' '}
                                detik. Jika ini berjalan di local, pastikan
                                queue worker aktif lewat{' '}
                                <span className="font-mono">
                                    composer run dev
                                </span>{' '}
                                atau{' '}
                                <span className="font-mono">
                                    php artisan queue:work
                                </span>
                                .
                            </p>
                        ) : null}
                    </div>
                </div>

                <div className="mt-5 grid gap-2">
                    <div className="flex items-center justify-between font-mono text-[11px] text-muted-foreground">
                        <span>{progressLabel}</span>
                        <span>{Math.round(progress)}%</span>
                    </div>
                    <Progress value={progress} className="h-2" />
                    {isQueued ? (
                        <p className="text-[11.5px] text-muted-foreground">
                            Waktu antre {queuedSeconds} detik
                        </p>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export function ClipResultModal({
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
