import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Dashboard empty-state: premium CampusCoin 3D orange-metal coin.
 * SVG faces + CSS 3D tilt/float (no WebGL). Decorative only.
 */
export function EmptyStateCoinVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const faceGrad = `esc-face-${uid}`;
    const bevelGrad = `esc-bevel-${uid}`;
    const coreGrad = `esc-core-${uid}`;
    const shadeGrad = `esc-shade-${uid}`;
    const shineGrad = `esc-shine-${uid}`;
    const edgeGrad = `esc-edge-${uid}`;
    const backGrad = `esc-back-${uid}`;
    const dollarFill = `esc-dollar-${uid}`;
    const clipFront = `esc-clip-${uid}`;

    return (
        <div className={cn('cc-empty-coin', className)} aria-hidden="true">
            <div className="cc-empty-coin__glow" />
            <div className="cc-empty-coin__shadow" />

            <div className="cc-empty-coin__float">
                <div className="cc-empty-coin__pivot">
                    {/* Thickness / rim cylinder */}
                    <div
                        className="cc-empty-coin__edge"
                        style={{ background: 'linear-gradient(180deg, #FF8B5C 0%, #E94F09 35%, #B83A06 70%, #7A2404 100%)' }}
                    />

                    {/* Back face */}
                    <svg className="cc-empty-coin__face cc-empty-coin__face--back" viewBox="0 0 120 120" focusable="false">
                        <defs>
                            <radialGradient id={backGrad} cx="45%" cy="40%" r="65%">
                                <stop offset="0%" stopColor="#F46A2A" />
                                <stop offset="55%" stopColor="#E94F09" />
                                <stop offset="100%" stopColor="#7A2404" />
                            </radialGradient>
                        </defs>
                        <circle cx="60" cy="60" r="52" fill={`url(#${backGrad})`} />
                        <circle cx="60" cy="60" r="44" fill="none" stroke="rgba(26,11,5,0.45)" strokeWidth="2" />
                        <circle cx="60" cy="60" r="18" fill="rgba(26,11,5,0.25)" />
                    </svg>

                    {/* Front face */}
                    <svg className="cc-empty-coin__face cc-empty-coin__face--front" viewBox="0 0 120 120" focusable="false">
                        <defs>
                            <radialGradient id={faceGrad} cx="38%" cy="32%" r="70%">
                                <stop offset="0%" stopColor="#FFD0BB" />
                                <stop offset="22%" stopColor="#FF8B5C" />
                                <stop offset="48%" stopColor="#F46A2A" />
                                <stop offset="72%" stopColor="#E94F09" />
                                <stop offset="100%" stopColor="#9A3208" />
                            </radialGradient>
                            <linearGradient id={bevelGrad} x1="15%" y1="8%" x2="88%" y2="95%">
                                <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.55" />
                                <stop offset="35%" stopColor="#FFD0BB" stopOpacity="0.35" />
                                <stop offset="70%" stopColor="#E94F09" stopOpacity="0.2" />
                                <stop offset="100%" stopColor="#1A0B05" stopOpacity="0.55" />
                            </linearGradient>
                            <radialGradient id={coreGrad} cx="40%" cy="35%" r="60%">
                                <stop offset="0%" stopColor="#FFB088" />
                                <stop offset="45%" stopColor="#E94F09" />
                                <stop offset="100%" stopColor="#7A2404" />
                            </radialGradient>
                            <radialGradient id={shadeGrad} cx="75%" cy="80%" r="55%">
                                <stop offset="0%" stopColor="#1A0B05" stopOpacity="0.5" />
                                <stop offset="100%" stopColor="#E94F09" stopOpacity="0" />
                            </radialGradient>
                            <linearGradient id={shineGrad} x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0" />
                                <stop offset="45%" stopColor="#FFFFFF" stopOpacity="0.45" />
                                <stop offset="55%" stopColor="#FFD0BB" stopOpacity="0.25" />
                                <stop offset="100%" stopColor="#FFFFFF" stopOpacity="0" />
                            </linearGradient>
                            <linearGradient id={edgeGrad} x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stopColor="#FFD0BB" />
                                <stop offset="40%" stopColor="#E94F09" />
                                <stop offset="100%" stopColor="#5C1A04" />
                            </linearGradient>
                            <linearGradient id={dollarFill} x1="30%" y1="15%" x2="70%" y2="90%">
                                <stop offset="0%" stopColor="#FFFFFF" />
                                <stop offset="40%" stopColor="#FFF6F0" />
                                <stop offset="100%" stopColor="#FFD0BB" />
                            </linearGradient>
                            <clipPath id={clipFront}>
                                <circle cx="60" cy="60" r="52" />
                            </clipPath>
                        </defs>

                        {/* Soft drop under face */}
                        <ellipse cx="60" cy="108" rx="34" ry="5" fill="#030303" opacity="0.35" />

                        {/* Outer rim */}
                        <circle cx="60" cy="60" r="54" fill={`url(#${edgeGrad})`} />
                        <circle cx="60" cy="60" r="52" fill={`url(#${faceGrad})`} />
                        <circle cx="60" cy="60" r="52" fill={`url(#${shadeGrad})`} />

                        {/* Bevel ring */}
                        <circle cx="60" cy="60" r="48" fill="none" stroke={`url(#${bevelGrad})`} strokeWidth="2.4" />
                        <circle cx="60" cy="60" r="42" fill="none" stroke="rgba(255,208,187,0.22)" strokeWidth="1" />
                        <circle cx="60" cy="60" r="36" fill="none" stroke="rgba(26,11,5,0.18)" strokeWidth="1" />

                        {/* Inner well */}
                        <circle cx="60" cy="60" r="28" fill={`url(#${coreGrad})`} opacity="0.35" />

                        {/* Embossed $ — locked to coin face */}
                        <g className="cc-empty-coin__mark">
                            <text
                                x="61"
                                y="61.5"
                                textAnchor="middle"
                                dominantBaseline="central"
                                fill="rgba(26,11,5,0.4)"
                                fontSize="34"
                                fontWeight="700"
                                fontFamily="ui-sans-serif, system-ui, Segoe UI, Helvetica, Arial, sans-serif"
                            >
                                $
                            </text>
                            <text
                                x="60"
                                y="60"
                                textAnchor="middle"
                                dominantBaseline="central"
                                fill={`url(#${dollarFill})`}
                                fontSize="34"
                                fontWeight="700"
                                fontFamily="ui-sans-serif, system-ui, Segoe UI, Helvetica, Arial, sans-serif"
                            >
                                $
                            </text>
                            <text
                                x="59.3"
                                y="59"
                                textAnchor="middle"
                                dominantBaseline="central"
                                fill="rgba(255,208,187,0.55)"
                                fontSize="34"
                                fontWeight="700"
                                fontFamily="ui-sans-serif, system-ui, Segoe UI, Helvetica, Arial, sans-serif"
                            >
                                $
                            </text>
                        </g>

                        {/* Specular sweep (animated via CSS) */}
                        <g clipPath={`url(#${clipFront})`} className="cc-empty-coin__specular">
                            <rect x="-40" y="8" width="36" height="104" fill={`url(#${shineGrad})`} opacity="0.85" transform="rotate(-18 60 60)" />
                        </g>

                        {/* Top catchlight */}
                        <ellipse cx="44" cy="38" rx="14" ry="8" fill="rgba(255,255,255,0.22)" />
                    </svg>
                </div>
            </div>
        </div>
    );
}
