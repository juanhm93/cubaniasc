export type CubaniaSocialLinks = {
    instagram: string;
    tiktok: string;
    whatsapp: string;
};

export type CubaniaHeroConfig = {
    youtubeUrl: string;
    youtubeId: string | null;
    playbackRate: number;
};

export type CubaniaInstructor = {
    image: string;
};

export type CubaniaFooterCommunityLinks = {
    events: boolean;
    competitions: boolean;
    blog: boolean;
    review: boolean;
};

export type CubaniaFooterConfig = {
    community: CubaniaFooterCommunityLinks;
};

export type CubaniaShared = {
    social: CubaniaSocialLinks;
    hero: CubaniaHeroConfig;
    instructors: CubaniaInstructor[];
    sliderImages: string[];
    footer: CubaniaFooterConfig;
};

/** Promotion the public landing shows today (`promotion` prop of `welcome`). */
export type LandingPromotion = {
    id: number;
    title: string;
    imageUrl: string;
    ctaLabel: string | null;
    ctaUrl: string | null;
    showModal: boolean;
    showSection: boolean;
    /** Image shape, so the modal is narrow for a flyer and wide for a banner. */
    modalOrientation: 'portrait' | 'landscape';
    /** Changes whenever staff edits the promotion, so a dismissed modal shows again. */
    version: number;
};
