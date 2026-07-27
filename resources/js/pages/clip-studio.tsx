import { Head, Link } from '@inertiajs/react';
import { Github, Heart } from 'lucide-react';

import { BrandLogo } from '@/components/brand-logo';
import type {
    ClipRecommendation,
    ExportOptions,
    VideoMeta,
} from '@/features/clip-editor/clip-editor.types';
import { ClipResultCard } from '@/features/clip-editor/components/clip-result-card';
import { EditorPanel } from '@/features/clip-editor/components/editor-panel';
import { EmptyState } from '@/features/clip-editor/components/empty-state';
import { RecommendationGallery } from '@/features/clip-editor/components/recommendation-gallery';
import { UrlInput } from '@/features/clip-editor/components/url-input';
import { VideoPreview } from '@/features/clip-editor/components/video-preview';
import {
    ClipReadyBanner,
    ClipResultModal,
    DownloadProgressModal,
} from '@/features/clip-studio/clip-overlays';
import { ClipWorkspaceTabs } from '@/features/clip-studio/clip-workspace-tabs';
import { LocalWorkerManifestModal } from '@/features/clip-studio/local-worker-manifest-modal';
// import { SupportCard } from '@/features/clip-studio/support-card';
// import { defaultSupportTransparency } from '@/features/clip-studio/types';
import type { SupportTransparency } from '@/features/clip-studio/types';
import { useClipStudioFlow } from '@/features/clip-studio/use-clip-studio-flow';
import { GITHUB_REPOSITORY_URL } from '@/lib/links';

export default function ClipStudio({
    maxClipLength: pageMaxClipLength,
    supportUrl = 'https://saweria.co/freekliping',
    // supportTransparency = defaultSupportTransparency,
}: {
    maxClipLength?: number;
    supportUrl?: string;
    supportTransparency?: SupportTransparency;
}) {
    const flow = useClipStudioFlow(pageMaxClipLength);

    return (
        <>
            <Head title="FreeKliping" />
            <main className="min-h-screen bg-background text-foreground selection:bg-brand selection:text-brand-foreground">
                <ClipStudioHeader supportUrl={supportUrl} />
                <ClipStudioHero
                    error={flow.metadataError}
                    loading={flow.stage === 'loading'}
                    onSubmit={flow.handleLoadVideo}
                    onUrlChange={flow.handleUrlChange}
                    url={flow.url}
                />
                <ClipStudioWorkspace flow={flow} />
                {/* <SupportCard
                    support={supportTransparency}
                    supportUrl={supportUrl}
                /> */}
            </main>
        </>
    );
}

