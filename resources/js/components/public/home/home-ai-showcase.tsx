import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';

export default function HomeAiShowcase() {
    const [whyOpen, setWhyOpen] = useState(true);

    return (
        <PublicSection className="cc-home-ai !py-16 sm:!py-20" aria-labelledby="home-ai-heading">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-home-kicker">
                        <span className="inline-flex items-center gap-1.5">
                            <Sparkles className="size-3.5" aria-hidden />
                            Intelligence
                        </span>
                    </p>
                    <h2 id="home-ai-heading" className="cc-home-section-title">
                        Your spending has a story.
                    </h2>
                    <p className="cc-home-section-lede">Let Campus Coin help you understand the patterns behind your numbers.</p>
                </div>

                <div className="cc-home-ai-panel" role="group" aria-label="Example AI insight preview">
                    <div className="cc-home-ai-card cc-home-ai-card--1">
                        <p className="cc-home-ai-card__label">Campus Coin AI · Example</p>
                        <p>You spent 18% more on food than your recent average.</p>
                    </div>

                    <div className="cc-home-ai-card cc-home-ai-card--2">
                        <button
                            type="button"
                            className="cc-home-ai-card__label rounded-sm hover:opacity-90 focus-visible:ring-2 focus-visible:ring-[#e94f09] focus-visible:ring-offset-0 focus-visible:outline-none"
                            aria-expanded={whyOpen}
                            onClick={() => setWhyOpen((open) => !open)}
                        >
                            Why?
                        </button>
                        {whyOpen && <p>Most of the increase came from food spending during the second half of the month.</p>}
                    </div>

                    <div className="cc-home-ai-card cc-home-ai-card--3">
                        <p className="cc-home-ai-card__label">Suggested action</p>
                        <p>Try setting a monthly food budget.</p>
                    </div>
                </div>

                <p className="cc-home-ai-disclaimer">Illustrative example only — not live advice or a prediction about your account.</p>
            </PublicContainer>
        </PublicSection>
    );
}
