import type { ReactNode } from 'react';
import { WhatsAppIcon } from '@/components/base/cubania/cubania-social-icons';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { useTranslation } from '@/i18n/use-translation';

export function CubaniaWhatsappFloat(): ReactNode {
    const { t } = useTranslation();
    const { social } = useCubaniaConfig();

    if (!social.whatsapp) {
        return null;
    }

    return (
        <a
            href={social.whatsapp}
            className="cubania-whatsapp-float"
            target="_blank"
            rel="noreferrer"
            aria-label={t('landing.social.whatsappAria')}
            data-cubania-cursor="interactive"
        >
            <WhatsAppIcon width={28} height={28} />
        </a>
    );
}
