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
                    is a free tool that helps you cut short clips from YouTube
                    videos without an account.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    Your responsibility for source content
                </h2>
                <p>
                    You are solely responsible for ensuring that you have the
                    rights and permissions to clip and download any video you
                    process. FreeKliping does not grant you any rights to
                    third-party content. If you are unsure whether you may use a
                    video, contact the content owner or consult YouTube&apos;s
                    Terms of Service.
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
            </section>
        </LegalPage>
    );
}
