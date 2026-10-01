import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * 3D isometric financial-forecast visual for Intelligence → Next Month Forecast.
 * Local CSS-animated SVG (no new dependencies). CampusCoin #E94F09 / #030303.
 */
export function NextMonthForecastVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const glowId = `nmf-glow-${uid}`;
    const deckId = `nmf-deck-${uid}`;
    const sideId = `nmf-side-${uid}`;
    const barId = `nmf-bar-${uid}`;
    const lineId = `nmf-line-${uid}`;

    return (
        <svg
            viewBox="0 0 96 80"
            className={cn('cc-nmf-visual size-12 shrink-0 overflow-visible', className)}
            aria-hidden="true"
            focusable="false"
            fill="none"
        >
            <defs>
                <radialGradient id={glowId} cx="50%" cy="55%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.38" />
                    <stop offset="60%" stopColor="#E94F09" stopOpacity="0.08" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={deckId} x1="10%" y1="0%" x2="90%" y2="100%">
                    <stop offset="0%" stopColor="#2A1A12" />
                    <stop offset="50%" stopColor="#15100C" />
                    <stop offset="100%" stopColor="#0A0806" />
                </linearGradient>
                <linearGradient id={sideId} x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#B83A06" />
                    <stop offset="100%" stopColor="#5C1E03" />
                </linearGradient>
                <linearGradient id={barId} x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="55%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#B83A06" />
                </linearGradient>
                <linearGradient id={lineId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#FF8B5C" stopOpacity="0.35" />
                    <stop offset="55%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#FFD0BB" />
                </linearGradient>
            </defs>

            <ellipse cx="48" cy="42" rx="40" ry="28" fill={`url(#${glowId})`} className="cc-nmf-visual__glow" />

            {/* Isometric deck (soft 3D platform) */}
            <g className="cc-nmf-visual__float">
                <path d="M18 48 L48 62 L78 48 L48 34 Z" fill={`url(#${deckId})`} stroke="rgba(255,255,255,0.1)" strokeWidth="0.8" />
                <path d="M18 48 L18 54 L48 68 L48 62 Z" fill={`url(#${sideId})`} opacity="0.85" />
                <path d="M78 48 L78 54 L48 68 L48 62 Z" fill="#7A2404" opacity="0.7" />
                <path d="M22 46 L48 58 L74 46" stroke="rgba(255,255,255,0.06)" strokeWidth="0.7" />

                {/* 3D bars */}
                <g className="cc-nmf-visual__bar cc-nmf-visual__bar--1">
                    <path d="M30 50 L36 53 L36 42 L30 39 Z" fill={`url(#${barId})`} opacity="0.75" />
                    <path d="M36 53 L40 51 L40 40 L36 42 Z" fill="#B83A06" opacity="0.8" />
                    <path d="M30 39 L36 42 L40 40 L34 37 Z" fill="#FF8B5C" opacity="0.9" />
                </g>
                <g className="cc-nmf-visual__bar cc-nmf-visual__bar--2">
                    <path d="M42 52 L48 55 L48 36 L42 33 Z" fill={`url(#${barId})`} />
                    <path d="M48 55 L52 53 L52 34 L48 36 Z" fill="#B83A06" opacity="0.85" />
                    <path d="M42 33 L48 36 L52 34 L46 31 Z" fill="#FFD0BB" opacity="0.85" />
                </g>
                <g className="cc-nmf-visual__bar cc-nmf-visual__bar--3">
                    <path d="M54 51 L60 54 L60 40 L54 37 Z" fill={`url(#${barId})`} opacity="0.85" />
                    <path d="M60 54 L64 52 L64 38 L60 40 Z" fill="#B83A06" opacity="0.8" />
                    <path d="M54 37 L60 40 L64 38 L58 35 Z" fill="#FF8B5C" opacity="0.9" />
                </g>

                {/* Forecast trend line (actual → projection) */}
                <path
                    d="M28 46 C36 40 42 34 48 36 C54 38 58 30 66 26"
                    stroke={`url(#${lineId})`}
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="cc-nmf-visual__line"
                    pathLength={100}
                />
                <path d="M48 36 C54 38 58 30 66 26" stroke="#E94F09" strokeWidth="1.6" strokeLinecap="round" strokeDasharray="3 3" opacity="0.75" />

                {/* Forecast endpoint */}
                <circle cx="66" cy="26" r="4.2" fill="#E94F09" className="cc-nmf-visual__node" />
                <circle cx="66" cy="26" r="8" fill="none" stroke="#E94F09" strokeOpacity="0.35" className="cc-nmf-visual__ring" />
                <circle cx="66" cy="26" r="1.6" fill="#FFD0BB" />
            </g>

            {/* Soft data particles */}
            <circle cx="24" cy="28" r="1.4" fill="#FF8B5C" opacity="0.55" className="cc-nmf-visual__particle cc-nmf-visual__particle--1" />
            <circle cx="72" cy="34" r="1.2" fill="#FFFFFF" opacity="0.35" className="cc-nmf-visual__particle cc-nmf-visual__particle--2" />
            <circle cx="58" cy="20" r="1.1" fill="#F46A2A" opacity="0.5" className="cc-nmf-visual__particle cc-nmf-visual__particle--3" />
        </svg>
    );
}
