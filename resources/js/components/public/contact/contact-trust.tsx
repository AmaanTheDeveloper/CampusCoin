import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

export default function ContactTrust() {
    return (
        <PublicSection aria-labelledby="contact-trust-heading">
            <PublicContainer>
                <div className="cc-contact-trust max-w-3xl">
                    <p className="cc-ac-kicker">Product scope</p>
                    <h2 id="contact-trust-heading" className="cc-ac-title !text-2xl">
                        Your financial information should be handled responsibly.
                    </h2>
                    <p className="cc-ac-lede">
                        Campus Coin is designed as a student budgeting and expense-tracking tool. It does not connect to bank accounts or process real
                        payments. AI assistance remains advisory — suggestions you can review, not decisions made for you.
                    </p>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
