import { Heart } from 'lucide-react';

import type { SupportTransparency } from './types';

export function SupportCard({
    support,
    supportUrl,
}: {
    support: SupportTransparency;
    supportUrl: string;
}) {
    const progress = Math.max(0, Math.min(100, support.progressPercent));
    const topSupporter = support.supporters[0];

    return (
        <aside className="pointer-events-none fixed top-20 right-3 z-30 sm:right-6 lg:right-8">
            <a
                href={supportUrl}
                target="_blank"
                rel="noreferrer"
                className="pointer-events-auto flex max-w-[210px] items-start gap-2 bg-transparent p-1 text-right drop-shadow-[0_2px_8px_rgba(0,0,0,0.36)] transition-transform hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                aria-label="Kirim dukungan via Saweria"
            >
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f6c945] text-stone-950 shadow-[0_4px_16px_rgba(0,0,0,0.22)]">
                    <Heart className="size-4 fill-current" />
                </span>
                <span className="min-w-0">
                    <span className="block text-[11px] leading-none font-extrabold tracking-normal text-foreground">
                        Kirim dukungan
                    </span>
                    <span className="mt-1 block font-mono text-[10px] leading-none font-semibold text-amber-500">
                        Saweria · {progress}% server
                    </span>
                    <span className="mt-1 block truncate text-[10.5px] leading-tight text-muted-foreground">
                        {topSupporter
                            ? `${topSupporter.name} ${formatMoney(topSupporter.amount, support.currency)}`
                            : 'Bantu server tetap aktif'}
                    </span>
                </span>
            </a>
        </aside>
    );
}

function formatMoney(amount: number, currency: string) {
    try {
        return new Intl.NumberFormat('id-ID', {
            currency,
            maximumFractionDigits: 0,
            notation: amount >= 1000000 ? 'compact' : 'standard',
            style: 'currency',
        }).format(amount);
    } catch {
        return `Rp${Math.round(amount).toLocaleString('id-ID')}`;
    }
}
