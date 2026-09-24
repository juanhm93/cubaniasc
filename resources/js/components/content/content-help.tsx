import { CircleAlert, Lightbulb } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/i18n/use-translation';

export function ContentHelpBadge({ children }: { children: ReactNode }) {
    return (
        <Badge
            variant="outline"
            className="border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
        >
            <CircleAlert aria-hidden />
            {children}
        </Badge>
    );
}

export function ContentHelpNotice({ messages }: { messages: string[] }) {
    const { t } = useTranslation();

    if (messages.length === 0) {
        return null;
    }

    return (
        <Alert className="border-amber-500/40 bg-amber-500/10">
            <Lightbulb className="text-amber-600 dark:text-amber-300" />
            <AlertTitle className="text-amber-800 dark:text-amber-200">
                {t('content.help.noticeTitle')}
            </AlertTitle>
            <AlertDescription className="text-amber-900/80 dark:text-amber-100/80">
                {messages.map((message) => (
                    <p key={message}>{message}</p>
                ))}
            </AlertDescription>
        </Alert>
    );
}
