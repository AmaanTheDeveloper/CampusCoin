import { useEffect, useState } from 'react';

export function usePrefersReducedMotion(): boolean {
    const [reduced, setReduced] = useState(false);

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => setReduced(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    return reduced;
}

/**
 * Entrance count-up for display. Does not alter the source financial value.
 */
export function useCountUp(target: number, durationMs = 800, enabled = true): number {
    const reduced = usePrefersReducedMotion();
    const [value, setValue] = useState(reduced || !enabled ? target : 0);

    useEffect(() => {
        if (reduced || !enabled) {
            setValue(target);
            return;
        }

        let frame = 0;
        const start = performance.now();
        const from = 0;

        const tick = (now: number) => {
            const t = Math.min(1, (now - start) / durationMs);
            const eased = 1 - Math.pow(1 - t, 3);
            setValue(from + (target - from) * eased);
            if (t < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [target, durationMs, enabled, reduced]);

    return value;
}
