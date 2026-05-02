import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

/**
 * Custom cursor (dot + ring) for the Cubanía landing. Updates DOM refs in rAF.
 */
export function CubaniaCursor(): ReactNode {
    const dotRef = useRef<HTMLDivElement>(null);
    const ringRef = useRef<HTMLDivElement>(null);
    const mx = useRef(0);
    const my = useRef(0);
    const rx = useRef(0);
    const ry = useRef(0);
    const hoverRef = useRef(false);

    useEffect(() => {
        const onMove = (e: MouseEvent): void => {
            mx.current = e.clientX;
            my.current = e.clientY;
        };

        const onHoverScan = (e: MouseEvent): void => {
            const el = e.target as HTMLElement | null;
            hoverRef.current = Boolean(
                el?.closest('[data-cubania-cursor="interactive"]'),
            );
        };

        let frame = 0;

        const tick = (): void => {
            rx.current += (mx.current - rx.current) * 0.12;
            ry.current += (my.current - ry.current) * 0.12;

            const dot = dotRef.current;
            const ring = ringRef.current;
            const hover = hoverRef.current;
            const size = hover ? 56 : 36;

            if (dot) {
                const scale = hover ? ' scale(1.6)' : '';
                dot.style.transform = `translate(${mx.current - 6}px, ${my.current - 6}px)${scale}`;
            }

            if (ring) {
                ring.style.width = `${size}px`;
                ring.style.height = `${size}px`;
                ring.style.transform = `translate(${rx.current - size / 2}px, ${ry.current - size / 2}px)`;
            }

            frame = requestAnimationFrame(tick);
        };

        frame = requestAnimationFrame(tick);
        window.addEventListener('mousemove', onMove);
        document.addEventListener('mouseover', onHoverScan);

        return () => {
            window.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseover', onHoverScan);
            cancelAnimationFrame(frame);
        };
    }, []);

    return (
        <>
            <div ref={dotRef} className="cubania-cursor" aria-hidden />
            <div ref={ringRef} className="cubania-cursor-ring" aria-hidden />
        </>
    );
}
