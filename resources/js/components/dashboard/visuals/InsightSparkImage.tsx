import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

/**
 * Behance four-point spark asset for Monthly Review / Intelligence headers only.
 * Local copy of: https://mir-s3-cdn-cf.behance.net/project_modules/1400/7cf51e188450701.65b6d6991d38d.png
 * Minimal CSS: blend dark plate into the page + slight hue shift toward CampusCoin orange.
 */
export function InsightSparkImage({ className }: Props) {
    return (
        <img
            src="/images/insight-spark.png"
            alt=""
            aria-hidden="true"
            draggable={false}
            className={cn('size-14 shrink-0 object-contain mix-blend-screen [filter:hue-rotate(155deg)_saturate(1.15)]', className)}
        />
    );
}
