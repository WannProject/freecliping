import { Link } from '@inertiajs/react';
import { Github } from 'lucide-react';
import type { ReactNode } from 'react';

import { BrandLogo } from '@/components/brand-logo';
import { GITHUB_REPOSITORY_URL } from '@/lib/links';

export function LegalPage({
    actionHref,
    actionLabel,
    children,
    title,
}: {
    actionHref: string;
    actionLabel: string;
    children: ReactNode;
    title: string;
}) {
    return (
        <main className="min-h-screen bg-background px-5 py-8 text-foreground sm:px-8">
            <div className="mx-auto max-w-3xl">
                <nav className="mb-10 flex items-center justify-between gap-4">
                    <Link
                        href="/"
                        className="flex items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <BrandLogo />
                    </Link>
                    <div className="flex items-center gap-2">
                        <a
                            href={GITHUB_REPOSITORY_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-2 rounded-md border border-ring px-3 py-2 text-sm text-foreground transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            <Github className="size-4" />
                            GitHub
                        </a>
                        <Link
                            href={actionHref}
                            className="rounded-md border border-ring px-3 py-2 text-sm text-foreground transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            {actionLabel}
                        </Link>
                    </div>
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
