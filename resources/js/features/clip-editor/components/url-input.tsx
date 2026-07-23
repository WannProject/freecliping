import {
    AlertCircle,
    ClipboardPaste,
    Link2,
    LoaderCircle,
    Sparkles,
    X,
} from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { useFocusOnMount } from '../use-focus-on-mount';

export function UrlInput({
    error,
    loadingLabel = 'Loading video',
    loading,
    onChange,
    onSubmit,
    submitLabel = 'Load video',
    value,
}: {
    error: string | null;
    loadingLabel?: string;
    loading: boolean;
    onChange: (value: string) => void;
    onSubmit: () => void;
    submitLabel?: string;
    value: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);

    async function handlePaste() {
        try {
            const clipboardText = await navigator.clipboard.readText();

            if (clipboardText) {
                onChange(clipboardText);
            }
        } finally {
            inputRef.current?.focus();
        }
    }

    function handleFormSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        onSubmit();
    }

    return (
        <div className="mt-9 w-full max-w-[600px]">
            <form
                onSubmit={handleFormSubmit}
                className={cn(
                    'flex flex-col items-stretch gap-2.5 rounded-lg border bg-card p-2 shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_-12px_rgba(0,0,0,0.55)] transition-colors sm:flex-row sm:items-center',
                    error
                        ? 'border-destructive/60'
                        : 'border-border focus-within:border-ring',
                )}
            >
                <div className="flex flex-1 items-center gap-2.5 px-3 py-2 sm:py-0">
                    <Link2 className="size-[18px] shrink-0 text-muted-foreground" />
                    <input
                        ref={inputRef}
                        type="url"
                        inputMode="url"
                        autoComplete="off"
                        spellCheck={false}
                        disabled={loading}
                        placeholder="Paste a YouTube link"
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        aria-invalid={!!error}
                        aria-describedby={error ? 'url-error' : undefined}
                        className="w-full min-w-0 bg-transparent font-mono text-[14.5px] text-foreground outline-none placeholder:font-sans placeholder:text-muted-foreground disabled:opacity-50"
                    />
                    {value && !loading ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onChange('')}
                                    aria-label="Clear link"
                                    className="size-7 rounded-full text-muted-foreground hover:bg-secondary hover:text-text-secondary"
                                >
                                    <X className="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Clear link</TooltipContent>
                        </Tooltip>
                    ) : null}
                    {!value ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handlePaste}
                            className="hidden shrink-0 border-border bg-transparent text-xs text-text-secondary hover:border-ring hover:bg-secondary hover:text-foreground sm:inline-flex"
                        >
                            <ClipboardPaste className="size-3.5" />
                            Paste
                        </Button>
                    ) : null}
                </div>

                <Button
                    type="submit"
                    disabled={!value.trim() || loading}
                    className="h-10 w-full gap-2 font-semibold sm:w-auto"
                >
                    {loading ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Sparkles className="size-4" />
                    )}
                    {loading ? loadingLabel : submitLabel}
                </Button>
            </form>

            {error ? <MetadataError message={error} /> : null}
        </div>
    );
}

function MetadataError({ message }: { message: string }) {
    const ref = useFocusOnMount<HTMLParagraphElement>();

    return (
        <p
            ref={ref}
            id="url-error"
            role="alert"
            tabIndex={-1}
            className="mt-2.5 flex items-center gap-1.5 rounded-sm px-1 text-[13px] text-destructive focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
            <AlertCircle className="size-3.5 shrink-0" />
            {message}
        </p>
    );
}
