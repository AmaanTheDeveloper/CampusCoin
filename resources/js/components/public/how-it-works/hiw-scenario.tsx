import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const scenes = [
    'Ali creates an account and opens an empty dashboard.',
    'Adds allowance income and logs campus expenses (and scans one receipt).',
    'Sets a monthly food budget and a laptop savings goal.',
    'Checks Money Pulse and month forecast mid-month.',
    'Acts on a spending alert and reviews the month.',
    'Sees savings progress move closer to the laptop.',
] as const;

export default function HiwScenario() {
    return (
        <PublicSection id="student-scenario" aria-labelledby="hiw-scenario-heading">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-hiw-kicker">Student scenario</p>
                    <h2 id="hiw-scenario-heading" className="cc-hiw-title">
                        A month on the real product path
                    </h2>
                    <p className="cc-hiw-lede">An illustrative story — not a real user's financial record.</p>
                </div>

                <ol className="cc-hiw-scenario">
                    {scenes.map((scene) => (
                        <li key={scene} className="cc-hiw-scene">
                            <p>{scene}</p>
                        </li>
                    ))}
                </ol>
            </PublicContainer>
        </PublicSection>
    );
}
