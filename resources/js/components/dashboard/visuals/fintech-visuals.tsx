import { cn } from '@/lib/utils';

type Props = { className?: string; score?: number | null; indicator?: string };

export function MoneyPulseVisual({ className, score = null, indicator = 'neutral' }: Props) {
    const stroke = indicator === 'green' ? '#34d399' : indicator === 'amber' ? '#fbbf24' : indicator === 'red' ? '#f87171' : '#E94F09';
    const pct = score === null ? 0 : Math.max(0, Math.min(100, score));
    const radius = 40;
    const circ = 2 * Math.PI * radius;
    const offset = circ - (pct / 100) * circ;

    return (
        <svg viewBox="0 0 120 120" className={cn('size-28 shrink-0', className)} aria-hidden>
            <defs>
                <radialGradient id="mpGlow" cx="50%" cy="45%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.42" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.08" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </radialGradient>
                <linearGradient id="mpRing" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor={stroke} stopOpacity="1" />
                    <stop offset="100%" stopColor="#ff7a3d" stopOpacity="0.75" />
                </linearGradient>
            </defs>
            <circle cx="60" cy="60" r="56" fill="url(#mpGlow)" className="cc-svg-pulse" />
            <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="1" />
            <circle cx="60" cy="60" r="46" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="1.25" />
            <g className="cc-svg-orbit" style={{ transformOrigin: '60px 60px' }}>
                <circle cx="60" cy="10" r="2.8" fill="#E94F09" opacity="0.95" />
                <circle cx="60" cy="10" r="5.5" fill="none" stroke="#E94F09" strokeOpacity="0.25" strokeWidth="1" />
            </g>
            <circle cx="60" cy="60" r={radius} fill="none" stroke="rgba(255,255,255,0.07)" strokeWidth="8" />
            <circle
                cx="60"
                cy="60"
                r={radius}
                fill="none"
                stroke="url(#mpRing)"
                strokeWidth="8"
                strokeLinecap="round"
                strokeDasharray={circ}
                strokeDashoffset={offset}
                transform="rotate(-90 60 60)"
                className="cc-svg-glow"
            />
            <circle cx="60" cy="60" r="22" fill="rgba(3,3,3,0.72)" stroke="rgba(255,255,255,0.06)" strokeWidth="1" />
            {score !== null ? (
                <text x="60" y="64" textAnchor="middle" fill="#fff" fontSize="18" fontWeight="600" fontFamily="ui-sans-serif, system-ui, sans-serif">
                    {Math.round(pct)}
                </text>
            ) : (
                <>
                    <circle cx="60" cy="60" r="5.5" fill="#E94F09" />
                    <circle cx="60" cy="60" r="11" fill="none" stroke="#E94F09" strokeOpacity="0.3" strokeWidth="1.5" className="cc-svg-pulse" />
                </>
            )}
        </svg>
    );
}

export function ForecastVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 132 68" className={cn('h-14 w-32', className)} aria-hidden>
            <defs>
                <linearGradient id="fcStroke" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.35" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="1" />
                    <stop offset="100%" stopColor="#ff7a3d" stopOpacity="0.95" />
                </linearGradient>
            </defs>
            {/* baseline grid */}
            <path d="M6 52 H126" stroke="rgba(255,255,255,0.08)" strokeWidth="1" />
            <path d="M6 36 H126" stroke="rgba(255,255,255,0.04)" strokeWidth="1" strokeDasharray="3 5" />
            {/* actual (solid) */}
            <path
                d="M10 48 C26 48 30 28 48 26 C62 24 68 38 78 34"
                fill="none"
                stroke="url(#fcStroke)"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeDasharray="120"
                strokeDashoffset="120"
                className="cc-svg-path-draw"
            />
            {/* forecast projection (dashed) */}
            <path
                d="M78 34 C90 28 98 20 118 16"
                fill="none"
                stroke="#E94F09"
                strokeWidth="2"
                strokeLinecap="round"
                strokeDasharray="5 5"
                opacity="0.85"
                className="cc-svg-path-draw"
                style={{ animationDelay: '0.35s' }}
            />
            <circle cx="78" cy="34" r="3" fill="#fff" opacity="0.9" />
            <circle cx="118" cy="16" r="4.2" fill="#E94F09" className="cc-svg-glow" />
            <circle cx="118" cy="16" r="9" fill="none" stroke="#E94F09" strokeOpacity="0.35" className="cc-svg-pulse" />
        </svg>
    );
}

