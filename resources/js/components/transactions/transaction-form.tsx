import { FintechFormActions, FintechFormShell } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { BudgetOverrunDialog, type BudgetOverrunPayload } from '@/components/transactions/budget-overrun-dialog';
import { InsufficientBalanceDialog } from '@/components/transactions/insufficient-balance-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { type CategoryOption, type SharedData, type TransactionTypeValue, type TypeOption } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

export type TransactionFormData = {
    type: TransactionTypeValue | '';
    category_id: string;
    amount: string;
    description: string;
    transaction_date: string;
    ai_suggested_category_id: string;
    ai_suggestion_accepted: boolean;
};

type AiSuggestion = {
    category_id: number;
    category_name: string;
    confidence: 'high' | 'medium' | 'low' | string;
    reason: string;
    source: string;
};

type TransactionFormProps = {
    typeOptions: TypeOption[];
    categories: CategoryOption[];
    initial?: Partial<TransactionFormData>;
    submitLabel: string;
    cancelHref: string;
    aiAvailable?: boolean;
    /** When false, do not auto-trigger AI (edit page). User can still request via description changes if enabled. */
    autoSuggest?: boolean;
    /** Application calendar date (YYYY-MM-DD) — max selectable transaction date. */
    today: string;
    /**
     * Authoritative available balance for create, or replacement balance for edit
     * (current balance excluding the transaction being edited). Display/preview only.
     */
    availableBalance: string;
    onSubmit: (form: ReturnType<typeof useForm<TransactionFormData>>) => void;
};

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** Integer cents comparison — display/preview only; server remains authoritative. */
function toCents(value: string): number | null {
    const trimmed = value.trim();
    if (trimmed === '' || !/^\d+(\.\d{1,2})?$/.test(trimmed)) {
        return null;
    }

    const [whole, fraction = ''] = trimmed.split('.');
    const cents = Number(whole) * 100 + Number((fraction + '00').slice(0, 2));

    return Number.isFinite(cents) ? cents : null;
}

