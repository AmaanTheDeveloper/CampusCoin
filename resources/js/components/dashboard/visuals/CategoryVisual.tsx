import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Animated category-organization visual for the Categories page.
 *
 * Source: adapted from lucide-animated `folders`
 *   https://lucide-animated.com/icons/folders
 *   https://github.com/pqoqubbw/icons
 * License: MIT © pqoqubbw
 *
 * Adaptation: CampusCoin #E94F09 / #030303 palette, layered fills/depth,
 * floating category tiles, CSS entrance + calm loop (reduced-motion safe).
 * No Motion/runtime animation dependency.
 */
export function CategoryVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const glowId = `cv-glow-${uid}`;
    const frontId = `cv-front-${uid}`;
    const backId = `cv-back-${uid}`;
    const tileA = `cv-tile-a-${uid}`;
    const tileB = `cv-tile-b-${uid}`;
    const shineId = `cv-shine-${uid}`;

    // Lucide folders paths (viewBox 0 0 24 24), rendered in a padded 0–72 frame via scale
    const frontFolder = 'M20 17a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3.9a2 2 0 0 1-1.69-.9l-.81-1.2a2 2 0 0 0-1.67-.9H8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2Z';
    const backEdge = 'M2 8v11a2 2 0 0 0 2 2h14';

    return (
        <svg
            viewBox="0 0 72 72"
            className={cn('cc-category-visual size-14 shrink-0 overflow-visible', className)}
            aria-hidden="true"
            focusable="false"
            fill="none"
        >
            <defs>
                <radialGradient id={glowId} cx="50%" cy="48%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.4" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.1" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={backId} x1="10%" y1="15%" x2="90%" y2="90%">
                    <stop offset="0%" stopColor="#B83A06" />
                    <stop offset="100%" stopColor="#7A2404" />
                </linearGradient>
                <linearGradient id={frontId} x1="15%" y1="10%" x2="85%" y2="95%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="40%" stopColor="#F46A2A" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
                <linearGradient id={tileA} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
                <linearGradient id={tileB} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#F46A2A" />
                    <stop offset="100%" stopColor="#B83A06" />
                </linearGradient>
                <linearGradient id={shineId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.45" />
                    <stop offset="55%" stopColor="#FF8B5C" stopOpacity="0.12" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </linearGradient>
            </defs>

            <ellipse cx="36" cy="38" rx="30" ry="26" fill={`url(#${glowId})`} className="cc-category-visual__glow" />
            <ellipse cx="36" cy="58" rx="18" ry="3.5" fill="#030303" opacity="0.5" />

            {/* Folder stack — Lucide folders geometry scaled into frame */}
            <g transform="translate(12 14) scale(2)">
                <g className="cc-category-visual__back">
                    <path d={backEdge} stroke={`url(#${backId})`} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                    <path
                        d={frontFolder}
                        fill={`url(#${backId})`}
                        stroke="rgba(255,255,255,0.08)"
                        strokeWidth="1"
                        transform="translate(-1.2 1.4)"
                        opacity="0.85"
                    />
                </g>
                <g className="cc-category-visual__front">
                    <path d={frontFolder} fill={`url(#${frontId})`} stroke="rgba(255,255,255,0.18)" strokeWidth="1" strokeLinejoin="round" />
                    <path d={frontFolder} fill={`url(#${shineId})`} />
                    <path
                        d="M8 10.2 h5.2"
                        stroke="rgba(255,255,255,0.35)"
                        strokeWidth="1.2"
                        strokeLinecap="round"
                        className="cc-category-visual__pulse"
                    />
                </g>
            </g>

            {/* Category tiles assembling into the collection */}
            <g className="cc-category-visual__tile cc-category-visual__tile--1">
                <rect x="46" y="18" width="12" height="9" rx="2" fill={`url(#${tileA})`} stroke="rgba(255,255,255,0.2)" strokeWidth="0.75" />
                <rect x="48" y="20.5" width="6" height="1.1" rx="0.5" fill="#FFFFFF" opacity="0.35" />
            </g>
            <g className="cc-category-visual__tile cc-category-visual__tile--2">
                <rect x="50" y="30" width="11" height="8" rx="2" fill={`url(#${tileB})`} stroke="rgba(255,255,255,0.15)" strokeWidth="0.75" />
                <rect x="52" y="32.2" width="5" height="1" rx="0.5" fill="#FFFFFF" opacity="0.28" />
            </g>
            <g className="cc-category-visual__tile cc-category-visual__tile--3">
                <rect x="44" y="40" width="10" height="7.5" rx="2" fill="#FF8B5C" opacity="0.85" stroke="rgba(255,255,255,0.18)" strokeWidth="0.75" />
                <rect x="46" y="42" width="4.5" height="1" rx="0.5" fill="#FFFFFF" opacity="0.3" />
            </g>
            <g className="cc-category-visual__tile cc-category-visual__tile--4">
                <rect x="14" y="42" width="9" height="7" rx="2" fill="#B0B0B0" opacity="0.35" stroke="rgba(255,255,255,0.12)" strokeWidth="0.75" />
            </g>
        </svg>
    );
}
