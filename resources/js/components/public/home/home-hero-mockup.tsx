import { Sparkles } from 'lucide-react';

/**
 * Illustrative marketing product preview — not live user data.
 */
export default function HomeHeroMockup() {
    return (
        <div className="cc-home-preview cc-home-enter cc-home-enter--4" aria-hidden="true">
            <div className="cc-home-preview__glow" />
            <div className="cc-home-preview__frame">
                <span className="cc-home-preview__badge">Example preview</span>
                <div className="cc-home-preview__body">
                    <div className="cc-home-preview__brand">
                        <span className="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md text-[10px] font-bold">
                            CC
                        </span>
                        Campus Coin
                    </div>

                    <p className="cc-public-caption">Balance</p>
                    <p className="cc-home-preview__balance">Rs. 17,500</p>

                    <div className="cc-home-preview__metrics">
                        <div className="cc-home-preview__metric">
                            <p className="cc-home-preview__metric-label">Income</p>
                            <p className="cc-home-preview__metric-value">Rs. 60,000</p>
                        </div>
                        <div className="cc-home-preview__metric">
                            <p className="cc-home-preview__metric-label">Expenses</p>
                            <p className="cc-home-preview__metric-value">Rs. 42,500</p>
                        </div>
                    </div>

                    <p className="cc-home-preview__savings">Savings 29.1%</p>

                    <div className="cc-home-preview__bars">
                        <div className="cc-home-preview__bar-row">
                            <span>Food</span>
                            <div className="cc-home-preview__bar-track">
                                <div className="cc-home-preview__bar-fill" style={{ width: '78%' }} />
                            </div>
                        </div>
                        <div className="cc-home-preview__bar-row">
                            <span>Transport</span>
                            <div className="cc-home-preview__bar-track">
                                <div className="cc-home-preview__bar-fill" style={{ width: '52%', animationDelay: '120ms' }} />
                            </div>
                        </div>
                    </div>

                    <div className="cc-home-preview__insight">
                        <p className="cc-home-preview__insight-label">
                            <Sparkles className="size-3.5" aria-hidden />
                            AI Insight
                        </p>
                        <p className="cc-home-preview__insight-text">Food spending increased versus your recent average.</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
