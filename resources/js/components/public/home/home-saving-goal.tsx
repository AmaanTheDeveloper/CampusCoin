import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function HomeSavingGoal() {
    const { auth } = usePage<SharedData>().props;
    const href = auth.user ? route('dashboard') : route('register');

    return (
        <PublicSection aria-labelledby="home-goal-heading">
            <PublicContainer>
                <div className="cc-home-goal">
                    <div>
                        <p className="cc-home-kicker">Student goals</p>
                        <h2 id="home-goal-heading" className="cc-home-section-title">
                            Small changes.
                            <br className="hidden sm:block" /> Bigger goals.
                        </h2>
                        <p className="cc-home-section-lede">
                            Turn everyday tracking into progress you can see — whether you are saving for a laptop, a trip home, or next semester.
                        </p>
                        <div className="mt-8">
                            <Button asChild size="lg">
                                <Link href={href}>
                                    Build your goal
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <aside className="cc-home-goal-card" aria-label="Example saving goal preview">
                        <p className="cc-home-dash__label">Monthly savings · Example</p>
                        <p className="cc-home-goal-card__amount mt-2">Rs. 12,500</p>
                        <div className="mt-6 flex items-end justify-between gap-4">
                            <div>
                                <p className="cc-public-caption">Goal</p>
                                <p className="mt-1 text-lg font-semibold tracking-tight">New laptop</p>
                            </div>
                            <p className="text-primary text-sm font-bold tracking-wide">42% complete</p>
                        </div>
                        <div className="cc-home-goal-progress mt-4" aria-hidden="true">
                            <span />
                        </div>
                        <p className="cc-public-caption mt-4">Illustrative example — not linked to any account.</p>
                    </aside>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
