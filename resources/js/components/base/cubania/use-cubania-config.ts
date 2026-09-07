import { usePage } from '@inertiajs/react';
import type { CubaniaShared } from '@/types/cubania';

const fallbackCubania: CubaniaShared = {
    social: {
        instagram: 'https://www.instagram.com/cubania.sc',
        tiktok: 'https://www.tiktok.com/@cubania.sc',
        whatsapp: 'https://wa.me/+584122801334',
    },
    hero: {
        youtubeUrl: 'https://www.youtube.com/watch?v=s4DT0BFxDEk',
        youtubeId: 's4DT0BFxDEk',
        playbackRate: 0.75,
    },
    instructors: [
        {
            image: '/cubania-assets/juan.webp',
        },
        {
            image: 'https://images.unsplash.com/photo-1547153760-18fc86324498?auto=format&fit=crop&w=800&q=80',
        },
        {
            image: '/cubania-assets/javier.webp',
        },
    ],
};

export function useCubaniaConfig(): CubaniaShared {
    const { cubania } = usePage().props;

    if (!cubania) {
        return fallbackCubania;
    }

    return cubania;
}
