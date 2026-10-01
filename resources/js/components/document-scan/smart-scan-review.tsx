import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { TransactionTypeValue } from '@/types';
import { CheckCircle2 } from 'lucide-react';
import { useMemo } from 'react';
import type { ScanCategoryOption, ScanExtraction } from './types';

export type ReviewFormState = {
    type: TransactionTypeValue;
    category_id: string;
    amount: string;
    description: string;
    transaction_date: string;
};

type Props = {
    extraction: ScanExtraction;
    categories: ScanCategoryOption[];
    today: string;
    form: ReviewFormState;
    onChange: (next: ReviewFormState) => void;
    onSubmit: () => void;
    onCancel: () => void;
    submitting: boolean;
    error: string | null;
    duplicateWarning: string | null;
    budgetWarning: string | null;
};

export function SmartScanReview({
    extraction,
    categories,
    today,
    form,
    onChange,
    onSubmit,
    onCancel,
    submitting,
    error,
    duplicateWarning,
    budgetWarning,
}: Props) {
    const filteredCategories = useMemo(() => categories.filter((c) => c.type === form.type), [categories, form.type]);

    const needs = new Set(extraction.needs_review ?? []);
    const confidencePct = Math.round((extraction.confidence ?? 0) * 100);

    if (extraction.document_type === 'card') {
        const kindLabel = extraction.card_kind === 'debit' ? 'Debit card' : extraction.card_kind === 'credit' ? 'Credit card' : 'Payment card';

        return (
            <div className="flex flex-col gap-4">
                <div className="flex items-start gap-3 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-3 py-3">
                    <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-400" aria-hidden />
                    <div>
                        <p className="text-sm font-medium text-white">Card detected successfully</p>
                        <p className="mt-1 text-xs font-medium text-emerald-300">✓ Card verified</p>
                        <p className="cc-text-secondary mt-1 text-xs">
                            Cards are not saved as transactions. Only masked details are shown — never enter a full card number or CVV.
                        </p>
                    </div>
                </div>

                <div>
                    <p className="cc-label">Card detected</p>
                    <p className="mt-1 text-lg font-semibold text-white">{kindLabel}</p>
                    {extraction.card_brand ? <p className="cc-text-secondary mt-0.5 text-sm capitalize">{extraction.card_brand}</p> : null}
                </div>

                <dl className="grid gap-3 text-sm">
                    {extraction.cardholder_name ? (
                        <div className="flex justify-between gap-3 border-b border-white/5 pb-2">
                            <dt className="cc-text-secondary">Cardholder</dt>
                            <dd className="text-right text-white">{extraction.cardholder_name}</dd>
                        </div>
                    ) : null}
                    <div className="flex justify-between gap-3 border-b border-white/5 pb-2">
                        <dt className="cc-text-secondary">Card number</dt>
                        <dd className="font-mono tracking-wider text-white">•••• •••• •••• {extraction.card_last4 ?? '····'}</dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt className="cc-text-secondary">Expiry</dt>
                        <dd className="font-mono text-white">{extraction.card_expiry_masked ?? '••/••'}</dd>
                    </div>
                </dl>

                <div className="flex justify-end">
                    <Button type="button" className="cc-dash-btn-primary" onClick={onCancel}>
                        Done
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit();
            }}
        >
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                <span className="cc-text-secondary capitalize">Document detected — {extraction.document_type}</span>
                <span
                    className={cn(
                        'rounded-md px-2 py-0.5',
                        confidencePct >= 70 ? 'bg-emerald-500/15 text-emerald-200' : 'bg-amber-500/15 text-amber-100',
                    )}
                >
                    Confidence {confidencePct}%
                </span>
            </div>

            {needs.size > 0 ? (
                <p className="text-xs text-amber-200/90">Review highlighted fields — extraction was uncertain for: {[...needs].join(', ')}.</p>
            ) : null}

            {duplicateWarning ? (
                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">{duplicateWarning}</p>
            ) : null}
            {budgetWarning ? (
                <p className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">{budgetWarning}</p>
            ) : null}
            {error ? <p className="text-sm text-red-300">{error}</p> : null}

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor="smart-scan-type">Type</Label>
                    <select
                        id="smart-scan-type"
                        className="border-input bg-background flex h-9 w-full rounded-md border px-3 text-sm"
                        value={form.type}
                        onChange={(e) =>
                            onChange({
                                ...form,
                                type: e.target.value as TransactionTypeValue,
                                category_id: '',
                            })
                        }
                    >
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="smart-scan-category" className={needs.has('category') ? 'text-amber-200' : undefined}>
                        Category
                    </Label>
                    <select
                        id="smart-scan-category"
                        required
                        className="border-input bg-background flex h-9 w-full rounded-md border px-3 text-sm"
                        value={form.category_id}
                        onChange={(e) => onChange({ ...form, category_id: e.target.value })}
                    >
                        <option value="">Select category</option>
                        {filteredCategories.map((c) => (
                            <option key={c.id} value={String(c.id)}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor="smart-scan-amount" className={needs.has('amount') ? 'text-amber-200' : undefined}>
                        Amount
                    </Label>
                    <Input
                        id="smart-scan-amount"
                        inputMode="decimal"
                        required
                        value={form.amount}
                        onChange={(e) => onChange({ ...form, amount: e.target.value })}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="smart-scan-date" className={needs.has('date') ? 'text-amber-200' : undefined}>
                        Date
                    </Label>
                    <Input
                        id="smart-scan-date"
                        type="date"
                        required
                        max={today}
                        value={form.transaction_date}
                        onChange={(e) => onChange({ ...form, transaction_date: e.target.value })}
                    />
                </div>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="smart-scan-description" className={needs.has('merchant') || needs.has('description') ? 'text-amber-200' : undefined}>
                    Description
                </Label>
                <Input
                    id="smart-scan-description"
                    maxLength={255}
                    value={form.description}
                    onChange={(e) => onChange({ ...form, description: e.target.value })}
                    placeholder="Merchant or note"
                />
            </div>

            <div className="flex flex-wrap justify-end gap-2 pt-1">
                <Button type="button" variant="ghost" className="cc-dash-btn-ghost" onClick={onCancel} disabled={submitting}>
                    Cancel
                </Button>
                <Button type="submit" className="cc-dash-btn-primary" disabled={submitting || !form.category_id}>
                    {submitting ? 'Adding…' : duplicateWarning || budgetWarning ? 'Add anyway' : 'Add transaction'}
                </Button>
            </div>
        </form>
    );
}
