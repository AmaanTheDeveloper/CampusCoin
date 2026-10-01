import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

export default function FeaturesCategories() {
    return (
        <PublicSection id="categories" variant="muted" aria-labelledby="feat-categories-heading">
            <PublicContainer>
                <div className="cc-feat-split cc-feat-split--flip">
                    <div>
                        <p className="cc-feat-kicker">Smart categories</p>
                        <h2 id="feat-categories-heading" className="cc-feat-title">
                            Make every transaction
                            <br className="hidden sm:block" /> easier to understand.
                        </h2>
                        <p className="cc-feat-lede">
                            Categories organize spending and help students understand where money goes — with AI-assisted suggestions where available.
                        </p>
                    </div>

                    <div className="cc-feat-panel" aria-label="Example category suggestion">
                        <div className="cc-feat-panel__body">
                            <p className="cc-feat-kicker !mb-4">AI suggestion · Example</p>
                            <div className="cc-feat-suggest">
                                <div className="cc-feat-suggest__row">
                                    <span className="cc-feat-suggest__label">Description</span>
                                    <span className="cc-feat-suggest__value">Campus Cafe</span>
                                </div>
                                <div className="cc-feat-suggest__row">
                                    <span className="cc-feat-suggest__label">Suggested category</span>
                                    <span className="cc-feat-suggest__value text-primary">Food</span>
                                </div>
                                <div>
                                    <div className="cc-feat-suggest__row mb-2">
                                        <span className="cc-feat-suggest__label">Confidence</span>
                                        <span className="cc-feat-suggest__value">97%</span>
                                    </div>
                                    <div className="cc-feat-suggest__confidence" aria-hidden="true">
                                        <span />
                                    </div>
                                </div>
                                <div className="cc-feat-suggest__actions" aria-hidden="true">
                                    <span className="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-md px-3 text-sm font-medium">
                                        Accept
                                    </span>
                                    <span className="border-input bg-background inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium">
                                        Change
                                    </span>
                                </div>
                            </div>
                            <p className="cc-feat-note">Illustrative example — not a measured accuracy claim or live AI result.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
