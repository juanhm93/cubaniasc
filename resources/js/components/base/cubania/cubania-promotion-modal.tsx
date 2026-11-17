import { XIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CubaniaPromotionCta } from '@/components/base/cubania/cubania-promotion-cta';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';
import type { LandingPromotion } from '@/types/cubania';

function dismissalKey(promotion: LandingPromotion): string {
    return `cubania-promo-dismissed-${promotion.id}-${promotion.version}`;
}

/**
 * Whether the visitor already closed this version of the promotion in this tab session.
 * Blocked storage counts as "not dismissed", so the promotion still shows.
 */
function wasDismissed(promotion: LandingPromotion): boolean {
    try {
        return sessionStorage.getItem(dismissalKey(promotion)) === '1';
    } catch {
        return false;
    }
}

function markDismissed(promotion: LandingPromotion): void {
    try {
        sessionStorage.setItem(dismissalKey(promotion), '1');
    } catch {
        // Private mode or blocked storage: it shows again on the next visit.
    }
}

type CubaniaPromotionModalProps = {
    promotion: LandingPromotion;
};

/**
 * Promotion image shown once per visit when the landing opens, closed with the X at the
 * top right. Its width follows the image orientation; its height never exceeds the
 * viewport (the image shrinks instead of scrolling) and it stacks above the fixed nav,
 * so the X is always visible. Editing the promotion changes its version, so visitors who
 * closed the old one see the new one.
 */
export function CubaniaPromotionModal({
    promotion,
}: CubaniaPromotionModalProps): ReactNode {
    const { t } = useTranslation();
    const [open, setOpen] = useState(
        () => typeof window !== 'undefined' && !wasDismissed(promotion),
    );

    const close = (): void => {
        markDismissed(promotion);
        setOpen(false);
    };

    const landscape = promotion.modalOrientation === 'landscape';
    const hasCta = promotion.ctaLabel !== null && promotion.ctaUrl !== null;

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    close();
                }
            }}
        >
            <DialogContent
                showCloseButton={false}
                overlayClassName="z-[300]"
                className={cn(
                    'z-[300] flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden border-white/10 bg-[#1a1025] p-0 text-[#f9f5ff]',
                    landscape ? 'sm:max-w-3xl' : 'sm:max-w-md',
                )}
            >
                <DialogTitle className="sr-only">{promotion.title}</DialogTitle>
                <DialogDescription className="sr-only">
                    {t('landing.promotion.dialogDescription')}
                </DialogDescription>
                <img
                    src={promotion.imageUrl}
                    alt={promotion.title}
                    className={cn(
                        'block min-h-0 w-full shrink object-contain',
                        hasCta ? 'rounded-t-lg' : 'rounded-lg',
                    )}
                />
                <DialogClose
                    aria-label={t('landing.promotion.close')}
                    data-cubania-cursor="interactive"
                    className="absolute top-3 right-3 inline-flex size-9 items-center justify-center rounded-full bg-black/60 text-[#f9f5ff] transition-colors hover:bg-black/80 hover:text-[#f5c842] focus-visible:ring-2 focus-visible:ring-[#f5c842] focus-visible:outline-none"
                >
                    <XIcon className="size-5" aria-hidden="true" />
                </DialogClose>
                {hasCta ? (
                    <div className="shrink-0 p-5">
                        <CubaniaPromotionCta
                            promotion={promotion}
                            onClick={() => markDismissed(promotion)}
                            className="inline-flex w-full items-center justify-center rounded-[2px] bg-[#f5c842] px-6 py-3.5 font-['Archivo',sans-serif] text-[0.86rem] font-extrabold tracking-[0.1em] text-[#0a0010] uppercase transition-colors hover:bg-[#ffd94a]"
                        />
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
