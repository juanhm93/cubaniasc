import { cn } from '@/lib/utils';

type AppLogoIconProps = {
    className?: string;
};

export default function AppLogoIcon({ className }: AppLogoIconProps) {
    return (
        <img
            src="/favicon.jpeg"
            alt=""
            className={cn('size-full object-contain', className)}
        />
    );
}
