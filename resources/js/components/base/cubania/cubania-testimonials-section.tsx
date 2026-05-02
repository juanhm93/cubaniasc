import type { ReactNode } from 'react';
import { CubaniaReveal } from '@/components/base/cubania/cubania-reveal';
import { CubaniaTestimonialCard } from '@/components/base/cubania/cubania-testimonial-card';

/**
 * Community quotes section.
 */
export function CubaniaTestimonialsSection(): ReactNode {
    return (
        <section className="cubania-testimonials" id="testimonios">
            <div className="cubania-section-header__label">Lo que dicen</div>
            <h2 className="cubania-section-header__title">
                La comunidad
                <br />
                Cubanía
            </h2>

            <div className="cubania-testimonials__grid">
                <CubaniaReveal delayMs={0}>
                    <CubaniaTestimonialCard
                        quote="Llegué sin saber bailar y ahora no puedo imaginar mi semana sin Cubanía. Es más que clases, es una familia."
                        authorName="María G."
                        authorRole="Estudiante · Nivel Intermedio"
                        avatarLetter="M"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={100}>
                    <CubaniaTestimonialCard
                        quote="Los profesores tienen una energía increíble. Aprendes técnica pero también filosofía del baile."
                        authorName="Rafael T."
                        authorRole="Estudiante · Nivel Avanzado"
                        avatarLetter="R"
                    />
                </CubaniaReveal>
                <CubaniaReveal delayMs={200}>
                    <CubaniaTestimonialCard
                        quote="La sede en el Ministerio de Cultura es preciosa. Cada clase es una experiencia diferente y auténtica."
                        authorName="Laura P."
                        authorRole="Estudiante · Nivel Principiante"
                        avatarLetter="L"
                    />
                </CubaniaReveal>
            </div>
        </section>
    );
}
