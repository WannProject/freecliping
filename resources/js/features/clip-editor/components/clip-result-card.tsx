import {
    AlertCircle,
    Check,
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
        <Card className="gap-0 rounded-lg border-border bg-card p-0">
            <CardHeader className="flex-row items-start gap-3 rounded-t-lg px-5 py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-success/12 text-success">
                    <Check className="size-4" />
                </div>
                <div className="min-w-0 flex-1">
                    <CardTitle className="flex items-center gap-2 text-[15px] text-foreground">
                        Clip ready
                        <FilePenLine className="size-4 text-muted-foreground" />
                    </CardTitle>
                </div>
            </CardHeader>

            <CardContent className="grid gap-4 px-5 py-4">
                <label className="grid max-w-[420px] gap-2 text-left">
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

                <p className="text-[13px] text-text-secondary">
                    {formatTimecode(result.duration)}
                    <span className="mx-1.5 text-muted-foreground">·</span>
                    {aspectRatioLabel(result.aspectRatio)}
                    <span className="mx-1.5 text-muted-foreground">·</span>
                    {qualityLabel(result.quality)}
                    {result.sizeMb ? (
                        <>
                            <span className="mx-1.5 text-muted-foreground">
                                ·
                            </span>
                            {result.sizeMb} MB
                        </>
                    ) : null}
                </p>
            </CardContent>

            <CardFooter className="flex flex-col gap-2 rounded-b-lg border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
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
                    className="h-9 w-full font-semibold sm:w-auto"
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
