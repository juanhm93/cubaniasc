import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCubaniaConfig } from '@/components/base/cubania/use-cubania-config';
import { useTranslation } from '@/i18n/use-translation';
import { reviewPanel } from '@/routes';

/**
 * Site footer with columns and social links.
 */
export function CubaniaFooter(): ReactNode {
    const { t } = useTranslation();
    const { social, footer } = useCubaniaConfig();
    const { community } = footer;
    const hasCommunityLinks = Object.values(community).some(Boolean);

    return (
        <footer className="cubania-footer">
            <div className="cubania-footer__top">
                <div>
                    <div className="cubania-footer__logo">
                        {t('navigation.appName')}
                    </div>
                    <p className="cubania-footer__tagline">
                        {t('landing.footer.tagline')}
                    </p>
                </div>
                <div className="cubania-footer__columns">
                    <div>
                        <h3 className="cubania-footer__col-title">
                            {t('landing.footer.academy')}
                        </h3>
                        <ul className="cubania-footer__list">
                            <li>
                                <a
                                    href="#clases"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.footer.classes')}
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#horarios"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.footer.schedule')}
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.footer.prices')}
                                </a>
                            </li>
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.footer.instructors')}
                                </a>
                            </li>
                        </ul>
                    </div>
                    {hasCommunityLinks ? (
                        <div>
                            <h3 className="cubania-footer__col-title">
                                {t('landing.footer.community')}
                            </h3>
                            <ul className="cubania-footer__list">
                                {community.events ? (
                                    <li>
                                        <a
                                            href="#"
                                            className="cubania-footer__link"
                                            data-cubania-cursor="interactive"
                                        >
                                            {t('landing.footer.events')}
                                        </a>
                                    </li>
                                ) : null}
                                {community.competitions ? (
                                    <li>
                                        <a
                                            href="#"
                                            className="cubania-footer__link"
                                            data-cubania-cursor="interactive"
                                        >
                                            {t('landing.footer.competitions')}
                                        </a>
                                    </li>
                                ) : null}
                                {community.blog ? (
                                    <li>
                                        <a
                                            href="#"
                                            className="cubania-footer__link"
                                            data-cubania-cursor="interactive"
                                        >
                                            {t('landing.footer.blog')}
                                        </a>
                                    </li>
                                ) : null}
                                {community.review ? (
                                    <li>
                                        <Link
                                            href={reviewPanel()}
                                            className="cubania-footer__link"
                                            data-cubania-cursor="interactive"
                                        >
                                            {t('landing.footer.review')}
                                        </Link>
                                    </li>
                                ) : null}
                            </ul>
                        </div>
                    ) : null}
                    <div>
                        <h3 className="cubania-footer__col-title">
                            {t('landing.footer.contact')}
                        </h3>
                        <ul className="cubania-footer__list">
                            {social.whatsapp ? (
                                <li>
                                    <a
                                        href={social.whatsapp}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="cubania-footer__link"
                                        data-cubania-cursor="interactive"
                                    >
                                        {t('landing.footer.whatsapp')}
                                    </a>
                                </li>
                            ) : null}
                            {social.instagram ? (
                                <li>
                                    <a
                                        href={social.instagram}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="cubania-footer__link"
                                        data-cubania-cursor="interactive"
                                    >
                                        {t('landing.footer.instagram')}
                                    </a>
                                </li>
                            ) : null}
                            {social.tiktok ? (
                                <li>
                                    <a
                                        href={social.tiktok}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="cubania-footer__link"
                                        data-cubania-cursor="interactive"
                                    >
                                        {t('landing.footer.tiktok')}
                                    </a>
                                </li>
                            ) : null}
                            <li>
                                <a
                                    href="#"
                                    className="cubania-footer__link"
                                    data-cubania-cursor="interactive"
                                >
                                    {t('landing.footer.location')}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div className="cubania-footer__bottom">
                <span>
                    {t('landing.footer.copyright', {
                        year: new Date().getFullYear(),
                    })}
                </span>
                <div className="cubania-footer__social">
                    {social.instagram ? (
                        <a
                            href={social.instagram}
                            target="_blank"
                            rel="noreferrer"
                            className="cubania-footer__social-link"
                            data-cubania-cursor="interactive"
                        >
                            {t('landing.footer.instagram')}
                        </a>
                    ) : null}
                    {social.tiktok ? (
                        <a
                            href={social.tiktok}
                            target="_blank"
                            rel="noreferrer"
                            className="cubania-footer__social-link"
                            data-cubania-cursor="interactive"
                        >
                            {t('landing.footer.tiktok')}
                        </a>
                    ) : null}
                    {social.whatsapp ? (
                        <a
                            href={social.whatsapp}
                            target="_blank"
                            rel="noreferrer"
                            className="cubania-footer__social-link"
                            data-cubania-cursor="interactive"
                        >
                            {t('landing.footer.whatsapp')}
                        </a>
                    ) : null}
                </div>
            </div>
        </footer>
    );
}
