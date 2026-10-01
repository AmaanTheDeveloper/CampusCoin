import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';

export default function FeaturesAi() {
    const [whyOpen, setWhyOpen] = useState(true);

    return (
        <PublicSection id="ai" className="cc-feat-ai !py-16 sm:!py-20" aria-labelledby="feat-ai-heading">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-feat-kicker">
                        <span className="inline-flex items-center gap-1.5">
                            <Sparkles className="size-3.5" aria-hidden />
                            AI insights
                        </span>
                    </p>
                    <h2 id="feat-ai-heading" className="cc-feat-title">
                        See the pattern.
                        <br className="hidden sm:block" /> Not just the number.
                    </h2>
                    <p className="cc-feat-lede">Campus Coin surfaces readable insights so students can understand change — then decide what to do.</p>
                </div>

                <div className="cc-feat-ai-cards" role="group" aria-label="Example AI insight">
                    <div className="cc-feat-ai-card">
                        <p className="cc-feat-ai-card__label">Campus Coin AI · Example</p>
                        <p>Food spending is up 18% compared with your recent average.</p>
                    </div>
                    <div className="cc-feat-ai-card">
                        <button
                            type="button"
                            className="cc-feat-ai-card__label rounded-sm focus-visible:ring-2 focus-visible:ring-[#e94f09] focus-visible:outline-none"
                            aria-expanded={whyOpen}
                            onClick={() => setWhyOpen((open) => !open)}
                        >
                            Why it matters
                        </button>
                        {whyOpen && <p>Most of the increase came from higher food spending later in the month.</p>}
                    </div>
                    <div className="cc-feat-ai-card">
                        <p className="cc-feat-ai-card__label">Suggested action</p>
                        <p>Consider tightening your monthly food budget.</p>
                    </div>
                </div>

                <p className="cc-feat-ai-disclaimer">Illustrative example only — advisory, not financial certainty or automated decisions.</p>
            </PublicContainer>
        </PublicSection>
    );
}
