import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface QuickPreset {
    label: string;
    seconds: number;
}

function buildPresets(duration: number, maxClipLength: number): QuickPreset[] {
    const baseDurations = [15, 30, 60, Math.min(maxClipLength, 180)];
    const unique = baseDurations.filter(
        (value, index, values) =>
            value <= duration && values.indexOf(value) === index,
    );

    return unique.map((seconds) => ({
        label: presetLabel(seconds),
        seconds,
    }));
}

function presetLabel(seconds: number): string {
    if (seconds >= 60 && seconds % 60 === 0) {
        return `${seconds / 60}m`;
    }

    return `${seconds}s`;
}

export function QuickLengthPresets({
    activeDuration,
    duration,
    maxClipLength,
    onSelect,
}: {
    activeDuration: number;
    duration: number;
    maxClipLength: number;
    onSelect: (seconds: number) => void;
}) {
    const presets = buildPresets(duration, maxClipLength);
    const activeSeconds = Math.round(activeDuration);

    return (
        <div className="grid gap-2">
            <Label className="font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                Quick length
            </Label>
            <div className="flex flex-wrap gap-2">
                {presets.map((preset) => {
                    const isActive = preset.seconds === activeSeconds;

                    return (
                        <Button
                            key={preset.seconds}
                            type="button"
                            variant="outline"
                            size="sm"
                            aria-pressed={isActive}
                            onClick={() => onSelect(preset.seconds)}
                            className={cn(
                                'h-9 min-w-[3rem] font-mono text-[12px] text-text-secondary',
                                isActive &&
                                    'border-brand bg-brand/10 text-brand hover:bg-brand/15 hover:text-brand',
                            )}
                        >
                            {preset.label}
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}
