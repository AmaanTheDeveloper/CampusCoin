import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { LayoutGrid, Sparkles } from 'lucide-react';

export default function HiwStepAddMoney() {
    return (
        <PublicSection id="step-start" aria-labelledby="hiw-step-01">
            <PublicContainer>
                <div className="cc-hiw-split">
                    <div>
                        <p className="cc-hiw-kicker">01 — Start</p>
                        <h2 id="hiw-step-01" className="cc-hiw-title">
                            Create an account and open your dashboard
                        </h2>
                        <p className="cc-hiw-lede">
                            After you register and verify email, you land on an empty financial command center — ready for your first income, expense,
                            Smart Scan, or CSV import.
                        </p>
                    </div>

                    <div className="cc-hiw-panel" aria-label="Example empty dashboard callouts">
                        <div className="cc-hiw-panel__body">
                            <p className="cc-hiw-kicker !mb-3">Empty dashboard · Example</p>
                            <div className="space-y-3">
                                <div className="flex items-start gap-3 rounded-[var(--radius)] border border-white/10 bg-white/5 p-3">
                                    <LayoutGrid className="text-primary mt-0.5 size-5 shrink-0" aria-hidden />
                                    <div>
                                        <p className="text-sm font-semibold">Your financial command center</p>
                                        <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                            Add income or expenses to unlock Money Pulse, forecasts, goals, and alerts.
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {['Add income', 'Add expense', 'Smart Scan', 'Import CSV'].map((label) => (
                                        <span
                                            key={label}
                                            className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium"
                                        >
                                            <Sparkles className="size-3 text-[#e94f09]" aria-hidden />
                                            {label}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <p className="cc-hiw-note mt-4">Illustrative empty state — mirrors the real student dashboard.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}