export function SavingsGoalVisual({ className, percent = 0 }: { className?: string; percent?: number }) {
    const pct = Math.max(0, Math.min(100, percent));
    const radius = 34;
    const circ = 2 * Math.PI * radius;
    const offset = circ - (pct / 100) * circ;

    return (
        <svg viewBox="0 0 100 100" className={cn('size-24 shrink-0', className)} aria-hidden>
            <defs>
                <linearGradient id="sgRing" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#c43f07" />
                    <stop offset="50%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#ff7a3d" />
                </linearGradient>
                <radialGradient id="sgCore" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.35" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </radialGradient>
            </defs>
            <circle cx="50" cy="50" r="46" fill="url(#sgCore)" />
            <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="1" />
            <circle cx="50" cy="50" r={radius} fill="none" stroke="rgba(255,255,255,0.08)" strokeWidth="7" />
            <circle
                cx="50"
                cy="50"
                r={radius}
                fill="none"
                stroke="url(#sgRing)"
                strokeWidth="7"
                strokeLinecap="round"
                strokeDasharray={circ}
                strokeDashoffset={offset}
                transform="rotate(-90 50 50)"
                className="cc-svg-glow"
            />
            <g className="cc-svg-orbit" style={{ transformOrigin: '50px 50px' }}>
                <circle cx="50" cy="16" r="2.4" fill="#ff7a3d" />
            </g>
            <circle cx="50" cy="50" r="12" fill="rgba(3,3,3,0.5)" stroke="rgba(255,255,255,0.08)" strokeWidth="1" />
            <circle cx="50" cy="50" r="3.5" fill="#E94F09" />
        </svg>
    );
}

export function SpendingAlertVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 72 72" className={cn('cc-soft-loop size-16 shrink-0', className)} aria-hidden>
            <circle cx="36" cy="36" r="30" fill="none" stroke="rgba(233,79,9,0.12)" strokeWidth="1" />
            <circle cx="36" cy="36" r="24" fill="none" stroke="rgba(233,79,9,0.22)" strokeWidth="1.4" className="cc-svg-signal" />
            <circle
                cx="36"
                cy="36"
                r="24"
                fill="none"
                stroke="rgba(233,79,9,0.4)"
                strokeWidth="1.4"
                className="cc-svg-signal"
                style={{ animationDelay: '1.1s' }}
            />
            <circle cx="36" cy="36" r="9" fill="#E94F09" opacity="0.95" />
            <circle cx="36" cy="36" r="4" fill="#fff" opacity="0.92" />
        </svg>
    );
}

export function MonthlyReviewVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 80 80" className={cn('size-16', className)} aria-hidden>
            <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,0.07)" strokeWidth="1" />
            <circle cx="40" cy="40" r="22" fill="none" stroke="rgba(233,79,9,0.28)" strokeWidth="1.2" strokeDasharray="3 7" />
            <circle cx="40" cy="40" r="7" fill="#E94F09" className="cc-svg-glow" />
            <circle cx="40" cy="40" r="12" fill="none" stroke="rgba(233,79,9,0.35)" strokeWidth="1" className="cc-svg-pulse" />
            <g className="cc-svg-orbit" style={{ transformOrigin: '40px 40px' }}>
                <circle cx="40" cy="12" r="2.6" fill="#ff7a3d" />
                <circle cx="64" cy="40" r="2" fill="rgba(255,255,255,0.55)" />
                <circle cx="40" cy="68" r="2.2" fill="rgba(255,122,61,0.75)" />
                <circle cx="16" cy="40" r="1.8" fill="rgba(255,255,255,0.4)" />
            </g>
        </svg>
    );
}

export function InsightVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 64 64" className={cn('size-12 shrink-0', className)} aria-hidden>
            <circle cx="32" cy="32" r="22" fill="none" stroke="rgba(233,79,9,0.2)" strokeWidth="1.25" className="cc-svg-pulse" />
            <circle cx="32" cy="32" r="6" fill="#E94F09" className="cc-svg-glow" />
            <circle cx="32" cy="14" r="2.2" fill="#ff7a3d" opacity="0.9" />
            <circle cx="48" cy="28" r="1.8" fill="rgba(255,255,255,0.55)" />
            <circle cx="44" cy="46" r="2" fill="rgba(255,122,61,0.7)" />
            <circle cx="18" cy="40" r="1.7" fill="rgba(255,255,255,0.4)" />
            <path d="M32 18 L32 26 M46 32 L38 32 M32 46 L32 38 M18 32 L26 32" stroke="rgba(233,79,9,0.45)" strokeWidth="1.2" strokeLinecap="round" />
        </svg>
    );
}

