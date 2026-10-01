import { FintechEmpty, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Budgets', href: '/budgets' },
    { title: 'Add', href: '/budgets/create' },
];

type Props = {
    categories: CategoryOption[];
    defaultMonth: string;
};

export default function CreateBudget({ categories, defaultMonth }: Props) {
    const form = useForm({
        month: defaultMonth,
        category_id: '',
        limit_amount: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing || categories.length === 0) {
            return;
        }

        form.transform((data) => ({
            ...data,
            month: data.month.length === 7 ? `${data.month}-01` : data.month,
        }));
        form.post(route('budgets.store'));
    };

    const defaults = categories.filter((c) => c.is_default);
    const personal = categories.filter((c) => !c.is_default);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add budget" />
            <FintechPage stagger={false}>
                <FintechPageHeader eyebrow="Finance" title="Add budget" description="Set a monthly spending limit for an expense category" />

                {categories.length === 0 ? (
                    <FintechEmpty
                        title="No expense categories available"
                        description="Create an expense category before setting a budget."
                        action={
                            <Button asChild>
                                <Link href={route('categories.create')}>Add category</Link>
                            </Button>
                        }
                    />
                ) : (
                    <FintechFormShell as="form" onSubmit={submit} noValidate>
                        <div className="grid gap-1.5">
                            <Label htmlFor="month">Month</Label>
                            <Input
                                id="month"
                                type="month"
                                className="cc-field"
                                required
                                value={form.data.month}
                                onChange={(e) => form.setData('month', e.target.value)}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.month)}
                            />
                            <InputError message={form.errors.month} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="category_id">Expense category</Label>
                            <select
                                id="category_id"
                                required
                                className="cc-field w-full"
                                value={form.data.category_id}
                                onChange={(e) => form.setData('category_id', e.target.value)}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.category_id)}
                            >
                                <option value="">Select a category</option>
                                {defaults.length > 0 && (
                                    <optgroup label="System default">
                                        {defaults.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                                {personal.length > 0 && (
                                    <optgroup label="Personal">
                                        {personal.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                            </select>
                            <InputError message={form.errors.category_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="limit_amount">Budget limit</Label>
                            <Input
                                id="limit_amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                inputMode="decimal"
                                className="cc-field"
                                value={form.data.limit_amount}
                                onChange={(e) => form.setData('limit_amount', e.target.value)}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.limit_amount)}
                            />
                            <InputError message={form.errors.limit_amount} />
                        </div>

                        <FintechFormActions>
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('budgets.index')}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing} aria-busy={form.processing}>
                                {form.processing ? 'Saving…' : 'Create budget'}
                            </Button>
                        </FintechFormActions>
                    </FintechFormShell>
                )}
            </FintechPage>
        </AppLayout>
    );
}