export function TransactionForm({
    typeOptions,
    categories,
    initial,
    submitLabel,
    cancelHref,
    aiAvailable = false,
    autoSuggest = true,
    today,
    availableBalance,
    onSubmit,
}: TransactionFormProps) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm<TransactionFormData>({
        type: initial?.type ?? 'expense',
        category_id: initial?.category_id ?? '',
        amount: initial?.amount ?? '',
        description: initial?.description ?? '',
        transaction_date: initial?.transaction_date ?? today,
        ai_suggested_category_id: '',
        ai_suggestion_accepted: false,
    });

    const { data, setData, processing, errors, transform } = form;

    const [suggestion, setSuggestion] = useState<AiSuggestion | null>(null);
    const [aiStatus, setAiStatus] = useState<'idle' | 'loading' | 'error' | 'ready'>('idle');
    const [aiMessage, setAiMessage] = useState<string | null>(null);
    const [dismissedFor, setDismissedFor] = useState<string>('');
    const [manualLock, setManualLock] = useState(Boolean(initial?.category_id));
    const [balanceDialogOpen, setBalanceDialogOpen] = useState(false);
    const [dialogExpenseAmount, setDialogExpenseAmount] = useState(availableBalance);
    const [overrunDialogOpen, setOverrunDialogOpen] = useState(false);
    const [overrunPayload, setOverrunPayload] = useState<BudgetOverrunPayload | null>(null);
    const requestSeq = useRef(0);
    const latestDescription = useRef(data.description);
    const latestType = useRef(data.type);
    const amountInputRef = useRef<HTMLInputElement>(null);

    latestDescription.current = data.description;
    latestType.current = data.type;

    const focusAmountForReview = () => {
        const input = amountInputRef.current;
        if (!input) {
            return;
        }

        input.focus();
        input.select();
    };

    const filteredCategories = useMemo(() => categories.filter((category) => !data.type || category.type === data.type), [categories, data.type]);

    useEffect(() => {
        if (errors.amount && errors.amount.toLowerCase().includes('insufficient balance')) {
            setOverrunDialogOpen(false);
            setDialogExpenseAmount(data.amount || '0.00');
            setBalanceDialogOpen(true);
        }
    }, [errors.amount, data.amount]);

    useEffect(() => {
        if (errors.budget_overrun && flash?.budget_overrun) {
            setOverrunPayload(flash.budget_overrun);
            setOverrunDialogOpen(true);
        }
    }, [errors.budget_overrun, flash?.budget_overrun]);

    const setType = (type: TransactionTypeValue) => {
        const matching = categories.filter((category) => category.type === type);
        const stillValid = matching.some((category) => category.id === Number(data.category_id));
        setSuggestion(null);
        setAiStatus('idle');
        setAiMessage(null);
        setManualLock(false);
        setData({
            ...data,
            type,
            category_id: stillValid ? data.category_id : '',
            ai_suggested_category_id: '',
            ai_suggestion_accepted: false,
        });
    };

    useEffect(() => {
        if (!aiAvailable || !autoSuggest || manualLock) {
            return;
        }

        const description = data.description.trim();
        const type = data.type;
        const dismissKey = `${type}:${description.toLowerCase()}`;

        if (!type || description.length < 3 || dismissedFor === dismissKey) {
            return;
        }

        const seq = ++requestSeq.current;
        const timer = window.setTimeout(async () => {
            setAiStatus('loading');
            setAiMessage(null);

            try {
                const response = await fetch(route('transactions.suggest-category'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ type, description }),
                });

                const payload = await response.json();

                // Stale response protection
                if (seq !== requestSeq.current) {
                    return;
                }
                if (latestDescription.current.trim() !== description || latestType.current !== type) {
                    return;
                }

                if (!response.ok) {
                    setSuggestion(null);
                    setAiStatus('error');
                    setAiMessage('Category suggestion unavailable.');
                    return;
                }

                if (payload.suggestion) {
                    setSuggestion(payload.suggestion);
                    setData('ai_suggested_category_id', String(payload.suggestion.category_id));
                    setData('ai_suggestion_accepted', false);
                    setAiStatus('ready');
                    setAiMessage(null);
                } else {
                    setSuggestion(null);
                    setAiStatus(payload.error ? 'error' : 'idle');
                    setAiMessage(payload.error ?? null);
                }
            } catch {
                if (seq !== requestSeq.current) {
                    return;
                }
                setSuggestion(null);
                setAiStatus('error');
                setAiMessage('Category suggestion unavailable.');
            }
        }, 600);

        return () => window.clearTimeout(timer);
    }, [aiAvailable, autoSuggest, data.description, data.type, dismissedFor, manualLock, setData]);

    const acceptSuggestion = () => {
        if (!suggestion) {
            return;
        }
        setData({
            ...data,
            category_id: String(suggestion.category_id),
            ai_suggested_category_id: String(suggestion.category_id),
            ai_suggestion_accepted: true,
        });
        setManualLock(true);
        setSuggestion(null);
        setAiStatus('idle');
    };

    const dismissSuggestion = () => {
        const key = `${data.type}:${data.description.trim().toLowerCase()}`;
        setDismissedFor(key);
        setSuggestion(null);
        setAiStatus('idle');
        setData('ai_suggestion_accepted', false);
    };

    const onCategoryChange = (value: string) => {
        setManualLock(true);
        setSuggestion(null);
        setAiStatus('idle');
        setData({
            ...data,
            category_id: value,
            ai_suggestion_accepted: data.ai_suggested_category_id !== '' && value === data.ai_suggested_category_id,
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (processing) {
            return;
        }

        if (data.type === 'expense') {
            const expenseCents = toCents(data.amount);
            const balanceCents = toCents(availableBalance);
            if (expenseCents !== null && balanceCents !== null && expenseCents > balanceCents) {
                setDialogExpenseAmount(data.amount);
                setBalanceDialogOpen(true);
                return;
            }
        }

        transform((payload) => ({
            ...payload,
            confirm_budget_overrun: false,
        }));
        onSubmit(form);
    };

    const continueDespiteOverrun = () => {
        if (processing) {
            return;
        }

        transform((payload) => ({
            ...payload,
            confirm_budget_overrun: true,
        }));
        onSubmit(form);
    };

    const reviewBudget = () => {
        setOverrunDialogOpen(false);
        router.visit(route('budgets.index'));
    };

    return (
        <>
            <FintechFormShell as="form" onSubmit={submit}>
                <div className="space-y-2">
                    <Label>Transaction type</Label>
                    <div className="grid grid-cols-2 gap-2" role="group" aria-label="Transaction type">
                        {typeOptions.map((option) => {
                            const selected = data.type === option.value;
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => setType(option.value as TransactionTypeValue)}
                                    disabled={processing}
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
                    <InputError message={errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="category_id">Category</Label>
                    <select
                        id="category_id"
                        required
                        className="cc-field w-full"
                        value={data.category_id}
                        onChange={(e) => onCategoryChange(e.target.value)}
                        disabled={processing}
                    >
                        <option value="">Select a category</option>
                        {filteredCategories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                                {category.is_default ? ' (System)' : ''}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.category_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="amount">Amount</Label>
                    <Input
                        ref={amountInputRef}
                        id="amount"
                        type="number"
                        inputMode="decimal"
                        min="0.01"
                        step="0.01"
                        required
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        placeholder="0.00"
                        disabled={processing}
                        autoFocus
                    />
                    <p className="cc-text-secondary text-xs">
                        Enter an amount greater than 0 (up to 2 decimal places).
                        {data.type === 'expense' ? ` Available balance: Rs ${availableBalance}.` : null}
                    </p>
                    <InputError message={errors.amount} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <Input
                        id="description"
                        type="text"
                        maxLength={255}
                        value={data.description}
                        onChange={(e) => {
                            setManualLock(false);
                            setData('description', e.target.value);
                            setData('ai_suggestion_accepted', false);
                        }}
                        placeholder="Optional note (e.g. Campus cafe lunch)"
                        disabled={processing}
                    />
                    <InputError message={errors.description} />

                    {aiAvailable && aiStatus === 'loading' ? (
                        <p className="cc-text-secondary text-xs" aria-live="polite">
                            Suggesting category…
                        </p>
                    ) : null}

                    {aiAvailable && aiMessage && !suggestion ? (
                        <p className="cc-text-secondary text-xs" aria-live="polite">
                            {aiMessage}
                        </p>
                    ) : null}

                    {suggestion ? (
                        <div className="border-border bg-muted/40 rounded-lg border p-3" role="status" aria-live="polite">
                            <p className="text-sm font-medium">Suggested category: {suggestion.category_name}</p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {suggestion.confidence.charAt(0).toUpperCase() + suggestion.confidence.slice(1)} confidence — AI suggestion, review
                                before saving.
                            </p>
                            {suggestion.reason ? <p className="text-muted-foreground mt-1 text-xs">{suggestion.reason}</p> : null}
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button type="button" size="sm" onClick={acceptSuggestion} disabled={processing}>
                                    Use suggestion
                                </Button>
                                <Button type="button" size="sm" variant="outline" onClick={dismissSuggestion} disabled={processing}>
                                    Dismiss
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="transaction_date">Date</Label>
                    <Input
                        id="transaction_date"
                        type="date"
                        required
                        max={today}
                        value={data.transaction_date}
                        onChange={(e) => setData('transaction_date', e.target.value)}
                        disabled={processing}
                    />
                    <p className="cc-text-secondary text-xs">Transaction date cannot be in the future (max {today}).</p>
                    <InputError message={errors.transaction_date} />
                </div>

                <FintechFormActions>
                    <Button type="button" variant="outline" asChild disabled={processing}>
                        <Link href={cancelHref}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing} aria-busy={processing}>
                        {processing ? 'Saving…' : submitLabel}
                    </Button>
                </FintechFormActions>
            </FintechFormShell>

            <InsufficientBalanceDialog
                open={balanceDialogOpen}
                availableBalance={availableBalance}
                expenseAmount={dialogExpenseAmount}
                onOpenChange={setBalanceDialogOpen}
                onReviewAmount={focusAmountForReview}
            />

            <BudgetOverrunDialog
                open={overrunDialogOpen}
                payload={overrunPayload}
                processing={processing}
                onContinue={continueDespiteOverrun}
                onReviewBudget={reviewBudget}
                onOpenChange={setOverrunDialogOpen}
            />
        </>
    );
}
