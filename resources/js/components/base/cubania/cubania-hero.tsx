import type { ReactNode } from 'react';
import { CubaniaPhotoCard } from '@/components/cards/photo-card';

/**
 * Full-viewport hero: headline, CTAs, floating cards, stats, scroll hint.
 */
export function CubaniaHero(): ReactNode {
    return (
        <section className="cubania-hero" id="clases">
            <div className="cubania-hero__bg">
                <div className="cubania-hero__orb cubania-hero__orb--1" />
                <div className="cubania-hero__orb cubania-hero__orb--2" />
                <div className="cubania-hero__orb cubania-hero__orb--3" />
            </div>
            <div className="cubania-hero__line" aria-hidden />
            <div className="cubania-hero__dots" aria-hidden />

            <div className="cubania-hero__content">
                <div className="cubania-hero__eyebrow">
                    Salsa Casino & Bachata · Cumaná - Sucre, Venezuela
                </div>

                <h1 className="cubania-hero__title">
                    <span className="cubania-hero__title-outline">Vive</span>
                    <span className="cubania-hero__title-accent">Bailando</span>
                </h1>

                <p className="cubania-hero__subtitle">
                    Te hacemos feliz a través del baile. Pasión y educación son nuestros valores.
                </p>

                <div className="cubania-hero__actions">
                    <a
                        href="#inscripcion"
                        className="cubania-btn cubania-btn--primary"
                        data-cubania-cursor="interactive"
                    >
                        Empieza ahora
                    </a>
                    <a
                        href="#estilos"
                        className="cubania-btn cubania-btn--secondary"
                        data-cubania-cursor="interactive"
                    >
                        Ver clases
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden
                        >
                            <path
                                d="M3 8H13M13 8L9 4M13 8L9 12"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </a>
                </div>
                <div className="cubania-hero__stats mt-6">
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">+2100</span>
                        <span className="cubania-stat__label">Seguidores</span>
                    </div>
                    <div className="cubania-stat__divider" aria-hidden />
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">+9</span>
                        <span className="cubania-stat__label">Años de pasión</span>
                    </div>
                    <div className="cubania-stat__divider" aria-hidden />
                    <div className="cubania-stat">
                        <span className="cubania-stat__num">100%</span>
                        <span className="cubania-stat__label">#Cubanízate</span>
                    </div>
                </div>
            </div>

            <div className="cubania-hero__panel" aria-hidden>
                <div className="cubania-hero__panel-inner">
                    <CubaniaPhotoCard
                        variant="a"
                        gradient="linear-gradient(135deg, #3D1080 0%, #8B31F0 40%, #1A0540 100%)"
                        emoji="💃"
                        tag="Salsa Casino"
                    />
                    <CubaniaPhotoCard
                        variant="b"
                        gradient="linear-gradient(135deg, #0D0A2E 0%, #2A1060 60%, #6B21C8 100%)"
                        emoji="🕺"
                        tag="Bachata"
                    />
                    <CubaniaPhotoCard
                        variant="c"
                        gradient="linear-gradient(135deg, #1A0525 0%, #5E18C0 60%, #F5C842 100%)"
                        emoji="🎵"
                        tag="Rueda"
                    />
                </div>
            </div>

         

            <div className="cubania-scroll-hint">
                <div className="cubania-scroll-hint__line" />
                Scroll
            </div>
        </section>
    );
}
