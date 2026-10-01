import { cn } from '@/lib/utils';
import { useId } from 'react';

type VisualProps = {
    className?: string;
};

function AdminVisualShell({ className, children, variant }: { className?: string; children: React.ReactNode; variant: string }) {
    return (
        <svg
            viewBox="0 0 72 72"
            className={cn('cc-admin-visual size-14 shrink-0 overflow-visible', `cc-admin-visual--${variant}`, className)}
            aria-hidden="true"
            focusable="false"
            fill="none"
        >
            {children}
        </svg>
    );
}

function useGradIds(prefix: string) {
    const uid = useId().replace(/:/g, '');
    return {
        glow: `${prefix}-glow-${uid}`,
        primary: `${prefix}-p-${uid}`,
        secondary: `${prefix}-s-${uid}`,
        shine: `${prefix}-shine-${uid}`,
        dark: `${prefix}-dark-${uid}`,
    };
}

export function AdminControlCenterVisual({ className }: VisualProps) {
    const g = useGradIds('acc');
    return (
        <AdminVisualShell className={className} variant="control">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="45%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.45" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="10%" y1="0%" x2="90%" y2="100%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="50%" stopColor="#E94F09" />
                    <stop offset="100%" stopColor="#B83A06" />
                </linearGradient>
                <linearGradient id={g.secondary} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#3A3A3A" />
                    <stop offset="100%" stopColor="#1A1A1A" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="28" ry="24" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <rect x="14" y="18" width="44" height="36" rx="6" fill={`url(#${g.secondary})`} className="cc-admin-visual__base" />
            <rect x="18" y="22" width="18" height="12" rx="2" fill={`url(#${g.primary})`} className="cc-admin-visual__panel" />
            <rect x="40" y="22" width="14" height="5" rx="1.5" fill="#FF8B5C" opacity="0.85" className="cc-admin-visual__bar" />
            <rect x="40" y="30" width="14" height="4" rx="1.5" fill="#6B6B6B" className="cc-admin-visual__bar" />
            <rect x="18" y="38" width="36" height="4" rx="1.5" fill="#E94F09" opacity="0.7" className="cc-admin-visual__pulse" />
            <rect x="18" y="45" width="24" height="3" rx="1.5" fill="#4A4A4A" />
            <circle cx="50" cy="46" r="3.5" fill="#E94F09" className="cc-admin-visual__dot" />
        </AdminVisualShell>
    );
}

export function AdminUsersVisual({ className }: VisualProps) {
    const g = useGradIds('aus');
    return (
        <AdminVisualShell className={className} variant="users">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="48%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.4" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="20%" y1="10%" x2="80%" y2="90%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="26" ry="22" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <circle cx="28" cy="28" r="8" fill={`url(#${g.primary})`} className="cc-admin-visual__avatar" />
            <circle cx="46" cy="30" r="6.5" fill="#B83A06" className="cc-admin-visual__avatar-b" />
            <path d="M14 52c2-8 8-12 14-12s12 4 14 12" fill="#2A2A2A" stroke="#E94F09" strokeWidth="1.5" className="cc-admin-visual__body" />
            <path d="M38 50c1-6 5-9 10-9s8 3 9 9" fill="#1A1A1A" stroke="#FF8B5C" strokeWidth="1.2" opacity="0.85" />
        </AdminVisualShell>
    );
}

export function AdminCategoriesVisual({ className }: VisualProps) {
    const g = useGradIds('acat');
    return (
        <AdminVisualShell className={className} variant="categories">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="45%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.35" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="26" ry="22" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <rect x="16" y="20" width="22" height="16" rx="3" fill={`url(#${g.primary})`} className="cc-admin-visual__tile-a" />
            <rect x="34" y="28" width="22" height="16" rx="3" fill="#2A2A2A" stroke="#E94F09" strokeWidth="1.5" className="cc-admin-visual__tile-b" />
            <rect x="20" y="42" width="28" height="12" rx="3" fill="#1A1A1A" stroke="#FF8B5C" strokeWidth="1.2" className="cc-admin-visual__tile-c" />
        </AdminVisualShell>
    );
}

export function AdminAnnouncementsVisual({ className }: VisualProps) {
    const g = useGradIds('aan');
    return (
        <AdminVisualShell className={className} variant="announcements">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="45%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.4" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="20%" y1="10%" x2="80%" y2="90%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="26" ry="22" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <path
                d="M22 28c0-2 2-4 4-4h20c2 0 4 2 4 4v14c0 2-2 4-4 4H30l-8 6v-6c-2 0-4-2-4-4V28z"
                fill={`url(#${g.primary})`}
                className="cc-admin-visual__bubble"
            />
            <rect x="30" y="32" width="16" height="2.5" rx="1" fill="#FFF6F0" opacity="0.9" />
            <rect x="30" y="38" width="12" height="2" rx="1" fill="#FFF6F0" opacity="0.55" />
        </AdminVisualShell>
    );
}

