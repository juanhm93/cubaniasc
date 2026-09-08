import type { ReactNode, SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

export function InstagramIcon(props: IconProps): ReactNode {
    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden {...props}>
            <rect
                x="3.5"
                y="3.5"
                width="17"
                height="17"
                rx="5"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <circle
                cx="12"
                cy="12"
                r="4.1"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <circle cx="17.2" cy="6.8" r="1.05" fill="currentColor" />
        </svg>
    );
}

export function TikTokIcon(props: IconProps): ReactNode {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden {...props}>
            <path d="M14.6 3.2c.35 2.38 1.78 4.05 4.15 4.42v3.02c-1.4.04-2.7-.4-3.88-1.18v6.62c0 3.5-2.84 6.34-6.34 6.34S2.2 19.58 2.2 16.08c0-3.38 2.64-6.15 5.97-6.33v3.1A3.26 3.26 0 0 0 5.5 16.08a3.26 3.26 0 0 0 3.26 3.26 3.26 3.26 0 0 0 3.26-3.26V3.2h2.58Z" />
        </svg>
    );
}

export function WhatsAppIcon(props: IconProps): ReactNode {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden {...props}>
            <path d="M12.04 2.2A9.8 9.8 0 0 0 2.2 11.96c0 1.73.45 3.42 1.32 4.9L2 22l5.28-1.38a9.8 9.8 0 0 0 4.76 1.22h.01A9.8 9.8 0 0 0 22 11.97 9.8 9.8 0 0 0 12.04 2.2Zm5.73 13.86c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.89-1.25-4.77-4.16-4.92-4.35-.14-.2-1.17-1.55-1.17-2.96 0-1.4.73-2.09 1-2.38.24-.26.64-.37.86-.37h.62c.2 0 .46 0 .7.54l.9 2.2c.08.2.11.37-.05.58l-.4.48c-.13.16-.28.34-.12.63.16.3.7 1.16 1.5 1.88.97.87 1.8 1.16 2.1 1.3.22.1.48.08.66-.13l.5-.6c.16-.2.4-.16.64-.1l2.12.99c.24.11.4.17.46.27.05.12.05.7-.2 1.38Z" />
        </svg>
    );
}
