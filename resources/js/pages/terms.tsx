import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';

import { home, privacy } from '@/routes';

export default function Terms() {
    return (
        <LegalPage title="Terms of Service">
            <Head title="Terms of Service" />
            <section className="space-y-5">
                <p>
                    FreeKliping MVP is provided as an interaction prototype. It
                    does not download, host, or encode YouTube videos in this
                    phase.
                </p>
                <p>
                    You are responsible for making sure you have the rights and
                    permissions needed for any source video you use. FreeKliping
                    does not grant rights to third-party content.
                </p>
                <p>
                    A future production backend may need stricter usage limits,
                    abuse prevention, and legal review before real video
                    processing is enabled.
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
                            <FileText className="size-4" />
                        </span>
                        <span className="text-[17px] font-semibold tracking-tight">
                            FreeKliping
                        </span>
                    </Link>
                    <Link
                        href={privacy()}
                        className="rounded-md border border-[#323942] px-3 py-2 text-sm text-[#f3f4f1] transition-colors hover:bg-[#1a1f25] focus-visible:ring-2 focus-visible:ring-[#f2a93b]"
                    >
                        Privacy
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
