import type { ReactNode } from 'react';

type CubaniaTestimonialCardProps = {
    quote: string;
    authorName: string;
    authorRole: string;
    avatarLetter: string;
};

/**
 * Single testimonial block with quote mark, italic body, and avatar row.
 */
export function CubaniaTestimonialCard({
    quote,
    authorName,
    authorRole,
    avatarLetter,
}: CubaniaTestimonialCardProps): ReactNode {
    return (
        <article className="cubania-testimonial-card">
            <div className="cubania-testimonial-card__quote">"</div>
            <p className="cubania-testimonial-card__text">{quote}</p>
            <div className="cubania-testimonial-card__author">
                <div className="cubania-testimonial-card__avatar">
                    {avatarLetter}
                </div>
                <div>
                    <div className="cubania-testimonial-card__name">
                        {authorName}
                    </div>
                    <div className="cubania-testimonial-card__role">
                        {authorRole}
                    </div>
                </div>
            </div>
        </article>
    );
}
