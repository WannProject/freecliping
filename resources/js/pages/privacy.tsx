import { Head } from '@inertiajs/react';

import { LegalPage } from '@/components/legal-page';
import { terms } from '@/routes';

export default function Privacy() {
    return (
        <LegalPage
            title="Privacy Policy"
            actionHref={terms().url}
            actionLabel="Terms"
        >
            <Head title="Privacy Policy" />
            <section className="space-y-5">
                <p>
                    FreeKliping does not create accounts and does not require a
                    login. We do not use third-party analytics or advertising
                    trackers.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    What we process
                </h2>
                <p>
                    When you paste a YouTube link, FreeKliping fetches video
                    metadata (title, channel, duration, thumbnail) using yt-dlp.
                    For clip recommendations, we may process available
                    transcript or caption text and store the recommendation
                    result temporarily. When you generate a clip, we download
                    the relevant section of the source video, trim it with
                    ffmpeg, and store the result temporarily so you can download
                    it.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    What we store
                </h2>
                <p>
                    Generated clips are stored on our server for a limited time
                    (currently one hour) and then automatically deleted. We do
                    not keep permanent copies of your clips or the source video.
                    Clip analysis metadata, transcript-derived text, requested
                    IP address, and recommendation data are retained temporarily
                    for cleanup and troubleshooting, then pruned automatically.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    IP addresses and rate limiting
                </h2>
                <p>
                    To prevent abuse, we temporarily log your IP address for
                    rate limiting purposes. This data is not used to identify
                    you personally and is not shared with third parties.
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                    YouTube and third parties
                </h2>
                <p>
                    FreeKliping interacts with YouTube to fetch video data. Your
                    use of YouTube is governed by YouTube&apos;s own Terms of
                    Service and Privacy Policy. FreeKliping is not affiliated
                    with YouTube or Google.
                </p>
            </section>
        </LegalPage>
    );
}
