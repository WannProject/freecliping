import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatTimecode } from '../clip-editor.utils';

export function TimecodeField({
    forceHours,
    label,
    onCommit,
    value,
}: {
    forceHours: boolean;
    label: string;
    onCommit: (value: string) => boolean;
    value: number;
}) {
    const formattedValue = formatTimecode(value, forceHours);

    function resetInput(input: HTMLInputElement) {
        input.value = formattedValue;
        input.setCustomValidity('');
    }

    function handleCommit(input: HTMLInputElement) {
        if (onCommit(input.value)) {
            input.setCustomValidity('');

            return;
        }

        input.setCustomValidity('Use MM:SS or HH:MM:SS.');
        input.reportValidity();
        resetInput(input);
    }

    return (
        <div className="grid gap-2 rounded-md border border-border bg-muted p-3 text-left">
            <Label className="font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                {label}
            </Label>
            <Input
                key={`${label}-${formattedValue}`}
                type="text"
                inputMode="numeric"
                defaultValue={formattedValue}
                onBlur={(event) => handleCommit(event.currentTarget)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        handleCommit(event.currentTarget);
                    }

                    if (event.key === 'Escape') {
                        resetInput(event.currentTarget);
                        event.currentTarget.blur();
                    }
                }}
                aria-label={`${label} timecode`}
                className="h-10 font-mono text-[15px] tabular-nums"
            />
        </div>
    );
}
