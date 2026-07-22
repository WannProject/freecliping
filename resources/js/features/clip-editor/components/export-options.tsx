import type { ReactNode } from 'react';

import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { aspectRatioOptions, qualityOptions } from '../clip-editor.constants';
import type { SelectOption } from '../clip-editor.constants';
import type { ExportOptions } from '../clip-editor.types';

export function ExportOptionsControls({
    disabled,
    onChange,
    options,
}: {
    disabled: boolean;
    onChange: (options: ExportOptions) => void;
    options: ExportOptions;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <OptionSelector
                disabled={disabled}
                options={aspectRatioOptions}
                value={options.aspectRatio}
                onChange={(aspectRatio) =>
                    onChange({ ...options, aspectRatio })
                }
                icon={<RatioIcon />}
                label="Ratio"
            />
            <OptionSelector
                disabled={disabled}
                options={qualityOptions}
                value={options.quality}
                onChange={(quality) => onChange({ ...options, quality })}
                icon={<QualityIcon />}
                label="Quality"
            />
        </div>
    );
}

function OptionSelector<TValue extends string>({
    disabled,
    icon,
    label,
    onChange,
    options,
    value,
}: {
    disabled: boolean;
    icon: ReactNode;
    label: string;
    onChange: (value: TValue) => void;
    options: SelectOption<TValue>[];
    value: TValue;
}) {
    const selected = options.find((option) => option.value === value);

    return (
        <div className="rounded-md border border-border bg-muted p-3">
            <div className="mb-3 flex items-center justify-between gap-3">
                <Label className="flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                    {icon}
                    {label}
                </Label>
                <span className="text-[12px] text-muted-foreground">
                    {selected?.description}
                </span>
            </div>
            <ToggleGroup
                type="single"
                disabled={disabled}
                value={value}
                onValueChange={(nextValue) => {
                    if (nextValue) {
                        onChange(nextValue as TValue);
                    }
                }}
                variant="outline"
                className="hidden w-full items-stretch overflow-hidden rounded-md border border-border bg-card sm:grid sm:grid-cols-4"
            >
                {options.map((option) => (
                    <ToggleGroupItem
                        key={option.value}
                        value={option.value}
                        aria-label={option.label}
                        className="h-10 border-0 border-l border-border bg-transparent text-[12px] text-text-secondary first:border-l-0 hover:bg-secondary hover:text-foreground data-[state=on]:bg-brand data-[state=on]:text-brand-foreground"
                    >
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <Select
                disabled={disabled}
                value={value}
                onValueChange={(nextValue) => onChange(nextValue as TValue)}
            >
                <SelectTrigger className="h-10 w-full sm:hidden">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function RatioIcon() {
    return (
        <svg
            className="size-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <rect
                width="12"
                height="20"
                x="6"
                y="2"
                rx="2"
                transform="rotate(0 12 12)"
            />
        </svg>
    );
}

function QualityIcon() {
    return (
        <svg
            className="size-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
        </svg>
    );
}
