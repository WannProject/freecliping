import { Clock3, Timer } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { formatTimecode, secondsToMinuteParts } from '../clip-editor.utils';

export function ManualTimeInput({
    label,
    maxSeconds,
    onChange,
    value,
}: {
    label: string;
    maxSeconds: number;
    onChange: (seconds: number) => void;
    value: number;
}) {
    const parts = secondsToMinuteParts(value);
    const maxParts = secondsToMinuteParts(maxSeconds);

    function updatePart(part: 'minutes' | 'seconds', nextValue: string) {
        const parsedValue = Number(nextValue);
        const safeValue = Number.isFinite(parsedValue) ? parsedValue : 0;
        const nextParts = {
            ...parts,
            [part]:
                part === 'seconds'
                    ? Math.max(0, Math.min(59, safeValue))
                    : Math.max(0, safeValue),
        };

        onChange(
            Math.min(
                maxSeconds,
                Math.floor(nextParts.minutes) * 60 +
                    Math.floor(nextParts.seconds),
            ),
        );
    }

    return (
        <div className="rounded-md border border-border bg-muted p-3">
            <div className="mb-3 flex items-center justify-between gap-2">
                <Label className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                    <Clock3 className="size-3.5" />
                    {label}
                </Label>
                <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                    {formatTimecode(value, maxSeconds >= 3600)}
                </span>
            </div>
            <div className="grid grid-cols-[1fr_1fr] gap-2">
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${label}-minutes`}
                        className="text-[12px] text-text-secondary"
                    >
                        Minutes
                    </Label>
                    <Input
                        id={`${label}-minutes`}
                        type="number"
                        min={0}
                        max={maxParts.minutes}
                        inputMode="numeric"
                        value={parts.minutes}
                        onChange={(event) =>
                            updatePart('minutes', event.target.value)
                        }
                        className="h-10 font-mono tabular-nums"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`${label}-seconds`}
                        className="text-[12px] text-text-secondary"
                    >
                        Seconds
                    </Label>
                    <Input
                        id={`${label}-seconds`}
                        type="number"
                        min={0}
                        max={59}
                        inputMode="numeric"
                        value={parts.seconds}
                        onChange={(event) =>
                            updatePart('seconds', event.target.value)
                        }
                        className="h-10 font-mono tabular-nums"
                    />
                </div>
            </div>
        </div>
    );
}

export function TimeStat({
    label,
    value,
    warning = false,
}: {
    label: string;
    value: string;
    warning?: boolean;
}) {
    return (
        <div className="rounded-md border border-border bg-muted p-3">
            <p className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                <Timer className="size-3.5" />
                {label}
            </p>
            <p
                className={cn(
                    'mt-2 font-mono text-[18px] font-semibold tabular-nums',
                    warning ? 'text-destructive' : 'text-foreground',
                )}
            >
                {value}
            </p>
        </div>
    );
}
