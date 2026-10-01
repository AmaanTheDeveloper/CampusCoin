import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Lightbulb, Target } from 'lucide-react';

export default function FeaturesSaving() {
    const { auth } = usePage<SharedData>().props;
    const href = auth.user ? route('dashboard') : route('register');

    return (
        <PublicSection id="saving" aria-labelledby="feat-saving-heading">
            <PublicContainer>
                <p className="cc-feat-kicker">Saving tips & goals</p>
                <h2 id="feat-saving-heading" className="cc-feat-title">
                    Make saving
                    <br className="hidden sm:block" /> feel measurable.
                </h2>
                <p className="cc-feat-lede">Pair practical tips with goals you can track — progress you can see, without overpromising outcomes.</p>

                <div className="cc-feat-save-grid mt-8">
                    <article className="cc-feat-panel">
                        <div className="cc-feat-panel__body">
                            <span className="cc-feat-notice__icon" aria-hidden="true">
                                <Lightbulb className="size-4" />
                            </span>
                            <p className="cc-feat-kicker !mt-3">Saving tip · Example</p>
                            <h3 className="mt-1 text-lg font-semibold tracking-tight">Food spending increased this month.</h3>
                            <p className="cc-feat-lede !mt-2 !text-sm">Suggested: try adjusting your monthly food budget.</p>
                        </div>
                    </article>

                    <article className="cc-feat-panel" aria-label="Example saving goal">
                        <div className="cc-feat-panel__body">
                            <span className="cc-feat-notice__icon" aria-hidden="true">
                                <Target className="size-4" />
                            </span>
                            <p className="cc-feat-kicker !mt-3">Goal · Example</p>
                            <h3 className="mt-1 text-lg font-semibold tracking-tight">New laptop</h3>
                            <p className="mt-3 text-2xl font-semibold tracking-tight">Rs. 12,500 saved</p>
                            <p className="mt-1 text-sm font-bold text-[var(--cc-accent-text,#d14508)]">42% complete</p>
                            <div className="cc-feat-progress" aria-hidden="true">
                                <span />
                            </div>
                            <p className="cc-feat-note">Illustrative example — not linked to any account.</p>
                        </div>
                    </article>
                </div>

                <Link href={href} className="cc-feat-link">
                    Start managing money
                    <ArrowRight className="size-3.5" aria-hidden />
                </Link>
            </PublicContainer>
        </PublicSection>
    );
}
