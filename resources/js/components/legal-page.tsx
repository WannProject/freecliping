import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export function LegalPage({
    actionHref,
    actionLabel,
    children,
    icon,
    title,
}: {
    actionHref: string;
    actionLabel: string;
    children: ReactNode;
    icon: ReactNode;
    title: string;
}) {
    return (
        <main className="min-h-screen bg-background px-5 py-8 text-foreground sm:px-8">
            <div className="mx-auto max-w-3xl">
                <nav className="mb-10 flex items-center justify-between gap-4">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <span className="flex size-8 items-center justify-center rounded-md bg-brand text-brand-foreground">
                            {icon}
                        </span>
                        <span className="text-[17px] font-semibold tracking-tight">
                            FreeKliping
                        </span>
                    </Link>
                    <Link
                        href={actionHref}
                        className="rounded-md border border-ring px-3 py-2 text-sm text-foreground transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        {actionLabel}
                    </Link>
                </nav>

                <article className="rounded-lg border border-border bg-card p-6 text-[15px] leading-7 text-text-secondary sm:p-8">
                    <p className="mb-3 font-mono text-xs tracking-[0.18em] text-muted-foreground uppercase">
                        FreeKliping
                    </p>
                    <h1 className="mb-6 text-3xl font-semibold tracking-normal text-foreground">
                        {title}
                    </h1>
                    {children}
                </article>
            </div>
        </main>
    );
}
