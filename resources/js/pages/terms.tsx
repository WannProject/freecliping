import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';

import { LegalPage } from '@/components/legal-page';
import { privacy } from '@/routes';

export default function Terms() {
    return (
        <LegalPage
            title="Terms of Service"
            icon={<FileText className="size-4" />}
            actionHref={privacy().url}
            actionLabel="Privacy"
        >
            <Head title="Terms of Service" />
            <section className="space-y-5">
                <p>
                    By using FreeKliping, you agree to these terms. FreeKliping
                    helps you review video moments and create clips from content
                    you are allowed to process. It is not a general-purpose
                    video downloader.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Your responsibility for source content
                </h2>
                <p>
                    You are solely responsible for ensuring that you have the
                    rights and permissions to analyze, render, export, publish,
                    or reuse any video you process. FreeKliping does not grant
                    you any rights to third-party content. If you are unsure
                    whether you may use a video, contact the content owner or
                    consult the platform&apos;s Terms of Service.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Analysis and export
                </h2>
                <p>
                    Pasting a YouTube link may be used to generate clip
                    recommendations before export. Rendering and exporting clips
                    requires your confirmation that you own the content, have a
                    license, or have permission to process it. Do not use
                    FreeKliping to bypass access controls, technical
                    restrictions, or platform rules.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Acceptable use
                </h2>
                <p>
                    You may not use FreeKliping to download copyrighted material
                    without permission, to redistribute content illegally, or to
                    overload or abuse the service. We may restrict access if we
                    detect misuse.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    No warranties
                </h2>
                <p>
                    FreeKliping is provided &quot;as is&quot; without warranties
                    of any kind. We do not guarantee that the service will be
                    available, uninterrupted, or error-free. Generated clips are
                    temporary and may be deleted at any time.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Third-party policies
                </h2>
                <p>
                    FreeKliping is not affiliated with YouTube or Google. Your
                    use of YouTube is subject to YouTube&apos;s Terms of
                    Service. FreeKliping does not bypass YouTube&apos;s
                    restrictions or protections.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Copyright notices
                </h2>
                <p>
                    If you believe content processed through FreeKliping
                    infringes your rights, send a takedown request to{' '}
                    <a
                        href="mailto:takedown@freekliping.app"
                        className="font-medium text-brand underline-offset-4 hover:underline"
                    >
                        takedown@freekliping.app
                    </a>
                    . Include the source URL, the infringing output URL if
                    available, your contact details, and a statement that you
                    are the rights holder or authorized to act for them.
                </p>
            </section>
        </LegalPage>
    );
}
