import { Play } from 'lucide-react';

import { cn } from '@/lib/utils';

export function Thumbnail({
    className,
    hue,
    offset = 0,
    showPlay = false,
    thumbnailUrl = null,
}: {
    className?: string;
    hue: number;
    offset?: number;
    showPlay?: boolean;
    thumbnailUrl?: string | null;
}) {
    const firstHue = (hue + offset) % 360;
    const secondHue = (hue + offset + 44) % 360;

    return (
        <div
            className={cn('relative overflow-hidden bg-muted', className)}
            style={
                thumbnailUrl
                    ? undefined
                    : {
                          backgroundImage: `linear-gradient(135deg, hsl(${firstHue} 55% 22%), hsl(${secondHue} 60% 12%) 60%, var(--background) 100%)`,
                      }
            }
        >
            {thumbnailUrl ? (
                <img
                    src={thumbnailUrl}
                    alt=""
                    className="absolute inset-0 h-full w-full object-cover"
                    loading="lazy"
                />
            ) : (
                <div
                    className="absolute inset-0 opacity-40 mix-blend-screen"
                    style={{
                        backgroundImage: `radial-gradient(circle at 30% 30%, hsl(${firstHue} 70% 45% / 0.5), transparent 55%)`,
                    }}
                />
            )}
            {showPlay ? (
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-background/50 ring-1 ring-white/10 backdrop-blur-sm">
                        <Play className="ml-0.5 size-5 fill-foreground text-foreground" />
                    </div>
                </div>
            ) : null}
        </div>
    );
}
