import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

type CubaniaRevealProps = {
    children: ReactNode;
    className?: string;
    /** Stagger delay in ms (applied to transition). */
    delayMs?: number;
};

/**
 * Fades/slides content in when it enters the viewport (IntersectionObserver).
 */
export function CubaniaReveal({
    children,
    className = '',
    delayMs = 0,
}: CubaniaRevealProps): ReactNode {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;

        if (!el) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    setVisible(true);
                }
            },
            { threshold: 0.15 },
        );

        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={`cubania-reveal ${visible ? 'cubania-reveal--visible' : ''} ${className}`.trim()}
            style={{ transitionDelay: `${delayMs}ms` }}
        >
            {children}
        </div>
    );
}
