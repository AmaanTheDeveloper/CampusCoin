import { BudgetProgress, BudgetStatusBadge } from '@/components/budgets/budget-progress';
import { BudgetVisual } from '@/components/dashboard/visuals/BudgetVisual';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { BudgetItem } from '@/types/budget';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Budgets', href: '/budgets' }];

type Props = {
    budgets: BudgetItem[];
    filters: { month: string | null };
};

export default function BudgetsIndex({ budgets, filters }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [month, setMonth] = useState(filters.month ? filters.month.slice(0, 7) : '');
    const [deletingId, setDeletingId] = useState<number | null>(null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    useEffect(() => {
        setMonth(filters.month ? filters.month.slice(0, 7) : '');
    }, [filters.month]);

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(route('budgets.index'), { month: month ? `${month}-01` : undefined }, { preserveState: true, replace: true });
    };

    const deleteBudget = (budget: BudgetItem) => {
        if (deletingId !== null) {
            return;
        }

        if (!confirm('Delete this budget? Transactions will be kept.')) {
            return;
        }

        setDeletingId(budget.id);
        router.delete(route('budgets.destroy', budget.id), {
            onFinish: () => setDeletingId(null),
        });
    };

    const hasMonthFilter = Boolean(filters.month);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Budgets" />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Budgets"
                    description="Plan your spending by category"
                    visual={<BudgetVisual className="size-14" />}
                    actions={
                        <Button asChild>
                            <Link href={route('budgets.create')}>
                                <Plus className="mr-2 size-4" aria-hidden />
                                Add budget
                            </Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <form onSubmit={applyFilters} className="cc-toolbar">
                    <div className="grid min-w-[10rem] flex-1 gap-1">
                        <Label htmlFor="filter-month" className="cc-text-secondary text-xs">
                            Month
                        </Label>
                        <Input id="filter-month" type="month" className="cc-field" value={month} onChange={(e) => setMonth(e.target.value)} />
                    </div>
                    <div className="flex gap-2 self-end">
                        <Button type="submit" variant="secondary" size="sm">
                            Apply
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setMonth('');
                                router.get(route('budgets.index'), {}, { preserveState: true, replace: true });
                            }}
                        >
                            Clear
                        </Button>
                    </div>
                </form>

                {budgets.length === 0 ? (
                    <FintechEmpty
                        visual={<BudgetVisual className="size-16" />}
                        title={hasMonthFilter ? 'No budgets for this month' : 'No budgets yet'}
                        description={
                            hasMonthFilter
                                ? 'Try another month, clear the filter, or create a budget for this period.'
                                : 'Set monthly category limits to track spending against your plan.'
                        }
                        action={
                            <>
                                {hasMonthFilter ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setMonth('');
                                            router.get(route('budgets.index'), {}, { preserveState: true, replace: true });
                                        }}
                                    >
                                        Clear filter
                                    </Button>
                                ) : null}
                                <Button asChild>
                                    <Link href={route('budgets.create')}>{hasMonthFilter ? 'Add budget' : 'Set your first budget'}</Link>
                                </Button>
                            </>
                        }
                    />
                ) : (
                    <div className="grid gap-3">
                        {budgets.map((budget) => (
                            <article key={budget.id} className="cc-elev-2 p-4">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold text-white">{budget.category.name}</h3>
                                            <BudgetStatusBadge status={budget.status} label={budget.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">{budget.month_label}</p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3 lg:w-[28rem]">
                                        <div>
                                            <p className="cc-text-tertiary text-xs">Budget</p>
                                            <p className="font-medium text-white tabular-nums">{formatMoney(budget.limit)}</p>
                                        </div>
                                        <div>
                                            <p className="cc-text-tertiary text-xs">Spent</p>
                                            <p className="font-medium text-white tabular-nums">{formatMoney(budget.spent)}</p>
                                        </div>
                                        <div>
                                            {budget.status === 'exceeded' ? (
                                                <>
                                                    <p className="cc-text-tertiary text-xs">Over Budget</p>
                                                    <p className="text-destructive font-medium tabular-nums">{formatMoney(budget.over_budget)}</p>
                                                </>
                                            ) : (
                                                <>
                                                    <p className="cc-text-tertiary text-xs">Remaining</p>
                                                    <p className="font-medium text-white tabular-nums">{formatMoney(budget.remaining)}</p>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <div className="lg:w-56">
                                        <BudgetProgress categoryName={budget.category.name} percentage={budget.percentage} status={budget.status} />
                                    </div>
                                    <div className="flex gap-1 self-start lg:self-center">
                                        <Button variant="ghost" size="icon" className="cc-icon-btn" asChild>
                                            <Link href={route('budgets.edit', budget.id)} aria-label={`Edit budget ${budget.category.name}`}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            className="cc-icon-btn"
                                            disabled={deletingId !== null}
                                            aria-label={`Delete budget ${budget.category.name}`}
                                            aria-busy={deletingId === budget.id}
                                            onClick={() => deleteBudget(budget)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </FintechPage>
        </AppLayout>
    );
}
