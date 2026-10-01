import { usePrefersReducedMotion } from '@/hooks/use-dashboard-motion';
import { cn } from '@/lib/utils';
import { type CSSProperties, type MouseEvent, type ReactNode, useCallback, useRef, useState } from 'react';

type Elevation = 'utility' | 'primary' | 'hero';

type Props = {
    children: ReactNode;
    className?: string;
    interactive?: boolean;
    /** Depth hierarchy — utility flat, primary default card, hero layered glow. */
    elevation?: Elevation;
    as?: 'div' | 'section' | 'article';
};

/**
 * Premium depth card with optional desktop parallax tilt (hero/primary only).
 */
export function FintechCard({ children, className, interactive = true, elevation = 'primary', as: Tag = 'div' }: Props) {
    const reduced = usePrefersReducedMotion();
    const ref = useRef<HTMLElement | null>(null);
    const [style, setStyle] = useState<CSSProperties>({});
    const allowTilt = interactive && elevation !== 'utility';

    const onMove = useCallback(
        (event: MouseEvent<HTMLElement>) => {
            if (reduced || !allowTilt || window.matchMedia('(pointer: coarse)').matches) {
                return;
            }
            const el = ref.current;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - 0.5;
            const y = (event.clientY - rect.top) / rect.height - 0.5;
            const intensity = elevation === 'hero' ? 2 : 1.15;
            const liftZ = elevation === 'hero' ? 8 : 4;
            setStyle({
                transform: `perspective(1000px) rotateX(${(-y * intensity).toFixed(2)}deg) rotateY(${(x * intensity).toFixed(2)}deg) translateY(-3px) translateZ(${liftZ}px)`,
            });
        },
        [allowTilt, elevation, reduced],
    );

    const onLeave = useCallback(() => setStyle({}), []);

    return (
        <Tag
            ref={ref as never}
            className={cn(
                elevation === 'hero' && 'cc-card-hero',
                elevation === 'utility' && 'cc-card-utility',
                elevation === 'primary' && 'cc-card',
                allowTilt && 'cc-card-interactive',
                className,
            )}
            style={style}
            onMouseMove={allowTilt ? onMove : undefined}
            onMouseLeave={allowTilt ? onLeave : undefined}
        >
            {children}
        </Tag>
    );
}
