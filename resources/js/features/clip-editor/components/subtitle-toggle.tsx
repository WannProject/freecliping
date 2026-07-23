import { Captions } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { CaptionAvailability } from '../clip-editor.types';

export function SubtitleToggle({
    captions,
    disabled,
    enabled,
    onChange,
}: {
    captions: CaptionAvailability;
    disabled: boolean;
    enabled: boolean;
    onChange: (enabled: boolean) => void;
}) {
    const available = captions.available;
    const isDisabled = disabled || !available;
    const label = available
        ? `${captions.kind === 'manual' ? 'Manual' : 'Auto'}${captions.language ? ` · ${captions.language}` : ''}`
        : 'Unavailable';

    return (
        <div className="rounded-md border border-border bg-muted p-3">
            <button
                type="button"
                role="switch"
                aria-checked={enabled}
                disabled={isDisabled}
                onClick={() => onChange(!enabled)}
                className="flex w-full items-center justify-between gap-3 rounded-sm text-left outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                    <Captions className="size-3.5" />
                    Subtitles
                </span>
                <span className="flex items-center gap-2.5">
                    <span className="text-[12px] font-medium text-text-secondary">
                        {label}
                    </span>
                    <span
                        aria-hidden="true"
                        className={cn(
                            'relative h-5 w-9 shrink-0 rounded-full transition-colors',
                            enabled && available ? 'bg-brand' : 'bg-border',
                        )}
                    >
                        <span
                            className={cn(
                                'absolute top-0.5 left-0.5 size-4 rounded-full bg-background shadow transition-transform',
                                enabled && available && 'translate-x-4',
                            )}
                        />
                    </span>
                </span>
            </button>
        </div>
    );
}
