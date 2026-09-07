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

export type CubaniaShared = {
    social: CubaniaSocialLinks;
    hero: CubaniaHeroConfig;
    instructors: CubaniaInstructor[];
};
