import { Copy, Cpu, X } from 'lucide-react';
import { useState } from 'react';

import type { LocalWorkerManifest } from '@/features/clip-editor/clip-editor.types';

export function LocalWorkerManifestModal({
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
