import { LoaderCircle, Scissors } from 'lucide-react';

import { Skeleton } from '@/components/ui/skeleton';

export function EmptyState({ loading }: { loading: boolean }) {
    if (loading) {
        return (
            <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
                <Skeleton className="h-20 w-full rounded-md" />
                <div className="flex flex-col gap-3 px-1">
                    <Skeleton className="h-5 w-2/3 rounded-md" />
                    <Skeleton className="h-10 w-full rounded-md" />
                    <Skeleton className="h-10 w-full rounded-md" />
                </div>
                <p className="flex items-center justify-center gap-2 text-[13px] text-muted-foreground">
                    <LoaderCircle className="size-4 animate-spin text-brand" />
                    Reading the video...
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border px-6 py-16 text-center">
            <Scissors className="size-5 text-muted-foreground" />
            <p className="text-[14px] text-text-secondary">
                Paste a link above to start marking your clip.
            </p>
        </div>
    );
}
