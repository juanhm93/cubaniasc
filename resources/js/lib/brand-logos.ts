/**
 * Version appended to brand logo URLs to bust browser and CDN caches.
 * Files in `public/` are served with a long max-age and no content hash,
 * so bump this value every time a file in `public/brand/logos` changes.
 */
const BRAND_LOGO_VERSION = '2';

export function brandLogoUrl(logoNumber: number): string {
    return `/brand/logos/cubania-logo-${logoNumber}.webp?v=${BRAND_LOGO_VERSION}`;
}