export function EmptyOrbitVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 120 80" className={cn('mx-auto h-20 w-32', className)} aria-hidden>
            <ellipse cx="60" cy="40" rx="46" ry="18" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="1.2" />
            <ellipse cx="60" cy="40" rx="28" ry="10" fill="none" stroke="rgba(233,79,9,0.18)" strokeWidth="1" />
            <g className="cc-svg-orbit" style={{ transformOrigin: '60px 40px' }}>
                <circle cx="106" cy="40" r="3.2" fill="#E94F09" />
            </g>
            <circle cx="60" cy="40" r="9" fill="rgba(233,79,9,0.2)" />
            <circle cx="60" cy="40" r="4.5" fill="#E94F09" />
        </svg>
    );
}

export function DuplicateReviewVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 72 72" className={cn('size-14 shrink-0', className)} aria-hidden>
            <rect x="18" y="14" width="28" height="36" rx="4" fill="none" stroke="rgba(255,255,255,0.2)" strokeWidth="1.5" />
            <rect x="26" y="22" width="28" height="36" rx="4" fill="rgba(10,10,10,0.85)" stroke="#E94F09" strokeWidth="1.5" className="cc-svg-glow" />
            <path d="M32 34 h16 M32 40 h12 M32 46 h14" stroke="rgba(255,255,255,0.35)" strokeWidth="1.5" strokeLinecap="round" />
            <circle cx="48" cy="54" r="8" fill="#E94F09" className="cc-svg-pulse" />
            <path d="M45 54 l2.5 2.5 5-5" fill="none" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ClearSignalVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 72 72" className={cn('size-16 shrink-0', className)} aria-hidden>
            <circle cx="36" cy="36" r="26" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="1.5" />
            <circle cx="36" cy="36" r="16" fill="none" stroke="rgba(233,79,9,0.25)" strokeWidth="1.5" className="cc-svg-pulse" />
            <circle cx="36" cy="36" r="6" fill="#E94F09" opacity="0.85" />
            <path d="M28 36 l5 5 11-12" fill="none" stroke="#fff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function SuccessVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 48 48" className={cn('size-8 shrink-0', className)} aria-hidden>
            <circle cx="24" cy="24" r="18" fill="none" stroke="rgba(233,79,9,0.35)" strokeWidth="1.5" className="cc-svg-pulse" />
            <circle cx="24" cy="24" r="12" fill="#E94F09" />
            <path d="M17 24 l5 5 9-10" fill="none" stroke="#fff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function WarningVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 48 48" className={cn('size-8 shrink-0', className)} aria-hidden>
            <circle cx="24" cy="24" r="18" fill="none" stroke="rgba(233,79,9,0.3)" strokeWidth="1.5" className="cc-svg-signal" />
            <circle cx="24" cy="24" r="8" fill="#E94F09" opacity="0.9" />
            <circle cx="24" cy="24" r="3" fill="#fff" />
        </svg>
    );
}

export function BudgetRingVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 72 72" className={cn('size-14 shrink-0', className)} aria-hidden>
            <circle cx="36" cy="36" r="26" fill="none" stroke="rgba(255,255,255,0.08)" strokeWidth="6" />
            <circle
                cx="36"
                cy="36"
                r="26"
                fill="none"
                stroke="#E94F09"
                strokeWidth="6"
                strokeLinecap="round"
                strokeDasharray="120"
                strokeDashoffset="40"
                transform="rotate(-90 36 36)"
                className="cc-svg-glow"
            />
            <rect x="28" y="28" width="16" height="16" rx="3" fill="none" stroke="rgba(255,255,255,0.35)" strokeWidth="1.5" />
        </svg>
    );
}

export function ChartEmptyVisual({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 120 64" className={cn('mx-auto h-16 w-28', className)} aria-hidden>
            <path d="M10 48 h100" stroke="rgba(255,255,255,0.12)" strokeWidth="1" />
            <rect x="18" y="28" width="14" height="20" rx="2" fill="rgba(255,255,255,0.08)" />
            <rect x="40" y="18" width="14" height="30" rx="2" fill="rgba(233,79,9,0.35)" className="cc-svg-glow" />
            <rect x="62" y="24" width="14" height="24" rx="2" fill="rgba(255,255,255,0.12)" />
            <rect x="84" y="14" width="14" height="34" rx="2" fill="rgba(233,79,9,0.55)" />
        </svg>
    );
}
