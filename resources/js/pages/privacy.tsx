import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

import { home, terms } from '@/routes';

export default function Privacy() {
    return (
        <LegalPage title="Privacy Policy">
            <Head title="Privacy Policy" />
            <section className="space-y-5">
                <p>
                    FreeKliping MVP is a frontend-only prototype. It does not
                    create accounts, does not run analytics, and does not send
                    your pasted YouTube link to a FreeKliping backend.
                </p>
                <p>
                    The current clip generation flow uses mock video metadata
                    and a mock download file generated in your browser.
                    Clipboard access is only used when you press the Paste
                    button.
                </p>
                <p>
                    If a production backend is added later, this policy should
                    be reviewed before any metadata fetching, video processing,
                    storage, logging, rate limiting, or abuse prevention is
                    enabled.
                </p>
            </section>
        </LegalPage>
    );
}

function LegalPage({
    children,
    title,
}: {
    children: React.ReactNode;
    title: string;
}) {
    return (
        <main className="min-h-screen bg-[#0b0d10] px-5 py-8 text-[#f3f4f1] sm:px-8">
            <div className="mx-auto max-w-3xl">
                <nav className="mb-10 flex items-center justify-between gap-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                    >
                        <span className="flex size-8 items-center justify-center rounded-md bg-[#f2a93b] text-[#1a1204]">
                            <ShieldCheck className="size-4" />
                        </span>
                        <span className="text-[17px] font-semibold tracking-tight">
                            FreeKliping
                        </span>
                    </Link>
                    <Link
                        href={terms()}
                        className="rounded-md border border-[#323942] px-3 py-2 text-sm text-[#f3f4f1] transition-colors hover:bg-[#1a1f25] focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                    >
                        Terms
                    </Link>
                </nav>

                <article className="rounded-lg border border-[#23282e] bg-[#14181d] p-6 text-[15px] leading-7 text-[#8b9198] sm:p-8">
                    <p className="mb-3 font-mono text-xs tracking-[0.18em] text-[#5a6067] uppercase">
                        FreeKliping
                    </p>
                    <h1 className="mb-6 text-3xl font-semibold tracking-normal text-[#f3f4f1]">
                        {title}
                    </h1>
                    {children}
                </article>
            </div>
        </main>
    );
}
