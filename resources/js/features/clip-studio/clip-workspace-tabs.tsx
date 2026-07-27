import { SlidersHorizontal, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';

import type { ClipWorkspaceTab } from './types';

export function ClipWorkspaceTabs({
    activeTab,
    onChange,
    recommendationCount,
}: {
    activeTab: ClipWorkspaceTab;
    onChange: (tab: ClipWorkspaceTab) => void;
    recommendationCount: number;
}) {
    const tabs: Array<{
        count?: number;
        icon: ReactNode;
        label: string;
        value: ClipWorkspaceTab;
    }> = [
        {
            count: Math.min(recommendationCount, 6),
            icon: <Sparkles className="size-4" />,
            label: 'Recommended Clips',
            value: 'recommended',
        },
        {
            icon: <SlidersHorizontal className="size-4" />,
            label: 'Manual Clips',
            value: 'manual',
        },
    ];

    return (
        <div
            role="tablist"
            aria-label="Clip workflow"
            className="grid gap-2 rounded-lg border border-border bg-card p-1.5 sm:grid-cols-2"
        >
            {tabs.map((tab) => {
                const selected = activeTab === tab.value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(tab.value)}
                        className={[
                            'flex h-11 items-center justify-center gap-2 rounded-md px-3 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                            selected
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        ].join(' ')}
                    >
                        {tab.icon}
                        <span>{tab.label}</span>
                        {typeof tab.count === 'number' ? (
                            <span
                                className={[
                                    'rounded-md px-1.5 py-0.5 font-mono text-[11px]',
                                    selected
                                        ? 'bg-primary-foreground/18 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ].join(' ')}
                            >
                                {tab.count}/6
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}
