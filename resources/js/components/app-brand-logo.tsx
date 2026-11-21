import { cn } from '@/lib/utils';

type AppBrandLogoProps = {
    className?: string;
};

export function AppBrandLogo({ className }: AppBrandLogoProps) {
    return (
        <img
            src="/brand/logos/cubania-logo-8.webp"
            alt="Cubanía"
            className={cn('h-auto max-h-32 w-auto object-contain', className)}
        />
    );
}
