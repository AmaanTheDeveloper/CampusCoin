import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

export default function HiwStepOrganize() {
    return (
        <PublicSection id="step-organize" aria-labelledby="hiw-step-03">
            <PublicContainer>
                <div className="cc-hiw-split">
                    <div>
                        <p className="cc-hiw-kicker">03 — Make your spending clear</p>
                        <h2 id="hiw-step-03" className="cc-hiw-title">
                            Organize your finances
                        </h2>
                        <p className="cc-hiw-lede">
                            Categories help turn a long transaction history into patterns you can actually understand — with AI-assisted suggestions
                            where available.
                        </p>
                    </div>

                    <div className="cc-hiw-panel" aria-label="Example categorization">
                        <div className="cc-hiw-panel__body">
                            <p className="cc-hiw-kicker !mb-4">Categorization · Example</p>
                            <div className="cc-hiw-suggest">
                                <div className="cc-hiw-suggest__row">
                                    <span className="cc-hiw-suggest__label">Transaction</span>
                                    <span className="cc-hiw-suggest__value">Campus Cafe — Rs. 650</span>
                                </div>
                                <div className="cc-hiw-suggest__row">
                                    <span className="cc-hiw-suggest__label">Category</span>
                                    <span className="cc-hiw-suggest__value text-primary">Food</span>
                                </div>
                                <div className="cc-hiw-suggest__row">
                                    <span className="cc-hiw-suggest__label">Suggested</span>
                                    <span className="cc-hiw-suggest__value">Food</span>
                                </div>
                                <div className="cc-hiw-suggest__actions" aria-hidden="true">
                                    <span className="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-md px-3 text-sm font-medium">
                                        Accept
                                    </span>
                                    <span className="border-input bg-background inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium">
                                        Change
                                    </span>
                                </div>
                            </div>
                            <p className="cc-hiw-note">Illustrative example — not a live AI result.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
