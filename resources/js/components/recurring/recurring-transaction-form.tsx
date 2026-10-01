import { FintechFormActions, FintechFormShell } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { type CategoryOption, type FrequencyOption, type TransactionTypeValue, type TypeOption } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

export type RecurringFormData = {
    type: TransactionTypeValue;
    category_id: string;
    amount: string;
    description: string;
    frequency: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

type Props = {
    typeOptions: TypeOption[];
    frequencyOptions: FrequencyOption[];
    categories: CategoryOption[];
    /** Application calendar date (YYYY-MM-DD) from the server — preferred default for start_date. */
    today?: string;
    initial?: Partial<RecurringFormData>;
    submitLabel: string;
    cancelHref: string;
    onSubmit: (form: ReturnType<typeof useForm<RecurringFormData>>) => void;
};

/** Local calendar YYYY-MM-DD — fallback only when server `today` is not provided. */
function localCalendarDate(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function RecurringTransactionForm({ typeOptions, frequencyOptions, categories, today, initial, submitLabel, cancelHref, onSubmit }: Props) {
    const form = useForm<RecurringFormData>({
        type: initial?.type ?? 'expense',
        category_id: initial?.category_id ?? '',
        amount: initial?.amount ?? '',
        description: initial?.description ?? '',
        frequency: initial?.frequency ?? 'monthly',
        start_date: initial?.start_date ?? today ?? localCalendarDate(),
        end_date: initial?.end_date ?? '',
        is_active: initial?.is_active ?? true,
    });

    const filteredCategories = useMemo(() => categories.filter((category) => category.type === form.data.type), [categories, form.data.type]);

    const setType = (type: TransactionTypeValue) => {
        const matching = categories.filter((category) => category.type === type);
        const stillValid = matching.some((category) => category.id === Number(form.data.category_id));
        form.setData({
            ...form.data,
            type,
            category_id: stillValid ? form.data.category_id : '',
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) {
            return;
        }
        onSubmit(form);
    };

    return (
        <FintechFormShell as="form" onSubmit={submit}>
            <div className="space-y-2">
                <Label>Type</Label>
                <div className="grid grid-cols-2 gap-2" role="group" aria-label="Transaction type">
                    {typeOptions.map((option) => {
                        const selected = form.data.type === option.value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => setType(option.value as TransactionTypeValue)}
                                disabled={form.processing}
                                aria-pressed={selected}
                                className={cn(
                                    'rounded-lg border px-4 py-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-[#E94F09]/50 focus-visible:outline-none',
                                    selected
                                        ? 'border-[#E94F09] bg-[#E94F09] text-white'
                                        : 'border-white/10 bg-white/[0.03] text-[#cfcfcf] hover:bg-white/[0.06]',
                                )}
                            >
                                {option.label}
                            </button>
                        );
                    })}
                </div>
                <InputError message={form.errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="category_id">Category</Label>
                <select
                    id="category_id"
                    required
                    className="cc-field w-full"
                    value={form.data.category_id}
                    onChange={(e) => form.setData('category_id', e.target.value)}
                    disabled={form.processing}
                >
                    <option value="">Select a category</option>
                    {filteredCategories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                            {category.is_default ? ' (System)' : ''}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.category_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="amount">Amount</Label>
                <Input
                    id="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                    disabled={form.processing}
                />
                <InputError message={form.errors.amount} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Input
                    id="description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    maxLength={255}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="frequency">Frequency</Label>
                <select
                    id="frequency"
                    required
                    className="cc-field w-full"
                    value={form.data.frequency}
                    onChange={(e) => form.setData('frequency', e.target.value)}
                    disabled={form.processing}
                >
                    {frequencyOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.frequency} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="start_date">Start date</Label>
                    <Input
                        id="start_date"
                        type="date"
                        required
                        min={today ?? localCalendarDate()}
                        value={form.data.start_date}
                        onChange={(e) => form.setData('start_date', e.target.value)}
                        disabled={form.processing}
                    />
                    <p className="cc-text-secondary text-xs">Please select today or a future date.</p>
                    <InputError message={form.errors.start_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="end_date">End date (optional)</Label>
                    <Input
                        id="end_date"
                        type="date"
                        min={form.data.start_date || today || localCalendarDate()}
                        value={form.data.end_date}
                        onChange={(e) => form.setData('end_date', e.target.value)}
                        disabled={form.processing}
                    />
                    <InputError message={form.errors.end_date} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <input
                    id="is_active"
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(e) => form.setData('is_active', e.target.checked)}
                    disabled={form.processing}
                    className="border-input size-4 rounded"
                />
                <Label htmlFor="is_active">Active</Label>
            </div>
            <InputError message={form.errors.is_active} />

            <FintechFormActions>
                <Button type="button" variant="outline" asChild>
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing} aria-busy={form.processing}>
                    {form.processing ? 'Saving…' : submitLabel}
                </Button>
            </FintechFormActions>
        </FintechFormShell>
    );
}
