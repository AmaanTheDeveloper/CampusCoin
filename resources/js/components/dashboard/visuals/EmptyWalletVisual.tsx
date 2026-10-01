import { cn } from '@/lib/utils';
import { useId } from 'react';

type Props = {
    className?: string;
};

/**
 * Transactions empty-state: premium empty wallet (slightly open pocket).
 * SVG + CSS motion only. Decorative.
 */
export function EmptyWalletVisual({ className }: Props) {
    const uid = useId().replace(/:/g, '');
    const bodyGrad = `ew-body-${uid}`;
    const flapGrad = `ew-flap-${uid}`;
    const pocketGrad = `ew-pocket-${uid}`;
    const edgeGrad = `ew-edge-${uid}`;
    const shineGrad = `ew-shine-${uid}`;
    const claspGrad = `ew-clasp-${uid}`;
    const clipId = `ew-clip-${uid}`;

    return (
        <div className={cn('cc-empty-wallet', className)} aria-hidden="true">
            <div className="cc-empty-wallet__glow" />
            <div className="cc-empty-wallet__shadow" />

            <div className="cc-empty-wallet__float">
                <svg className="cc-empty-wallet__svg" viewBox="0 0 140 100" focusable="false">
                    <defs>
                        <linearGradient id={bodyGrad} x1="12%" y1="8%" x2="88%" y2="95%">
                            <stop offset="0%" stopColor="#2A1A12" />
                            <stop offset="35%" stopColor="#1A100C" />
                            <stop offset="70%" stopColor="#120C0A" />
                            <stop offset="100%" stopColor="#0A0706" />
                        </linearGradient>
                        <linearGradient id={flapGrad} x1="20%" y1="0%" x2="80%" y2="100%">
                            <stop offset="0%" stopColor="#3A2418" />
                            <stop offset="45%" stopColor="#1F140F" />
                            <stop offset="100%" stopColor="#140E0B" />
                        </linearGradient>
                        <linearGradient id={pocketGrad} x1="50%" y1="0%" x2="50%" y2="100%">
                            <stop offset="0%" stopColor="#080505" />
                            <stop offset="55%" stopColor="#12100E" />
                            <stop offset="100%" stopColor="#1A1210" />
                        </linearGradient>
                        <linearGradient id={edgeGrad} x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stopColor="#FF8B5C" stopOpacity="0.55" />
                            <stop offset="40%" stopColor="#E94F09" stopOpacity="0.75" />
                            <stop offset="100%" stopColor="#7A2404" stopOpacity="0.9" />
                        </linearGradient>
                        <linearGradient id={shineGrad} x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0" />
                            <stop offset="45%" stopColor="#FFFFFF" stopOpacity="0.28" />
                            <stop offset="55%" stopColor="#FFD0BB" stopOpacity="0.18" />
                            <stop offset="100%" stopColor="#FFFFFF" stopOpacity="0" />
                        </linearGradient>
                        <radialGradient id={claspGrad} cx="40%" cy="35%" r="65%">
                            <stop offset="0%" stopColor="#FFD0BB" />
                            <stop offset="45%" stopColor="#F46A2A" />
                            <stop offset="100%" stopColor="#E94F09" />
                        </radialGradient>
                        <clipPath id={clipId}>
                            <rect x="18" y="28" width="104" height="52" rx="10" />
                        </clipPath>
                    </defs>

                    {/* Soft ground shadow (reacts via CSS with float) */}
                    <ellipse className="cc-empty-wallet__ground" cx="70" cy="88" rx="42" ry="5" fill="#030303" opacity="0.45" />

                    {/* Wallet body */}
                    <g className="cc-empty-wallet__body">
                        <rect x="18" y="36" width="104" height="44" rx="10" fill={`url(#${bodyGrad})`} />
                        <rect x="18" y="36" width="104" height="44" rx="10" fill="none" stroke={`url(#${edgeGrad})`} strokeWidth="1.4" />
                        {/* Depth bevel */}
                        <path d="M28 40 H112" stroke="rgba(255,208,187,0.12)" strokeWidth="1" strokeLinecap="round" />
                        <path d="M26 74 H114" stroke="rgba(0,0,0,0.35)" strokeWidth="1.2" strokeLinecap="round" />

                        {/* Empty inner pocket */}
                        <rect x="28" y="44" width="84" height="26" rx="5" fill={`url(#${pocketGrad})`} />
                        <rect x="28" y="44" width="84" height="26" rx="5" fill="none" stroke="rgba(233,79,9,0.18)" strokeWidth="1" />
                        <path d="M36 52 H104" stroke="rgba(255,255,255,0.05)" strokeWidth="1" strokeLinecap="round" />
                        <path d="M40 58 H72" stroke="rgba(255,255,255,0.04)" strokeWidth="1" strokeLinecap="round" />
                    </g>

                    {/* Flap (subtle open motion) */}
                    <g className="cc-empty-wallet__flap" style={{ transformOrigin: '70px 36px' }}>
                        <path
                            d="M22 38 C22 28 34 22 70 22 C106 22 118 28 118 38 L112 42 C104 34 88 30 70 30 C52 30 36 34 28 42 Z"
                            fill={`url(#${flapGrad})`}
                        />
                        <path
                            d="M26 37 C34 30 48 27 70 27 C92 27 106 30 114 37"
                            fill="none"
                            stroke="rgba(255,208,187,0.16)"
                            strokeWidth="1.1"
                            strokeLinecap="round"
                        />
                        <path d="M30 39 C40 33 52 31 70 31 C88 31 100 33 110 39" fill="none" stroke="rgba(0,0,0,0.28)" strokeWidth="1" />
                        {/* Clasp */}
                        <circle cx="70" cy="34" r="5.2" fill={`url(#${claspGrad})`} />
                        <circle cx="70" cy="34" r="5.2" fill="none" stroke="rgba(255,208,187,0.45)" strokeWidth="0.8" />
                        <circle cx="68.5" cy="32.5" r="1.4" fill="rgba(255,255,255,0.35)" />
                    </g>

                    {/* Specular sweep */}
                    <g clipPath={`url(#${clipId})`} className="cc-empty-wallet__specular">
                        <rect x="0" y="28" width="28" height="56" fill={`url(#${shineGrad})`} transform="rotate(-12 70 50)" />
                    </g>
                </svg>
            </div>
        </div>
    );
}
