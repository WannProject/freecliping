import { Progress } from '@/components/ui/progress';

export function GenerationProgress({ progress }: { progress: number }) {
    return (
        <div
            className="absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-background/78 p-6 backdrop-blur-sm"
            aria-hidden="true"
        >
            <div
                className="w-full max-w-[360px] rounded-lg border border-border bg-card p-5 shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_-12px_rgba(0,0,0,0.55)]"
                role="status"
                aria-live="polite"
            >
                <div className="mb-3 flex items-center justify-between gap-3">
                    <span className="text-sm font-medium text-foreground">
                        Preparing clip
                    </span>
                    <span className="font-mono text-xs text-text-secondary tabular-nums">
                        {Math.round(progress)}%
                    </span>
                </div>
                <Progress value={progress} className="bg-secondary" />
            </div>
        </div>
    );
}
