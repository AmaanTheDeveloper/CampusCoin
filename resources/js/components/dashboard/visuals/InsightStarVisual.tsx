import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
    /** Slightly different loop timing for Review vs Insight */
    variant?: 'insight' | 'review';
};

/**
 * Glossy four-point “insight spark” based on the Behance reference silhouette,
 * recolored to CampusCoin orange (#E94F09) on dark.
 */
export function InsightStarVisual({ className, variant = 'insight' }: Props) {
    const uid = useId().replace(/:/g, '');
    const glowId = `is-glow-${uid}`;
    const bodyId = `is-body-${uid}`;
    const shadeId = `is-shade-${uid}`;
    const shineId = `is-shine-${uid}`;
    const softBlurId = `is-blur-${uid}`;

    // Astroid-like four-cusp path: sharp N/E/S/W tips, deep concave sides
    const starPath = 'M64 6C66.2 38.5 89.5 61.8 122 64C89.5 66.2 66.2 89.5 64 122C61.8 89.5 38.5 66.2 6 64C38.5 61.8 61.8 38.5 64 6Z';

    return (
        <svg
            viewBox="0 0 128 128"
            className={cn(
                'cc-insight-star shrink-0 overflow-visible',
                variant === 'review' ? 'cc-insight-star--review size-12 md:size-14 lg:size-16' : 'size-12',
                className,
            )}
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <radialGradient id={glowId} cx="50%" cy="45%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.55" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.12" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>

                <linearGradient id={bodyId} x1="18%" y1="12%" x2="88%" y2="92%">
                    <stop offset="0%" stopColor="#FFD0BB" />
                    <stop offset="22%" stopColor="#FF915F" />
                    <stop offset="52%" stopColor="#F46A2A" />
                    <stop offset="78%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#B83A06" />
                </linearGradient>

                <radialGradient id={shadeId} cx="32%" cy="68%" r="62%">
                    <stop offset="0%" stopColor="#7A2404" stopOpacity="0.55" />
                    <stop offset="55%" stopColor="#E94F09" stopOpacity="0.12" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </radialGradient>

                <radialGradient id={shineId} cx="68%" cy="28%" r="42%">
                    <stop offset="0%" stopColor="#FFF6F0" stopOpacity="0.95" />
                    <stop offset="35%" stopColor="#FFD0BB" stopOpacity="0.65" />
                    <stop offset="70%" stopColor="#FF915F" stopOpacity="0.15" />
                    <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                </radialGradient>

                <filter id={softBlurId} x="-35%" y="-35%" width="170%" height="170%">
                    <feGaussianBlur stdDeviation="4.5" />
                </filter>
            </defs>

            {/* Soft atmospheric glow */}
            <circle cx="64" cy="64" r="52" fill={`url(#${glowId})`} className="cc-insight-star__glow" />

            {/* Depth shadow (slight offset) */}
            <path d={starPath} fill="#030303" opacity="0.45" transform="translate(1.5 2.5)" filter={`url(#${softBlurId})`} />

            {/* Main glossy body */}
            <g className="cc-insight-star__body">
                <path d={starPath} fill={`url(#${bodyId})`} />
                <path d={starPath} fill={`url(#${shadeId})`} />
                <path d={starPath} fill={`url(#${shineId})`} className="cc-insight-star__shine" />
                {/* Crisp rim catchlight */}
                <path d={starPath} fill="none" stroke="rgba(255,208,187,0.28)" strokeWidth="0.75" vectorEffect="non-scaling-stroke" />
            </g>
        </svg>
    );
}
