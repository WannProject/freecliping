import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { ClipRange } from '../clip-editor.types';
import {
    clampRange,
    formatTimecode,
    parseTimecode,
} from '../clip-editor.utils';
import { ManualTimeInput, TimeStat } from './manual-time-input';
import { QuickLengthPresets } from './quick-length-presets';
import { TimecodeField } from './timecode-field';

export function TimeRangeControls({
    clipLength,
    duration,
    isClipTooLong,
    maxClipLength,
    onRangeChange,
    range,
}: {
    clipLength: number;
    duration: number;
    isClipTooLong: boolean;
    maxClipLength: number;
    onRangeChange: (range: ClipRange) => void;
    range: ClipRange;
}) {
    const forceHours = duration >= 3600;
    const [advancedOpen, setAdvancedOpen] = useState(false);

    function updateStart(nextStart: number) {
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

    function updateEnd(nextEnd: number) {
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

    function setClipDuration(seconds: number) {
        const nextEnd = Math.min(duration, range.start + seconds);

        onRangeChange(
            clampRange(
                {
                    start: Math.max(0, nextEnd - seconds),
                    end: nextEnd,
                },
                duration,
            ),
        );
    }

    function handleStartCommit(value: string): boolean {
        const nextStart = parseTimecode(value);

        if (nextStart === null) {
            return false;
        }

        onRangeChange(
            clampRange(
                {
                    start: Math.min(nextStart, range.end - 1),
                    end: range.end,
                },
                duration,
            ),
        );

        return true;
    }

    function handleEndCommit(value: string): boolean {
        const nextEnd = parseTimecode(value);

        if (nextEnd === null) {
            return false;
        }

        onRangeChange(
            clampRange(
                {
                    start: range.start,
                    end: Math.max(nextEnd, range.start + 1),
                },
                duration,
            ),
        );

        return true;
    }

    return (
        <div className="grid gap-3">
            <p className="font-mono text-[13px] text-text-secondary tabular-nums">
                {formatTimecode(range.start, forceHours)}
                <span className="mx-2 text-muted-foreground">&rarr;</span>
                {formatTimecode(range.end, forceHours)}
                <span className="mx-2 text-muted-foreground">·</span>
                <span className={cn(isClipTooLong && 'text-destructive')}>
                    {formatTimecode(clipLength, forceHours)}
                </span>
            </p>

            <div className="grid gap-3 lg:grid-cols-[1fr_1fr_150px]">
                <ManualTimeInput
                    label="Start"
                    maxSeconds={Math.max(0, duration - 1)}
                    onChange={updateStart}
                    value={range.start}
                />
                <ManualTimeInput
                    label="End"
                    maxSeconds={duration}
                    onChange={updateEnd}
                    value={range.end}
                />
                <TimeStat
                    label="Length"
                    value={formatTimecode(clipLength, forceHours)}
                    warning={isClipTooLong}
                />
            </div>

            <div className="flex flex-wrap items-end justify-between gap-3">
                <QuickLengthPresets
                    activeDuration={clipLength}
                    duration={duration}
                    maxClipLength={maxClipLength}
                    onSelect={setClipDuration}
                />

                <Collapsible
                    open={advancedOpen}
                    onOpenChange={setAdvancedOpen}
                    className="grid gap-2"
                >
                    <CollapsibleTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="gap-1.5 font-mono text-[11px] tracking-[0.14em] text-muted-foreground uppercase hover:text-foreground"
                        >
                            <ChevronDown
                                className={cn(
                                    'size-3.5 transition-transform',
                                    advancedOpen && 'rotate-180',
                                )}
                            />
                            Advanced timecode
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <TimecodeField
                                forceHours={forceHours}
                                label="Start timecode"
                                onCommit={handleStartCommit}
                                value={range.start}
                            />
                            <TimecodeField
                                forceHours={forceHours}
                                label="End timecode"
                                onCommit={handleEndCommit}
                                value={range.end}
                            />
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>
        </div>
    );
}
