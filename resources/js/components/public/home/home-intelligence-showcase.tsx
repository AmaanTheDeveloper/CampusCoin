import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Activity, ArrowRight, Bell, CalendarCheck, ChartColumnIncreasing, Sparkles, TrendingUp } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

const CAPABILITIES = [
    {
        id: 'pulse',
        label: 'Money Pulse',
        title: 'A live read on your cash flow',
        body: 'See income, spending, and balance pressure in one place — the same pulse you open on the dashboard.',
        icon: Activity,
    },
    {
        id: 'forecast',
        label: 'Month Forecast',
        title: 'Look ahead before the month runs out',
        body: 'Project remaining days against recent patterns so campus costs do not surprise you.',
        icon: TrendingUp,
    },
    {
        id: 'reports',
        label: 'Reports',
        title: 'Clear breakdowns by category',
        body: 'Turn transactions into readable reports you can filter and review anytime.',
        icon: ChartColumnIncreasing,
    },
    {
        id: 'alerts',
        label: 'Spending Alerts',
        title: 'Notice unusual spending early',
        body: 'Get notified when activity looks off relative to your recent habits — not generic spam.',
        icon: Bell,
    },
    {
        id: 'review',
        label: 'Monthly Review',
        title: 'Close each month with clarity',
        body: 'A structured look back so you can adjust budgets and goals with real numbers.',
        icon: CalendarCheck,
    },
] as const;

type CapId = (typeof CAPABILITIES)[number]['id'];