function ClipStudioHeader({ supportUrl }: { supportUrl: string }) {
    return (
        <header className="sticky top-0 z-40 border-b border-border/80 bg-background/82 backdrop-blur-xl">
            <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                <Link
                    href="/"
                    className="flex min-w-0 items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <BrandLogo />
                </Link>

                <div className="flex items-center gap-2">
                    <a
                        href={GITHUB_REPOSITORY_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="hidden h-9 items-center justify-center gap-2 rounded-md bg-secondary px-3 text-sm font-medium text-foreground transition-colors hover:bg-border sm:inline-flex"
                    >
                        <Github className="size-3.5" />
                        GitHub
                    </a>
                    <a
                        href={supportUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="hidden h-9 items-center justify-center gap-2 rounded-md bg-secondary px-3 text-sm font-medium text-foreground transition-colors hover:bg-border sm:inline-flex"
                    >
                        <Heart className="size-3.5 fill-current text-destructive" />
                        Support
                    </a>
                </div>
            </nav>
        </header>
    );
}

function ClipStudioHero({
    error,
    loading,
    onSubmit,
    onUrlChange,
    url,
}: {
    error: string | null;
    loading: boolean;
    onSubmit: () => void;
    onUrlChange: (value: string) => void;
    url: string;
}) {
    return (
        <section className="px-5 pt-16 pb-14 sm:px-8 sm:pt-24 sm:pb-16">
            <div className="mx-auto flex max-w-[760px] flex-col items-center text-center">
                <p className="mb-5 font-mono text-[12px] font-medium tracking-[0.18em] text-muted-foreground uppercase">
                    Paste <span className="text-success">-&gt;</span> Mark{' '}
                    <span className="text-success">-&gt;</span> Clip
                </p>
                <h1 className="max-w-[680px] text-[40px] leading-[1.08] font-semibold tracking-normal text-foreground sm:text-[56px]">
                    Cut the good part out.
                </h1>
                <p className="mt-4 max-w-[500px] text-[16px] leading-relaxed text-text-secondary sm:text-[17px]">
                    Paste a YouTube link, mark the start and end, and export a
                    clean MP4 without an account.
                </p>

                <UrlInput
                    error={error}
                    loading={loading}
                    loadingLabel="Starting analysis"
                    onChange={onUrlChange}
                    onSubmit={onSubmit}
                    submitLabel="Find clips"
                    value={url}
                />

                <p className="mt-4 font-mono text-[11.5px] text-muted-foreground">
                    Free · No login · No watermark · MP4 out
                </p>
            </div>
        </section>
    );
}

function ClipStudioWorkspace({
    flow,
}: {
    flow: ReturnType<typeof useClipStudioFlow>;
}) {
    return (
        <section className="px-5 pb-20 sm:px-8">
            <div className="mx-auto max-w-6xl">
                {flow.stage === 'idle' || flow.stage === 'loading' ? (
                    <EmptyState loading={flow.stage === 'loading'} />
                ) : (
                    <ActiveClipWorkspace flow={flow} />
                )}
            </div>
        </section>
    );
}

function ActiveClipWorkspace({
    flow,
}: {
    flow: ReturnType<typeof useClipStudioFlow>;
}) {
    if (!flow.video) {
        return null;
    }

    return (
        <div className="flex flex-col gap-5">
            <VideoPreview onReset={flow.resetState} video={flow.video} />

            {flow.stage === 'done' && flow.result && !flow.resultModalOpen ? (
                <ClipReadyBanner
                    onOpen={() => flow.setResultModalOpen(true)}
                    result={flow.result}
                />
            ) : null}

            <ClipWorkspaceTabs
                activeTab={flow.activeClipTab}
                onChange={flow.setActiveClipTab}
                recommendationCount={flow.recommendations.length}
            />

            {flow.activeClipTab === 'recommended' ? (
                <RecommendedClipWorkspace
                    analysisCancelling={flow.analysisCancelling}
                    disabled={flow.stage === 'generating'}
                    forceHours={flow.forceHours}
                    loading={flow.stage === 'analyzing'}
                    localWorkerLoadingId={flow.localWorkerLoadingId}
                    onCancel={flow.handleCancelAnalysis}
                    onGenerate={flow.handleGenerateClip}
                    onPrepareLocal={flow.handlePrepareLocalWorkerJob}
                    onSelect={flow.handleSelectRecommendation}
                    progress={flow.analysisProgress}
                    recommendations={flow.recommendations}
                    video={flow.video}
                />
            ) : (
                <ManualClipWorkspace flow={flow} video={flow.video} />
            )}

            <ClipResultModal
                onClose={() => flow.setResultModalOpen(false)}
                open={!!flow.result && flow.resultModalOpen}
            >
                {flow.result ? (
                    <ClipResultCard
                        onRename={flow.handleRenameClip}
                        onReset={flow.resetState}
                        result={flow.result}
                    />
                ) : null}
            </ClipResultModal>

            <DownloadProgressModal
                open={flow.downloadProgressOpen && flow.stage === 'generating'}
                progress={flow.progress}
                queuedSeconds={flow.queuedSeconds}
                status={flow.generationStatus}
            />

            <LocalWorkerManifestModal
                error={flow.localWorkerError}
                manifest={flow.localWorkerManifest}
                onClose={() => flow.setLocalWorkerModalOpen(false)}
                open={flow.localWorkerModalOpen}
            />
        </div>
    );
}

function RecommendedClipWorkspace({
    analysisCancelling,
    disabled,
    forceHours,
    loading,
    localWorkerLoadingId,
    onCancel,
    onGenerate,
    onPrepareLocal,
    onSelect,
    progress,
    recommendations,
    video,
}: {
    analysisCancelling: boolean;
    disabled: boolean;
    forceHours: boolean;
    loading: boolean;
    localWorkerLoadingId: string | null;
    onCancel: () => Promise<void>;
    onGenerate: (
        recommendation?: ClipRecommendation,
        optionOverrides?: Partial<ExportOptions>,
    ) => Promise<void>;
    onPrepareLocal: (
        recommendation: ClipRecommendation,
        optionOverrides: Partial<ExportOptions>,
    ) => Promise<void>;
    onSelect: (recommendation: ClipRecommendation) => void;
    progress: number;
    recommendations: ClipRecommendation[];
    video: VideoMeta;
}) {
    return (
        <RecommendationGallery
            disabled={disabled}
            forceHours={forceHours}
            loading={loading}
            localWorkerLoadingId={localWorkerLoadingId}
            onCancel={analysisCancelling ? undefined : onCancel}
            onGenerate={(recommendation, options) => {
                void onGenerate(recommendation, options);
            }}
            onPrepareLocal={(recommendation, options) => {
                void onPrepareLocal(recommendation, options);
            }}
            onSelect={onSelect}
            progress={progress}
            recommendations={recommendations}
            video={video}
        />
    );
}

function ManualClipWorkspace({
    flow,
    video,
}: {
    flow: ReturnType<typeof useClipStudioFlow>;
    video: VideoMeta;
}) {
    return (
        <EditorPanel
            canGenerate
            clipLength={flow.clipLength}
            generationError={flow.generationError}
            isClipTooLong={flow.isClipTooLong}
            isGenerating={flow.stage === 'generating'}
            maxClipLength={flow.maxClipLength}
            onGenerate={() => {
                void flow.handleGenerateClip();
            }}
            onOptionsChange={flow.setExportOptions}
            onRangeChange={flow.setRange}
            options={flow.exportOptions}
            progress={flow.progress}
            range={flow.range}
            video={video}
        />
    );
}
