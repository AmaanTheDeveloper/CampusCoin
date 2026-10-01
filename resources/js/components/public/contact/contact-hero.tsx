import PublicContainer from '@/components/public/public-container';

export default function ContactHero() {
    return (
        <section className="cc-contact-hero" aria-labelledby="contact-hero-heading">
            <PublicContainer>
                <div className="cc-ac-enter max-w-2xl">
                    <p className="cc-ac-kicker">Contact</p>
                    <h1 id="contact-hero-heading" className="cc-contact-hero__title">
                        <span>Have a question?</span>
                        <span>Let&apos;s talk.</span>
                    </h1>
                    <p className="cc-ac-lede">
                        Questions, feedback or technical concerns? Send us a message and we&apos;ll route it to the appropriate support path.
                    </p>
                </div>
            </PublicContainer>
        </section>
    );
}
