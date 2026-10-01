import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

const faqs = [
    {
        q: 'How does Campus Coin categorize transactions?',
        a: 'You can assign categories when recording transactions. Where available, Campus Coin may also offer AI-assisted category suggestions that you can accept or change.',
    },
    {
        q: 'Can I import existing transactions?',
        a: 'Yes. Campus Coin supports CSV import so you can bring supported transaction history into the product, then review and categorize before finishing the import.',
    },
    {
        q: 'Can I export financial reports?',
        a: 'Yes. The authenticated reports experience includes report views and export options so you can review spending outside the app when needed.',
    },
    {
        q: 'Does Campus Coin connect to my bank?',
        a: 'No. Campus Coin does not connect to bank accounts or process real payments. It is a student budgeting and expense-tracking tool based on the information you enter or import.',
    },
    {
        q: 'How does AI assistance work?',
        a: 'AI assistance is advisory. It can help surface category suggestions and spending insights, but students stay in control and can review or override suggestions.',
    },
] as const;

export default function ContactFaq() {
    const [openIndex, setOpenIndex] = useState<number | null>(0);

    return (
        <PublicSection id="faq" variant="muted" aria-labelledby="contact-faq-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">FAQ</p>
                <h2 id="contact-faq-heading" className="cc-ac-title">
                    Common questions
                </h2>
                <div className="cc-contact-faq">
                    {faqs.map((item, index) => {
                        const open = openIndex === index;
                        return (
                            <Collapsible
                                key={item.q}
                                open={open}
                                onOpenChange={(next) => setOpenIndex(next ? index : null)}
                                className="cc-contact-faq__item"
                            >
                                <CollapsibleTrigger className="cc-contact-faq__trigger" aria-expanded={open}>
                                    <span>{item.q}</span>
                                    <ChevronDown className={`size-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="cc-contact-faq__content">{item.a}</CollapsibleContent>
                            </Collapsible>
                        );
                    })}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
