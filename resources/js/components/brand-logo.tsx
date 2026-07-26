import { cn } from '@/lib/utils';

export function BrandLogo({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-2', className)}>
            <img
                src="/assets/logo/logo.png"
                alt="FreeKliping"
                className="size-10"
            />

            <span className="text-[24px] leading-none font-bold whitespace-nowrap">
                <span className="text-foreground">Free</span>
                <span className="text-brand">Kliping</span>
            </span>
        </span>
    );
}
