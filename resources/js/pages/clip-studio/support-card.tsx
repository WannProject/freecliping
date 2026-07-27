import { Link } from '@inertiajs/react';
import { Heart, Menu, Minus } from 'lucide-react';
import { useState } from 'react';

import { Progress } from '@/components/ui/progress';
import { GITHUB_REPOSITORY_URL } from '@/lib/links';
import { privacy, terms } from '@/routes';

import type { SupportTransparency } from './types';

export function SupportCard({
    support,
    supportUrl,
}: {
    support: SupportTransparency;
    supportUrl: string;
}) {
    const progress = Math.max(0, Math.min(100, support.progressPercent));
    const [minimized, setMinimized] = useState(false);

    return (
        <aside className="fixed inset-x-4 bottom-4 z-30 sm:inset-x-auto sm:right-5 sm:bottom-5 sm:w-[360px] lg:right-8">
            {minimized ? (
                <button
                    type="button"
                    onClick={() => setMinimized(false)}
                    className="ml-auto flex h-12 max-w-full items-center gap-2 rounded-lg border border-amber-300/55 bg-background/62 px-3 text-sm font-semibold text-foreground shadow-xl shadow-black/12 backdrop-blur-xl transition-colors hover:bg-background/82 focus-visible:ring-2 focus-visible:ring-brand"
                    aria-label="Buka card dukungan"
                >
                    <Menu className="size-4 text-amber-500" />
                    <span className="truncate">Bantu server</span>
                    <span className="rounded-md bg-amber-300/70 px-2 py-0.5 font-mono text-xs text-stone-950">
                        {progress}%
                    </span>
                </button>
            ) : (
                <div className="max-h-[72dvh] overflow-y-auto rounded-lg border border-amber-300/45 bg-background/58 shadow-2xl shadow-black/18 backdrop-blur-xl">
                    <div className="border-b border-amber-200/45 bg-amber-50/62 px-4 py-4 text-stone-950 backdrop-blur-xl">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="flex items-center gap-2 text-sm font-semibold">
                                    <Heart className="size-4 fill-current text-amber-500" />
                                    FreeKliping gratis
                                </p>
                                <p className="mt-1 text-xs leading-5 text-stone-700">
                                    {support.caption}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <div className="rounded-md bg-white/72 px-2.5 py-1 text-sm font-bold text-stone-950 shadow-xs">
                                    {progress}%
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setMinimized(true)}
                                    className="flex size-8 items-center justify-center rounded-md bg-white/64 text-stone-700 transition-colors hover:bg-white/88 hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-brand"
                                    aria-label="Minimize card dukungan"
                                >
                                    <Minus className="size-4" />
                                </button>
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="mb-1 flex items-center justify-between text-[11px] font-semibold tracking-normal text-stone-700 uppercase">
                                <span>Progress sewa server bulan ini</span>
                                <span>{progress}%</span>
                            </div>
                            <Progress
                                value={progress}
                                className="h-2 bg-white"
                            />
                        </div>
                    </div>

                    <div className="space-y-4 px-4 py-4">
                        <a
                            href={supportUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex h-10 w-full items-center justify-center rounded-md bg-[#f6c945]/88 px-4 text-sm font-bold text-stone-950 transition-colors hover:bg-[#f6c945] focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Kirim Dukungan via Saweria
                        </a>

                        <div>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        Top dukungan bulan ini
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Dari terbanyak ke terendah.
                                    </p>
                                </div>
                            </div>

                            {support.supporters.length > 0 ? (
                                <ol className="mt-3 space-y-2">
                                    {support.supporters.map(
                                        (supporter, index) => (
                                            <li
                                                key={`${supporter.name}-${index}`}
                                                className="flex items-center justify-between gap-3 rounded-md bg-background/62 px-3 py-2 backdrop-blur"
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-100/86 font-mono text-xs font-semibold text-stone-800">
                                                        {index + 1}
                                                    </span>
                                                    <span className="truncate text-sm font-medium text-foreground">
                                                        {supporter.name}
                                                    </span>
                                                </div>
                                                <span className="shrink-0 text-sm font-semibold text-foreground">
                                                    {formatMoney(
                                                        supporter.amount,
                                                        support.currency,
                                                    )}
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ol>
                            ) : (
                                <p className="mt-3 rounded-md bg-background/62 px-3 py-3 text-sm text-muted-foreground backdrop-blur">
                                    Belum ada supporter bulan ini.
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between border-t border-border/70 pt-3 font-mono text-[11px] text-muted-foreground">
                            <div className="flex gap-3">
                                <Link
                                    href={privacy()}
                                    className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                                >
                                    Privacy
                                </Link>
                                <Link
                                    href={terms()}
                                    className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                                >
                                    Terms
                                </Link>
                            </div>
                            <a
                                href={GITHUB_REPOSITORY_URL}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                GitHub
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </aside>
    );
}

function formatMoney(amount: number, currency: string) {
    try {
        return new Intl.NumberFormat('id-ID', {
            currency,
            maximumFractionDigits: 0,
            style: 'currency',
        }).format(amount);
    } catch {
        return `Rp${Math.round(amount).toLocaleString('id-ID')}`;
    }
}