function PreviewVisual({ active }: { active: CapId }) {
    const gradId = useId().replace(/:/g, '');

    return (
        <div className="cc-intel-preview" data-active={active} aria-hidden>
            <div className="cc-intel-preview__chrome">
                <span className="cc-intel-preview__dot" />
                <span className="cc-intel-preview__dot" />
                <span className="cc-intel-preview__dot" />
                <span className="cc-intel-preview__chrome-label">Intelligence · sample</span>
            </div>

            {active === 'pulse' ? (
                <div className="cc-intel-preview__body">
                    <p className="cc-intel-preview__kicker">Your Money Pulse</p>
                    <div className="cc-intel-preview__metrics">
                        <div>
                            <span>Inflow</span>
                            <strong className="cc-intel-preview__num">42.5k</strong>
                        </div>
                        <div>
                            <span>Outflow</span>
                            <strong className="cc-intel-preview__num">28.1k</strong>
                        </div>
                        <div>
                            <span>Available</span>
                            <strong className="cc-intel-preview__num cc-intel-preview__num--accent">14.4k</strong>
                        </div>
                    </div>
                    <svg className="cc-intel-preview__chart" viewBox="0 0 320 88" role="presentation">
                        <defs>
                            <linearGradient id={`pulse-fill-${gradId}`} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="#E94F09" stopOpacity="0.35" />
                                <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                            </linearGradient>
                        </defs>
                        <path
                            className="cc-intel-preview__area"
                            d="M0 70 C40 62, 70 48, 110 52 S170 30, 210 38 S270 18, 320 28 L320 88 L0 88 Z"
                            fill={`url(#pulse-fill-${gradId})`}
                        />
                        <path
                            className="cc-intel-preview__line"
                            d="M0 70 C40 62, 70 48, 110 52 S170 30, 210 38 S270 18, 320 28"
                            fill="none"
                            stroke="#E94F09"
                            strokeWidth="2.5"
                            strokeLinecap="round"
                        />
                    </svg>
                </div>
            ) : null}

            {active === 'forecast' ? (
                <div className="cc-intel-preview__body">
                    <p className="cc-intel-preview__kicker">Month Forecast</p>
                    <div className="cc-intel-preview__bars" role="presentation">
                        {[42, 58, 35, 72, 48, 64, 40].map((h, i) => (
                            <span key={i} className="cc-intel-preview__bar" style={{ ['--bar-h' as string]: `${h}%`, animationDelay: `${i * 60}ms` }} />
                        ))}
                    </div>
                    <p className="cc-intel-preview__caption">Projected spend vs recent weeks — illustrative only.</p>
                </div>
            ) : null}

            {active === 'reports' ? (
                <div className="cc-intel-preview__body">
                    <p className="cc-intel-preview__kicker">Reports</p>
                    <ul className="cc-intel-preview__rows">
                        {[
                            ['Food', '34%'],
                            ['Transport', '18%'],
                            ['Academics', '22%'],
                            ['Other', '26%'],
                        ].map(([label, value], i) => (
                            <li key={label} className="cc-intel-preview__row" style={{ animationDelay: `${i * 70}ms` }}>
                                <span>{label}</span>
                                <span className="tabular-nums">{value}</span>
                                <span className="cc-intel-preview__row-track">
                                    <span className="cc-intel-preview__row-fill" style={{ width: value }} />
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {active === 'alerts' ? (
                <div className="cc-intel-preview__body">
                    <p className="cc-intel-preview__kicker">Spending Alerts</p>
                    <div className="cc-intel-preview__alert">
                        <span className="cc-intel-preview__pulse" />
                        <div>
                            <p className="font-medium text-white">Food spending above recent average</p>
                            <p className="cc-text-secondary text-xs">Example signal — your real alerts use your own activity.</p>
                        </div>
                    </div>
                </div>
            ) : null}

            {active === 'review' ? (
                <div className="cc-intel-preview__body">
                    <p className="cc-intel-preview__kicker">Monthly Review</p>
                    <div className="cc-intel-preview__review">
                        <div>
                            <span>Spent</span>
                            <strong>31.2k</strong>
                        </div>
                        <div>
                            <span>Budgeted</span>
                            <strong>35.0k</strong>
                        </div>
                        <div>
                            <span>On track</span>
                            <strong className="text-emerald-300">Yes</strong>
                        </div>
                    </div>
                    <p className="cc-intel-preview__caption">Sample month-end summary — not your personal data.</p>
                </div>
            ) : null}
        </div>
    );
}

type Props = {
    /** Hide the section intro when embedded under a page hero. */
    compact?: boolean;
    guestPrimaryHref?: string;
    guestPrimaryLabel?: string;
};

export default function HomeIntelligenceShowcase({
    compact = false,
    guestPrimaryHref,
    guestPrimaryLabel,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAuthed = Boolean(auth?.user);
    const [active, setActive] = useState<CapId>('pulse');
    const activeCap = CAPABILITIES.find((c) => c.id === active) ?? CAPABILITIES[0];

    useEffect(() => {
        if (typeof window === 'undefined') return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const order = CAPABILITIES.map((c) => c.id);
        const timer = window.setInterval(() => {
            setActive((current) => {
                const idx = order.indexOf(current);
                return order[(idx + 1) % order.length];
            });
        }, 5200);

        return () => window.clearInterval(timer);
    }, []);

    const primaryHref = isAuthed
        ? route('intelligence.index')
        : (guestPrimaryHref ?? route('intelligence.preview'));
    const primaryLabel = isAuthed ? 'Open Intelligence' : (guestPrimaryLabel ?? 'Explore Intelligence');

    return (
        <PublicSection className="cc-home-ai !py-16 sm:!py-20" aria-labelledby="home-intelligence-heading">
            <PublicContainer>
                {!compact ? (
                    <div className="max-w-2xl">
                        <p className="cc-home-kicker">
                            <span className="inline-flex items-center gap-1.5">
                                <Sparkles className="size-3.5" aria-hidden />
                                Intelligence
                            </span>
                        </p>
                        <h2 id="home-intelligence-heading" className="cc-home-section-title">
                            Understand your money before it becomes a problem.
                        </h2>
                        <p className="cc-home-section-lede">
                            Transactions feed Intelligence — Money Pulse, forecasts, reports, alerts, and monthly review — so you can see pressure
                            early. Preview is public; personal insights require an account.
                        </p>
                    </div>
                ) : (
                    <h2 id="home-intelligence-heading" className="sr-only">
                        Intelligence product preview
                    </h2>
                )}

                <div className={cn('cc-intel-layout', !compact && 'mt-10')}>
                    <div className="cc-intel-tabs" role="tablist" aria-label="Intelligence capabilities">
                        {CAPABILITIES.map((cap) => {
                            const Icon = cap.icon;
                            const selected = active === cap.id;
                            return (
                                <button
                                    key={cap.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={selected}
                                    className={cn('cc-intel-tab', selected && 'is-active')}
                                    onClick={() => setActive(cap.id)}
                                >
                                    <Icon className="size-4 shrink-0" aria-hidden />
                                    <span>{cap.label}</span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="cc-intel-stage" role="tabpanel" aria-label={activeCap.label}>
                        <PreviewVisual active={active} />
                        <div className="cc-intel-copy">
                            <p className="cc-public-label">{activeCap.label}</p>
                            <h3 className="mt-2 text-xl font-semibold tracking-tight text-[var(--foreground)]">{activeCap.title}</h3>
                            <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{activeCap.body}</p>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Button asChild className="cc-dash-btn-primary min-h-11">
                                    <Link href={primaryHref}>
                                        {primaryLabel}
                                        <ArrowRight className="size-4" aria-hidden />
                                    </Link>
                                </Button>
                                {!isAuthed ? (
                                    <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11">
                                        <Link href={route('login')}>Log In</Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>

                <p className="cc-home-ai-disclaimer mt-6">
                    Illustrative preview only — sample figures, not live advice or your private financial data.
                </p>
            </PublicContainer>
        </PublicSection>
    );
}
