import ContactFaq from '@/components/public/contact/contact-faq';
import ContactFinalCta from '@/components/public/contact/contact-final-cta';
import ContactForm from '@/components/public/contact/contact-form';
import ContactHero from '@/components/public/contact/contact-hero';
import ContactSupports from '@/components/public/contact/contact-supports';
import ContactTrust from '@/components/public/contact/contact-trust';
import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import PublicLayout from '@/layouts/public-layout';

const CONTACT_DESCRIPTION = 'Contact Campus Coin with questions, feedback, or technical concerns about student budgeting and expense tracking.';

export default function Contact() {
    return (
        <PublicLayout title="Contact" description={CONTACT_DESCRIPTION} className="cc-public-contact">
            <ContactHero />
            <PublicSection aria-label="Contact form and support options">
                <PublicContainer>
                    <div className="cc-contact-grid">
                        <ContactForm />
                        <ContactSupports />
                    </div>
                </PublicContainer>
            </PublicSection>
            <ContactFaq />
            <ContactTrust />
            <ContactFinalCta />
        </PublicLayout>
    );
}
