import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/i18n/use-translation';

const STORAGE_KEY = 'cubania.reviewWelcomeSeenOn';
const GROUP_PHOTO = '/cubania-assets/slider/slider-7.webp';

function todayKey(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

/**
 * Whether the welcome reminder was already shown today in this browser.
 * Blocked storage counts as "not seen", so the reminder still appears.
 */
export function reviewWelcomeSeenToday(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) === todayKey();
    } catch {
        return false;
    }
}

export function markReviewWelcomeSeen(): void {
    try {
        localStorage.setItem(STORAGE_KEY, todayKey());
    } catch {
        // Private mode or blocked storage: it shows again next time.
    }
}

type ReviewWelcomeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

/**
 * Reminder shown once a day before the review starts: the phone gives the
 * steps, but the dance happens out there with friends.
 */
export function ReviewWelcomeDialog({
    open,
    onOpenChange,
    onConfirm,
}: ReviewWelcomeDialogProps) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="cubania-landing cubania-review-welcome">
                <img
                    className="cubania-review-welcome__photo"
                    src={GROUP_PHOTO}
                    alt={t('review.welcome.photoAlt')}
                    width={2133}
                    height={1600}
                />

                <div className="cubania-review-welcome__content">
                    <DialogHeader>
                        <DialogTitle className="cubania-review-welcome__title">
                            {t('review.welcome.title')}
                        </DialogTitle>
                        <DialogDescription className="cubania-review-welcome__body">
                            {t('review.welcome.body')}
                        </DialogDescription>
                    </DialogHeader>

                    <button
                        type="button"
                        className="cubania-btn cubania-btn--primary cubania-review-welcome__confirm"
                        onClick={onConfirm}
                        data-cubania-cursor="interactive"
                    >
                        {t('review.welcome.confirm')}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
