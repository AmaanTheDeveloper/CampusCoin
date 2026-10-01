import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Camera, CreditCard, ScanLine } from 'lucide-react';

const modes = [
    {
        title: 'Receipt or bill',
        body: 'Point the camera at a document or upload a photo — review fields before anything is saved.',
        icon: Camera,
    },
    {
        title: 'Card statement',
        body: 'Capture statement lines and turn them into reviewable transactions you control.',
        icon: CreditCard,
    },
    {
        title: 'Confirm before save',
        body: 'Smart Scan never posts privately without your review — OCR stays inside your account.',
        icon: ScanLine,
    },
] as const;

export default function FeaturesSmartScan() {
    return (
        <PublicSection id="smart-scan" aria-labelledby="feat-scan-heading">
            <PublicContainer>
                <p className="cc-feat-kicker">Smart Scan</p>
                <h2 id="feat-scan-heading" className="cc-feat-title">
                    Capture a receipt.
                    <br className="hidden sm:block" /> Review. Then save.
                </h2>
                <p className="cc-feat-lede">
                    The same Smart Scan flow from the student dashboard — camera or upload, then a review screen. No bank connection. Concept shown
                    here; scanning happens after you sign in.
                </p>

                <div className="mt-8 grid gap-4 sm:grid-cols-3">
                    {modes.map((mode) => {
                        const Icon = mode.icon;
                        return (
                            <article key={mode.title} className="cc-feat-panel">
                                <div className="cc-feat-panel__body">
                                    <span className="cc-feat-notice__icon" aria-hidden="true">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="mt-3 text-base font-semibold tracking-tight">{mode.title}</h3>
                                    <p className="cc-feat-lede !mt-2 !text-sm">{mode.body}</p>
                                </div>
                            </article>
                        );
                    })}
                </div>
                <p className="cc-feat-note mt-4">Illustrative capability overview — this page does not process documents.</p>
            </PublicContainer>
        </PublicSection>
    );
}
