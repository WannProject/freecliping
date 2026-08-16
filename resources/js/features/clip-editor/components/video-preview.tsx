import { RotateCcw, User2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { VideoMeta } from '../clip-editor.types';
import { formatTimecode } from '../clip-editor.utils';

export function VideoPreview({
    onReset,
    video,
}: {
    onReset: () => void;
    video: VideoMeta;
}) {
    return (
        <div className="flex flex-col gap-5 overflow-hidden rounded-lg border border-border bg-card p-3 sm:flex-row sm:p-4">
            <iframe
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullScreen
                className="aspect-video w-full shrink-0 rounded-md border border-border bg-muted sm:w-72"
                src={`https://www.youtube.com/embed/${video.id}`}
                title={video.title}
            />

            <div className="flex min-w-0 flex-1 flex-col justify-center gap-2 py-1">
                <h2
                    className="truncate text-[15.5px] leading-snug font-semibold text-foreground"
                    title={video.title}
                >
                    {video.title}
                </h2>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-text-secondary">
                    <span className="flex items-center gap-1.5">
                        <User2 className="size-3.5 text-muted-foreground" />
                        {video.channel}
                    </span>
                    <span className="font-mono text-muted-foreground tabular-nums">
                        {formatTimecode(video.duration, video.duration >= 3600)}{' '}
                        runtime
                    </span>
                </div>
            </div>

            <div className="flex items-start justify-end sm:items-center">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onReset}
                    className="h-8 shrink-0 text-text-secondary hover:bg-secondary hover:text-foreground"
                >
                    <RotateCcw className="size-3.5" />
                    Change video
                </Button>
            </div>
        </div>
    );
}
