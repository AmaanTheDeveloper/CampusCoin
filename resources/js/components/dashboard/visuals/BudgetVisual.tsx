import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Animated budget meter visual for the Budgets page.
 *
 * Source: adapted from cssvg-icons `gauge`
 *   https://github.com/Harijohnson/cssvg-icon (package: cssvg-icons)
 * License: MIT © Harikrishnan
 *
 * Adaptation: CampusCoin #E94F09 / #030303 palette, layered depth,
 * progress-arc draw + glow + highlight sweep, CSS motion (reduced-motion safe).
 */
export function BudgetVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const trackId = `bv-track-${uid}`;
    const arcId = `bv-arc-${uid}`;
    const glowId = `bv-glow-${uid}`;
    const hubId = `bv-hub-${uid}`;
    const shineId = `bv-shine-${uid}`;

    // Semi-circle gauge path (viewBox 0 0 72 72), inspired by CSSVG gauge arc
    const arcPath = 'M14 48 A22 22 0 1 1 58 48';

    return (
        <svg
            viewBox="0 0 72 72"
            className={cn('cc-budget-visual size-14 shrink-0 overflow-visible', className)}
            aria-hidden="true"
            focusable="false"
            fill="none"
        >
            <defs>
                <radialGradient id={glowId} cx="50%" cy="55%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.42" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.1" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={arcId} x1="14" y1="30" x2="58" y2="48" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="45%" stopColor="#F46A2A" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
                <linearGradient id={trackId} x1="14" y1="48" x2="58" y2="30" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.08" />
                    <stop offset="100%" stopColor="#B0B0B0" stopOpacity="0.18" />
                </linearGradient>
                <radialGradient id={hubId} cx="40%" cy="35%" r="65%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="55%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#B83A06" />
                </radialGradient>
                <linearGradient id={shineId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.55" />
                    <stop offset="40%" stopColor="#FF8B5C" stopOpacity="0.15" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </linearGradient>
            </defs>

            {/* Soft atmospheric glow */}
            <ellipse cx="36" cy="40" rx="30" ry="26" fill={`url(#${glowId})`} className="cc-budget-visual__glow" />

            {/* Soft ground shadow */}
            <ellipse cx="36" cy="58" rx="16" ry="3.5" fill="#030303" opacity="0.55" />

            {/* Track */}
            <path d={arcPath} stroke={`url(#${trackId})`} strokeWidth="6" strokeLinecap="round" />

            {/* Progress arc */}
            <path d={arcPath} stroke={`url(#${arcId})`} strokeWidth="6" strokeLinecap="round" className="cc-budget-visual__arc" pathLength={100} />

            {/* Highlight sweep along arc */}
            <path
                d={arcPath}
                stroke={`url(#${shineId})`}
                strokeWidth="2.5"
                strokeLinecap="round"
                className="cc-budget-visual__shine"
                pathLength={100}
            />

            {/* Needle (subtle sweep) — geometry adapted from CSSVG gauge */}
            <g className="cc-budget-visual__needle" style={{ transformOrigin: '36px 48px' }}>
                <line x1="36" y1="48" x2="36" y2="24" stroke="#FF8B5C" strokeWidth="2.25" strokeLinecap="round" />
                <line x1="36" y1="48" x2="36" y2="28" stroke="#FFFFFF" strokeWidth="0.9" strokeLinecap="round" opacity="0.35" />
            </g>

            {/* Hub */}
            <circle cx="36" cy="48" r="6.5" fill={`url(#${hubId})`} stroke="rgba(255,255,255,0.2)" strokeWidth="1" />
            <circle cx="34.5" cy="46.5" r="2" fill="#FFFFFF" opacity="0.35" />
            <circle cx="36" cy="48" r="2.2" fill="#030303" opacity="0.35" />
        </svg>
    );
}
