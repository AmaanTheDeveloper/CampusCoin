import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const stages = [
    { stage: 'Data', name: 'Your activity' },
    { stage: 'System', name: 'Campus Coin' },
    { stage: 'Organization', name: 'Categorization' },
    { stage: 'Analysis', name: 'Analytics' },
    { stage: 'Control', name: 'Budgets' },
    { stage: 'Understanding', name: 'Insights' },
    { stage: 'Action', name: 'Suggestions' },
] as const;

export default function HiwBehindScenes() {
    return (
        <PublicSection id="behind-the-scenes" variant="muted" aria-labelledby="hiw-behind-heading">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-hiw-kicker">Behind the scenes</p>
                    <h2 id="hiw-behind-heading" className="cc-hiw-title">
                        What happens behind the scenes?
                    </h2>
                    <p className="cc-hiw-lede">
                        Campus Coin quietly organizes student financial activity into understanding — then into suggestions you can choose to act on.
                    </p>
                </div>

                <ol className="cc-hiw-pipeline">
                    {stages.map((item) => (
                        <li key={item.name} className="cc-hiw-pipe">
                            <span className="cc-hiw-pipe__stage">{item.stage}</span>
                            <span className="cc-hiw-pipe__name">{item.name}</span>
                        </li>
                    ))}
                </ol>
            </PublicContainer>
        </PublicSection>
    );
}
