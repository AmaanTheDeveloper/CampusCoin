import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Animated recurring-schedule visual for Recurring Transactions.
 *
 * Sources:
 * - lucide-animated `calendar-days`
 *   https://lucide-animated.com/icons/calendar-days
 *   https://github.com/pqoqubbw/icons  (MIT © pqoqubbw)
 * - Cycle/orbit motion language adapted from cssvg-icons `refresh`
 *   https://github.com/Harijohnson/cssvg-icon  (MIT © Harikrishnan)
 *
 * Adaptation: CampusCoin #E94F09 / #030303 palette, layered depth,
 * orbital recurrence path + schedule-node pulse, CSS only (reduced-motion safe).
 */
export function RecurringVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const glowId = `rv-glow-${uid}`;
    const bodyId = `rv-body-${uid}`;
    const headerId = `rv-header-${uid}`;
    const shineId = `rv-shine-${uid}`;
    const orbitId = `rv-orbit-${uid}`;

    // Lucide calendar-days geometry (viewBox 0 0 24 24)
    const hangLeft = 'M8 2v4';
    const hangRight = 'M16 2v4';
    const dots = [
        { cx: 8, cy: 14 },
        { cx: 12, cy: 14 },
        { cx: 16, cy: 14 },
        { cx: 8, cy: 18 },
        { cx: 12, cy: 18 },
        { cx: 16, cy: 18 },
    ];

    return (
        <svg
            viewBox="0 0 72 72"
            className={cn('cc-recurring-visual size-14 shrink-0 overflow-visible', className)}
            aria-hidden="true"
            focusable="false"
            fill="none"
        >
            <defs>
                <radialGradient id={glowId} cx="50%" cy="48%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.42" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.1" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={bodyId} x1="15%" y1="10%" x2="90%" y2="95%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="45%" stopColor="#F46A2A" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
                <linearGradient id={headerId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FFD0BB" stopOpacity="0.55" />
                    <stop offset="40%" stopColor="#FF8B5C" stopOpacity="0.35" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0.15" />
                </linearGradient>
                <linearGradient id={shineId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.4" />
                    <stop offset="50%" stopColor="#FF8B5C" stopOpacity="0.12" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </linearGradient>
                <linearGradient id={orbitId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0" />
                    <stop offset="50%" stopColor="#FF8B5C" stopOpacity="0.85" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </linearGradient>
            </defs>

            <ellipse cx="36" cy="38" rx="30" ry="26" fill={`url(#${glowId})`} className="cc-recurring-visual__glow" />
            <ellipse cx="36" cy="58" rx="16" ry="3.2" fill="#030303" opacity="0.5" />

            {/* Orbital recurrence path */}
            <circle
                cx="36"
                cy="36"
                r="30"
                stroke={`url(#${orbitId})`}
                strokeWidth="1.25"
                strokeDasharray="8 10"
                className="cc-recurring-visual__orbit-ring"
                opacity="0.55"
            />

            {/* Calendar — Lucide calendar-days scaled into frame */}
            <g transform="translate(12 10) scale(2)">
                <g className="cc-recurring-visual__cal">
                    <rect x="3" y="4" width="18" height="18" rx="2" fill={`url(#${bodyId})`} stroke="rgba(255,255,255,0.16)" strokeWidth="1" />
                    <rect x="3" y="4" width="18" height="6" rx="2" fill={`url(#${headerId})`} className="cc-recurring-visual__header-shine" />
                    <rect x="3" y="8" width="18" height="2" fill="#030303" opacity="0.18" />
                    <path d={hangLeft} stroke="#FFD0BB" strokeWidth="1.6" strokeLinecap="round" />
                    <path d={hangRight} stroke="#FFD0BB" strokeWidth="1.6" strokeLinecap="round" />
                    <path d="M3 10h18" stroke="rgba(3,3,3,0.35)" strokeWidth="1.2" />
                    <rect x="3" y="4" width="18" height="18" rx="2" fill={`url(#${shineId})`} />

                    {dots.map((dot, i) => (
                        <circle
                            key={`${dot.cx}-${dot.cy}`}
                            cx={dot.cx}
                            cy={dot.cy}
                            r="1.05"
                            fill={i === 1 || i === 4 ? '#FFFFFF' : 'rgba(255,255,255,0.45)'}
                            className={i === 4 ? 'cc-recurring-visual__node' : undefined}
                            opacity={i === 1 || i === 4 ? 0.95 : 0.55}
                        />
                    ))}
                </g>
            </g>

            {/* Orbiting schedule indicator */}
            <g className="cc-recurring-visual__orbit" style={{ transformOrigin: '36px 36px' }}>
                <circle cx="36" cy="6" r="3.2" fill="#E94F09" stroke="#FFD0BB" strokeWidth="0.8" />
                <circle cx="36" cy="6" r="1.2" fill="#FFFFFF" opacity="0.85" />
            </g>
        </svg>
    );
}
