import { cn } from '@/lib/utils';

export function BrandLogo({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center', className)}>
            <img
                src="/assets/logo/logo.png"
                alt=""
                className="size-10 shrink-0 object-contain drop-shadow-[0_10px_24px_rgba(242,169,59,0.24)]"
            />
            <span className="text-[24px] leading-none font-semibold tracking-normal whitespace-nowrap">
                <span className="text-foreground">Free</span>
                <span className="text-brand">Kliping</span>
            </span>
        </span>
    );
}