export function AdminTipsVisual({ className }: VisualProps) {
    const g = useGradIds('atip');
    return (
        <AdminVisualShell className={className} variant="tips">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="40%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.45" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="30%" y1="5%" x2="70%" y2="95%">
                    <stop offset="0%" stopColor="#FFD0BB" />
                    <stop offset="40%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="36" rx="24" ry="22" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <path
                d="M36 16c-8 0-14 6-14 13 0 5 3 9 7 11v4h14v-4c4-2 7-6 7-11 0-7-6-13-14-13z"
                fill={`url(#${g.primary})`}
                className="cc-admin-visual__bulb"
            />
            <rect x="30" y="48" width="12" height="3" rx="1" fill="#B83A06" />
            <rect x="32" y="52" width="8" height="2" rx="1" fill="#7A2404" />
            <circle cx="36" cy="30" r="3" fill="#FFF6F0" opacity="0.7" className="cc-admin-visual__spark" />
        </AdminVisualShell>
    );
}

export function AdminAnalyticsVisual({ className }: VisualProps) {
    const g = useGradIds('aanl');
    return (
        <AdminVisualShell className={className} variant="analytics">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.35" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="0%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stopColor="#B83A06" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="40" rx="26" ry="20" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <rect x="16" y="40" width="8" height="16" rx="2" fill="#3A3A3A" className="cc-admin-visual__bar-1" />
            <rect x="28" y="32" width="8" height="24" rx="2" fill={`url(#${g.primary})`} className="cc-admin-visual__bar-2" />
            <rect x="40" y="24" width="8" height="32" rx="2" fill="#FF8B5C" className="cc-admin-visual__bar-3" />
            <rect x="52" y="36" width="8" height="20" rx="2" fill="#E94F09" opacity="0.75" className="cc-admin-visual__bar-4" />
            <path
                d="M18 30 L30 26 L42 18 L54 28"
                stroke="#FFD0BB"
                strokeWidth="1.8"
                strokeLinecap="round"
                fill="none"
                className="cc-admin-visual__trend"
            />
        </AdminVisualShell>
    );
}

export function AdminIntelligenceVisual({ className }: VisualProps) {
    const g = useGradIds('aint');
    return (
        <AdminVisualShell className={className} variant="intelligence">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="45%" r="55%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.4" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="20%" y1="10%" x2="80%" y2="90%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="26" ry="22" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <circle cx="36" cy="34" r="14" fill={`url(#${g.primary})`} className="cc-admin-visual__core" />
            <circle cx="36" cy="34" r="7" fill="#030303" opacity="0.35" />
            <path
                d="M36 20v-4M36 52v-4M22 34h-4M54 34h-4M25 23l-3-3M50 45l-3-3M25 45l-3 3M50 23l-3 3"
                stroke="#FFD0BB"
                strokeWidth="1.6"
                strokeLinecap="round"
                className="cc-admin-visual__rays"
            />
        </AdminVisualShell>
    );
}

export function AdminSystemClearVisual({ className }: VisualProps) {
    const g = useGradIds('asc');
    return (
        <AdminVisualShell className={className} variant="system-clear">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="48%" r="50%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.28" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="20%" y1="20%" x2="80%" y2="80%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="38" rx="24" ry="20" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <circle cx="36" cy="36" r="16" fill="#1A1A1A" stroke={`url(#${g.primary})`} strokeWidth="2.5" className="cc-admin-visual__ring" />
            <path
                d="M28 36l5 5 11-12"
                stroke="#FF8B5C"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="cc-admin-visual__check"
            />
        </AdminVisualShell>
    );
}

export function AdminNotificationsVisual({ className }: VisualProps) {
    const g = useGradIds('anot');
    return (
        <AdminVisualShell className={className} variant="notifications">
            <defs>
                <radialGradient id={g.glow} cx="50%" cy="42%" r="52%">
                    <stop offset="0%" stopColor="#E94F09" stopOpacity="0.32" />
                    <stop offset="100%" stopColor="#030303" stopOpacity="0" />
                </radialGradient>
                <linearGradient id={g.primary} x1="20%" y1="15%" x2="80%" y2="90%">
                    <stop offset="0%" stopColor="#FF8B5C" />
                    <stop offset="100%" stopColor="#E94F09" />
                </linearGradient>
            </defs>
            <ellipse cx="36" cy="40" rx="24" ry="18" fill={`url(#${g.glow})`} className="cc-admin-visual__glow" />
            <path
                d="M24 30a12 12 0 0 1 24 0v8l3 4H21l3-4v-8z"
                fill="#1A1A1A"
                stroke={`url(#${g.primary})`}
                strokeWidth="2.2"
                className="cc-admin-visual__bell"
            />
            <path d="M32 46a4 4 0 0 0 8 0" stroke="#FF8B5C" strokeWidth="2" strokeLinecap="round" className="cc-admin-visual__bell-base" />
            <circle cx="44" cy="22" r="5" fill="#E94F09" className="cc-admin-visual__notif-dot" />
            <circle cx="44" cy="22" r="8" fill="none" stroke="#FF8B5C" strokeOpacity="0.45" className="cc-admin-visual__notif-ring" />
        </AdminVisualShell>
    );
}
