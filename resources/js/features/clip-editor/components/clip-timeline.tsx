import { useMemo } from 'react';

import { cn } from '@/lib/utils';
import {
    timelineFrameCount,
    timelineTickCount,
} from '../clip-editor.constants';
import type { ClipRange } from '../clip-editor.types';
import { clampRange, formatTimecode } from '../clip-editor.utils';
import { Thumbnail } from './thumbnail';

export function ClipTimeline({
    duration,
    hue,
    onRangeChange,
    range,
}: {
    duration: number;
    hue: number;
    onRangeChange: (range: ClipRange) => void;
    range: ClipRange;
}) {
    const frames = useMemo(
        () => Array.from({ length: timelineFrameCount }, (_, index) => index),
        [],
    );
    const ticks = useMemo(
        () =>
            Array.from(
                { length: timelineTickCount },
                (_, index) => (duration / (timelineTickCount - 1)) * index,
            ),
        [duration],
    );
    const startPercent = duration > 0 ? (range.start / duration) * 100 : 0;
    const endPercent = duration > 0 ? (range.end / duration) * 100 : 0;
    const forceHours = duration >= 3600;

    function handleStartChange(value: string) {
        const nextStart = Number(value);
        onRangeChange(
            clampRange(
                {
                    start: Math.min(nextStart, range.end - 1),
                    end: range.end,
                },
                duration,
            ),
        );
    }

    function handleEndChange(value: string) {
        const nextEnd = Number(value);
        onRangeChange(
            clampRange(
                {
                    start: range.start,
                    end: Math.max(nextEnd, range.start + 1),
                },
                duration,
            ),
        );
    }

    return (
        <div className="select-none">
            <div className="relative h-20 w-full overflow-hidden rounded-md border border-border bg-muted">
                <div className="absolute inset-0 flex">
                    {frames.map((frame) => (
                        <Thumbnail
                            key={frame}
                            className="h-full flex-1 border-r border-background/40 last:border-r-0"
                            hue={hue}
                            offset={frame * 11}
                        />
                    ))}
                </div>
                <div
                    className="absolute inset-y-0 left-0 bg-background/75"
                    style={{ width: `${startPercent}%` }}
                />
                <div
                    className="absolute inset-y-0 right-0 bg-background/75"
                    style={{ width: `${100 - endPercent}%` }}
                />
                <div
                    className="absolute inset-y-0 border-y-2 border-brand bg-brand/10 shadow-[0_0_0_1px_rgba(242,169,59,0.16),0_8px_28px_-8px_rgba(242,169,59,0.35)]"
                    style={{
                        left: `${startPercent}%`,
                        width: `${endPercent - startPercent}%`,
                    }}
                />
                <TimelineHandle position={startPercent} side="start" />
                <TimelineHandle position={endPercent} side="end" />
            </div>

            <div className="relative mt-2 h-4">
                {ticks.map((tick, index) => (
                    <span
                        key={index}
                        className={cn(
                            'absolute -translate-x-1/2 font-mono text-[10.5px] text-muted-foreground tabular-nums',
                            index % 2 === 1 && 'hidden sm:block',
                        )}
                        style={{ left: `${(tick / duration) * 100}%` }}
                    >
                        {formatTimecode(tick, forceHours)}
                    </span>
                ))}
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <label className="grid gap-2 text-left">
                    <span className="font-mono text-[11px] tracking-[0.14em] text-muted-foreground uppercase">
                        Start marker
                    </span>
                    <input
                        type="range"
                        min={0}
                        max={Math.max(1, Math.floor(duration - 1))}
                        value={Math.floor(range.start)}
                        onChange={(event) =>
                            handleStartChange(event.target.value)
                        }
                        className="accent-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                        aria-label="Clip start marker"
                    />
                </label>
                <label className="grid gap-2 text-left">
                    <span className="font-mono text-[11px] tracking-[0.14em] text-muted-foreground uppercase">
                        End marker
                    </span>
                    <input
                        type="range"
                        min={1}
                        max={Math.floor(duration)}
                        value={Math.ceil(range.end)}
                        onChange={(event) =>
                            handleEndChange(event.target.value)
                        }
                        className="accent-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                        aria-label="Clip end marker"
                    />
                </label>
            </div>
        </div>
    );
}

function TimelineHandle({
    position,
    side,
}: {
    position: number;
    side: 'start' | 'end';
}) {
    return (
        <div
            className={cn(
                'absolute top-0 z-10 flex h-full w-4 -translate-x-1/2 items-center justify-center',
                side === 'end' && 'translate-x-1/2',
            )}
            style={{ left: `${position}%` }}
        >
            <div className="flex h-full w-2.5 items-center justify-center rounded-sm bg-brand">
                <div className="h-6 w-[3px] rounded-full bg-brand-foreground/70" />
            </div>
        </div>
    );
}
