import { brandLogoUrl } from '@/lib/brand-logos';
import { cn } from '@/lib/utils';

type AppBrandLogoProps = {
    className?: string;
};

export function AppBrandLogo({ className }: AppBrandLogoProps) {
    return (
        <img
            src={brandLogoUrl(8)}
            alt="Cubanía"
            className={cn('h-auto max-h-32 w-auto object-contain', className)}
        />
    );
}
