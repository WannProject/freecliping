import {
    AlertCircle,
    Captions,
    Check,
    Clapperboard,
    Download,
    FilePenLine,
    LoaderCircle,
    Save,
} from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { aspectRatioLabel, qualityLabel } from '../clip-editor.constants';
import type { ClipResult } from '../clip-editor.types';
import { fileBaseName, formatTimecode } from '../clip-editor.utils';
import { useFocusOnMount } from '../use-focus-on-mount';

export function ClipResultCard({
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
    const headingRef = useFocusOnMount<HTMLDivElement>();
    const cleanDraft = draftFileName.trim();
    const currentBaseName = fileBaseName(result.fileName);
    const canSave =
        cleanDraft.length > 0 && cleanDraft !== currentBaseName && !saving;
    const subtitleLabel = subtitleStatusLabel(result.subtitleStatus);

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
        <Card className="gap-0 overflow-hidden rounded-lg border-border bg-card p-0 shadow-[0_24px_80px_-44px_rgba(0,0,0,0.95)]">
            <CardHeader className="flex-row items-start gap-4 border-b border-border bg-surface-2 px-5 py-5">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-md bg-success/12 text-success ring-1 ring-success/20">
                    <Check className="size-5" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle
                        ref={headingRef}
                        tabIndex={-1}
                        className="flex items-center gap-2 rounded-sm text-[18px] text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    >
                        Clip ready
                        <FilePenLine className="size-4 text-muted-foreground" />
                    </CardTitle>
                    <p className="text-[13px] leading-relaxed text-text-secondary">
                        Your clip has been rendered and is ready to download.
                    </p>
                </div>
            </CardHeader>

            <CardContent className="grid gap-5 px-5 py-5">
                {result.previewUrl ? (
                    <video
                        className="aspect-video w-full rounded-md border border-border bg-black"
                        controls
                        preload="metadata"
                        src={result.previewUrl}
                    />
                ) : null}

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <ResultMetric
                        label="Duration"
                        value={formatTimecode(result.duration)}
                    />
                    <ResultMetric
                        label="Aspect"
                        value={aspectRatioLabel(result.aspectRatio)}
                    />
                    <ResultMetric
                        label="Quality"
                        value={qualityLabel(result.quality)}
                    />
                    <ResultMetric
                        label="Size"
                        value={result.sizeMb ? `${result.sizeMb} MB` : 'Ready'}
                    />
                </div>

                {subtitleLabel ? (
                    <div className="flex items-center gap-2 rounded-md border border-border bg-muted px-3 py-2 text-[13px] text-text-secondary">
                        <Captions className="size-4 text-muted-foreground" />
                        {subtitleLabel}
                    </div>
                ) : null}

                <label className="grid gap-2 text-left">
                    <span className="font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                        File name
                    </span>
                    <div
                        className={cn(
                            'flex h-10 items-center rounded-md border bg-muted transition-colors',
                            renameError
                                ? 'border-destructive/60'
                                : 'border-border focus-within:border-ring',
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
                            className="min-w-0 flex-1 bg-transparent px-3 text-[14px] text-foreground outline-none placeholder:text-muted-foreground"
                            aria-invalid={!!renameError}
                            aria-describedby={
                                renameError ? 'file-name-error' : undefined
                            }
                        />
                        <span className="border-l border-border px-3 font-mono text-xs text-muted-foreground">
                            .mp4
                        </span>
                    </div>
                </label>

                {renameError ? (
                    <Alert variant="destructive" id="file-name-error">
                        <AlertCircle className="size-4" />
                        <AlertDescription>{renameError}</AlertDescription>
                    </Alert>
                ) : null}
            </CardContent>

            <CardFooter className="flex flex-col gap-2 border-t border-border bg-surface-2 px-5 py-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void handleSaveFileName()}
                    disabled={!canSave}
                    className="h-9 w-full sm:w-auto"
                >
                    {saving ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Save className="size-4" />
                    )}
                    Save
                </Button>
                <Button
                    type="button"
                    onClick={handleDownload}
                    className="h-10 w-full font-semibold sm:w-auto"
                >
                    <Download className="size-4" />
                    Download
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onReset}
                    className="h-9 w-full sm:w-auto"
                >
                    New clip
                </Button>
            </CardFooter>
        </Card>
    );
}

function ResultMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1 rounded-md border border-border bg-muted px-3 py-2">
            <span className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                <Clapperboard className="size-3" />
                {label}
            </span>
            <span className="truncate text-[13px] font-semibold text-foreground">
                {value}
            </span>
        </div>
    );
}

function subtitleStatusLabel(
    status: ClipResult['subtitleStatus'],
): string | null {
    if (status === 'burned') {
        return 'Subtitles burned';
    }

    if (status === 'unavailable') {
        return 'Subtitles unavailable';
    }

    if (status === 'failed') {
        return 'Subtitles skipped';
    }

    return null;
}
