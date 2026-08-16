import { Type } from 'lucide-react';

import { cn } from '@/lib/utils';
import { subtitleStyleOptions } from '../clip-editor.constants';
import type { SubtitleStyle } from '../clip-editor.types';

const SAMPLE = ['this', 'is', 'wild'];

export function SubtitleStylePicker({
    disabled,
    onChange,
    value,
}: {
    disabled: boolean;
    onChange: (style: SubtitleStyle) => void;
    value: SubtitleStyle;
}) {
    return (
        <div
            role="radiogroup"
            aria-label="Subtitle style"
            className="rounded-md border border-border bg-muted p-3"
        >
            <div className="mb-3 flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                <Type className="size-3.5" />
                Style
            </div>
            <div className="grid grid-cols-3 gap-2">
                {subtitleStyleOptions.map((option) => {
                    const selected = option.value === value;

                    return (
                        <button
                            type="button"
                            key={option.value}
                            role="radio"
                            aria-checked={selected}
                            disabled={disabled}
                            onClick={() => onChange(option.value)}
                            title={option.description}
                            className={cn(
                                'group flex flex-col gap-2 overflow-hidden rounded-md border p-1.5 text-left transition-colors outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50',
                                selected
                                    ? 'border-brand bg-brand/10'
                                    : 'border-border bg-card hover:bg-secondary',
                            )}
                        >
                            <SubtitlePreview style={option.value} />
                            <div className="min-w-0">
                                <p
                                    className={cn(
                                        'truncate text-[12px] font-semibold',
                                        selected
                                            ? 'text-brand'
                                            : 'text-text-secondary',
                                    )}
                                >
                                    {option.label}
                                </p>
                                <p className="truncate text-[10px] text-muted-foreground">
                                    {option.description}
                                </p>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function SubtitlePreview({ style }: { style: SubtitleStyle }) {
    return (
        <div className="relative aspect-video w-full overflow-hidden rounded bg-gradient-to-br from-zinc-600 to-zinc-900">
            <div className="absolute inset-x-0 bottom-0 flex justify-center p-1.5">
                <SampleLine style={style} />
            </div>
        </div>
    );
}

function SampleLine({ style }: { style: SubtitleStyle }) {
    if (style === 'classic') {
        return (
            <span className="text-[10px] font-extrabold tracking-tight text-white [text-shadow:0_0_2px_#000,0_1px_3px_#000]">
                {SAMPLE.join(' ')}
            </span>
        );
    }

    if (style === 'neon-box') {
        return (
            <span className="rounded-[2px] bg-black/85 px-1 py-0.5 text-[9px] font-extrabold tracking-tight text-zinc-200 [box-shadow:0_0_6px_rgba(0,0,0,0.6)]">
                {SAMPLE.map((word, index) => (
                    <span
                        key={word}
                        className={index === 1 ? 'text-[#00E5FF]' : undefined}
                    >
                        {word}{' '}
                    </span>
                ))}
            </span>
        );
    }

    return (
        <span className="text-[9px] font-extrabold tracking-tight text-white [text-shadow:0_0_2px_#000,0_1px_2px_#000]">
            {SAMPLE.map((word, index) => (
                <span
                    key={word}
                    className={index === 1 ? 'text-[#FFE15A]' : undefined}
                >
                    {word}{' '}
                </span>
            ))}
        </span>
    );
}
