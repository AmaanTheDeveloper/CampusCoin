import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'info' | 'warning' | 'success' | 'danger' | 'accent';

const toneClass: Record<Tone, string> = {
    neutral: 'border-white/15 bg-white/5 text-[#d4d4d4]',
    info: 'border-sky-500/30 bg-sky-500/10 text-sky-200',
    warning: 'border-amber-500/35 bg-amber-500/10 text-amber-100',
    success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
    danger: 'border-red-500/35 bg-red-500/10 text-red-200',
    accent: 'border-[#E94F09]/40 bg-[#E94F09]/12 text-[#ffb089]',
};

function toneForStatus(status: string): Tone {
    const key = status.toLowerCase();

    if (['paid', 'completed', 'approved', 'accepted', 'succeeded', 'disbursed', 'active'].includes(key)) {
        return 'success';
    }
    if (['overdue', 'failed', 'rejected', 'cancelled', 'suspended'].includes(key)) {
        return 'danger';
    }
    if (['due', 'partially_paid', 'more_information_required', 'agreement_pending', 'ready_for_disbursement', 'pending'].includes(key)) {
        return 'warning';
    }
    if (['submitted', 'under_review', 'processing', 'initiated'].includes(key)) {
        return 'info';
    }
    if (['draft', 'upcoming', 'waived', 'uploaded'].includes(key)) {
        return 'neutral';
    }

    return 'accent';
}

type Props = {
    status: string;
    label: string;
    className?: string;
};

/** Accessible status chip — color is supplemental; label text always present. */
export function LoanStatusBadge({ status, label, className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase',
                toneClass[toneForStatus(status)],
                className,
            )}
        >
            {label}
        </span>
    );
}
