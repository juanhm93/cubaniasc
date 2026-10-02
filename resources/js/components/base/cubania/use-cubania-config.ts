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
            image: '/cubania-assets/profesor_juan.webp',
        },
        {
            image: '/cubania-assets/profesor_mare.webp',
        },
        {
            image: '/cubania-assets/profesor_javier.webp',
        },
    ],
    sliderImages: [
        '/cubania-assets/slider/slider-1.webp',
        '/cubania-assets/slider/slider-2.webp',
        '/cubania-assets/slider/slider-3.webp',
        '/cubania-assets/slider/slider-4.webp',
    ],
    footer: {
        community: {
            events: false,
            competitions: false,
            blog: false,
            review: false,
        },
    },
};

export function useCubaniaConfig(): CubaniaShared {
    const { cubania } = usePage().props;

    if (!cubania) {
        return fallbackCubania;
    }

    return cubania;
}
