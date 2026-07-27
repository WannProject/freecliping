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
import { cn } from '@/lib/utils';
import {
    aspectRatioOptions,
    qualityOptions,
    subtitleColorOptions,
    subtitleFontFamilyOptions,
    subtitleFontSizeOptions,
    subtitlePositionOptions,
} from '../clip-editor.constants';
import type { SelectOption } from '../clip-editor.constants';
import type {
    CaptionAvailability,
    ExportOptions,
    SubtitleColor,
} from '../clip-editor.types';
import { SubtitleStylePicker } from './subtitle-style-picker';
import { SubtitleToggle } from './subtitle-toggle';

export function ExportOptionsControls({
    captions,
    disabled,
    onChange,
    options,
}: {
    captions: CaptionAvailability;
    disabled: boolean;
    onChange: (options: ExportOptions) => void;
    options: ExportOptions;
}) {
    return (
        <div className="grid gap-3">
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
            <SubtitleToggle
                captions={captions}
                disabled={disabled}
                enabled={options.subtitlesEnabled}
                onChange={(subtitlesEnabled) =>
                    onChange({ ...options, subtitlesEnabled })
                }
            />
            {options.subtitlesEnabled && captions.available ? (
                <div className="grid gap-3">
                    <SubtitleStylePicker
                        disabled={disabled}
                        onChange={(subtitleStyle) =>
                            onChange({ ...options, subtitleStyle })
                        }
                        value={options.subtitleStyle}
                    />
                    <SubtitleTextControls
                        disabled={disabled}
                        onChange={onChange}
                        options={options}
                    />
                </div>
            ) : null}
        </div>
    );
}

function SubtitleTextControls({
    disabled,
    onChange,
    options,
}: {
    disabled: boolean;
    onChange: (options: ExportOptions) => void;
    options: ExportOptions;
}) {
    return (
        <div className="rounded-md border border-border bg-muted p-3">
            <div className="mb-3 flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                Text
            </div>
            <div className="grid gap-3 lg:grid-cols-2">
                <OptionSelector
                    disabled={disabled}
                    icon={null}
                    label="Font"
                    onChange={(subtitleFontFamily) =>
                        onChange({ ...options, subtitleFontFamily })
                    }
                    options={subtitleFontFamilyOptions}
                    value={options.subtitleFontFamily}
                />
                <OptionSelector
                    disabled={disabled}
                    icon={null}
                    label="Size"
                    onChange={(subtitleFontSize) =>
                        onChange({ ...options, subtitleFontSize })
                    }
                    options={subtitleFontSizeOptions}
                    value={options.subtitleFontSize}
                />
                <OptionSelector
                    disabled={disabled}
                    icon={null}
                    label="Position"
                    onChange={(subtitlePosition) =>
                        onChange({ ...options, subtitlePosition })
                    }
                    options={subtitlePositionOptions}
                    value={options.subtitlePosition}
                />
                <SubtitleColorSelector
                    disabled={disabled}
                    onChange={(subtitleColor) =>
                        onChange({ ...options, subtitleColor })
                    }
                    value={options.subtitleColor}
                />
            </div>
        </div>
    );
}

function SubtitleColorSelector({
    disabled,
    onChange,
    value,
}: {
    disabled: boolean;
    onChange: (value: SubtitleColor) => void;
    value: SubtitleColor;
}) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <Label className="mb-3 flex items-center gap-1.5 font-mono text-[10.5px] tracking-[0.14em] text-muted-foreground uppercase">
                Color
            </Label>
            <div className="grid grid-cols-3 gap-2">
                {subtitleColorOptions.map((option) => {
                    const selected = option.value === value;

                    return (
                        <button
                            key={option.value}
                            aria-pressed={selected}
                            className={cn(
                                'flex h-10 items-center justify-center rounded-md border text-[12px] font-semibold transition-colors outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50',
                                selected
                                    ? 'border-brand bg-brand/10 text-brand'
                                    : 'border-border bg-background text-text-secondary hover:bg-secondary hover:text-foreground',
                            )}
                            disabled={disabled}
                            onClick={() => onChange(option.value)}
                            title={option.description}
                            type="button"
                        >
                            <span
                                className={cn(
                                    'mr-2 size-3 rounded-full border border-black/20',
                                    option.value === 'white' && 'bg-white',
                                    option.value === 'yellow' &&
                                        'bg-[#FFE15A]',
                                    option.value === 'cyan' && 'bg-[#5AE1FF]',
                                )}
                            />
                            {option.label}
                        </button>
                    );
                })}
            </div>
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
                <span className="text-[12px] font-medium text-text-secondary">
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
                        className="h-10 border-0 border-l border-border bg-transparent text-[13px] font-medium text-text-secondary first:border-l-0 hover:bg-secondary hover:text-foreground focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none focus-visible:ring-inset data-[state=on]:bg-brand data-[state=on]:text-brand-foreground data-[state=on]:hover:bg-brand data-[state=on]:hover:text-brand-foreground"
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
