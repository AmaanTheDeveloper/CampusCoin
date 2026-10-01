import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Animated financial report / analytics visual for the Reports page.
 *
 * Source: adapted from lucide-animated `file-chart-line`
 *   https://lucide-animated.com/icons/file-chart-line
 *   https://github.com/pqoqubbw/icons
 * License: MIT © pqoqubbw
 *
 * Adaptation: CampusCoin #E94F09 / #030303 palette, layered document depth,
 * entrance chart-path draw + data-node pulse + edge highlight (CSS only).
 * Chart line draws once on entrance; loop is restrained (no continuous redraw).
 */
export function ReportVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const glowId = `rp-glow-${uid}`;
    const sheetId = `rp-sheet-${uid}`;
    const foldId = `rp-fold-${uid}`;
    const lineId = `rp-line-${uid}`;
    const shineId = `rp-shine-${uid}`;

    // Lucide file-chart-line geometry (viewBox 0 0 24 24)
    const sheet = 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z';
    const fold = 'M14 2v4a2 2 0 0 0 2 2h4';
    const chart = 'm8 17 2.5-2.5 2 2L16 13';

    return (
        <svg
            viewBox="0 0 72 72"
            className={cn('cc-report-visual size-14 shrink-0 overflow-visible', className)}
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
                <linearGradient id={sheetId} x1="18%" y1="8%" x2="85%" y2="95%">
                    <stop offset="0%" stopColor="#2A1810" />
                    <stop offset="40%" stopColor="#1A100C" />
                    <stop offset="100%" stopColor="#0B0806" />
                </linearGradient>
                <linearGradient id={foldId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FF8B5C" stopOpacity="0.45" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0.2" />
                </linearGradient>
                <linearGradient id={lineId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="55%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#F46A2A" />
                </linearGradient>
                <linearGradient id={shineId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FFD0BB" stopOpacity="0.35" />
                    <stop offset="45%" stopColor="#FF8B5C" stopOpacity="0.08" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </linearGradient>
            </defs>

            <ellipse cx="36" cy="38" rx="29" ry="25" fill={`url(#${glowId})`} className="cc-report-visual__glow" />
            <ellipse cx="36" cy="58" rx="15" ry="3" fill="#030303" opacity="0.5" />

            {/* Depth offset sheet */}
            <g transform="translate(13 11) scale(2)" opacity="0.55">
                <path d={sheet} fill="#7A2404" transform="translate(0.8 1.1)" />
            </g>

            {/* Main report document — Lucide file-chart-line */}
            <g transform="translate(12 10) scale(2)" className="cc-report-visual__sheet">
                <path d={sheet} fill={`url(#${sheetId})`} stroke="rgba(255,255,255,0.14)" strokeWidth="1" strokeLinejoin="round" />
                <path d={fold} fill={`url(#${foldId})`} stroke="rgba(255,208,187,0.35)" strokeWidth="1" strokeLinejoin="round" />
                <path d={sheet} fill={`url(#${shineId})`} />

                {/* Soft header rule */}
                <path d="M7 9.2 h8" stroke="rgba(255,255,255,0.12)" strokeWidth="1" strokeLinecap="round" className="cc-report-visual__edge" />

                {/* Secondary muted bars */}
                <rect x="7" y="10.5" width="3.2" height="5.2" rx="0.6" fill="#B0B0B0" opacity="0.22" />
                <rect x="11" y="11.8" width="3.2" height="3.9" rx="0.6" fill="#B0B0B0" opacity="0.16" />

                {/* Chart line — entrance draw only */}
                <path
                    d={chart}
                    stroke={`url(#${lineId})`}
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="cc-report-visual__line"
                    pathLength={100}
                />

                {/* Data nodes */}
                <circle cx="8" cy="17" r="1.15" fill="#FF8B5C" className="cc-report-visual__dot" />
                <circle cx="10.5" cy="14.5" r="1.15" fill="#F46A2A" className="cc-report-visual__dot" />
                <circle cx="12.5" cy="16.5" r="1.15" fill="#E94F09" className="cc-report-visual__dot" />
                <circle cx="16" cy="13" r="1.35" fill="#FFD0BB" stroke="#E94F09" strokeWidth="0.6" className="cc-report-visual__dot-pulse" />
            </g>
        </svg>
    );
}
