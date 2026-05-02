import { useEffect } from 'react';

/**
 * Enables smooth scrolling while the Cubanía landing is mounted (window scroll).
 */
export function useCubaniaSmoothScroll(): void {
    useEffect(() => {
        const previous = document.documentElement.style.scrollBehavior;
        document.documentElement.style.scrollBehavior = 'smooth';

        return () => {
            document.documentElement.style.scrollBehavior = previous;
        };
    }, []);
}
