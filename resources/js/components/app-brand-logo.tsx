import { cn } from '@/lib/utils';

type AppBrandLogoProps = {
    className?: string;
};

export function AppBrandLogo({ className }: AppBrandLogoProps) {
    return (
        <img
            src="/logo.webp"
            alt="Cubanía"
            className={cn('h-auto w-auto max-h-16 object-contain', className)}
        />
    );
}
