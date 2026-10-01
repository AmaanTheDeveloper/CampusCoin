import { FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type TransactionTypeValue, type TypeOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Props = {
    category: {
        id: number;
        name: string;
        type: TransactionTypeValue;
    };
    typeOptions: TypeOption[];
};

export default function EditCategory({ category, typeOptions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Categories', href: '/categories' },
        { title: 'Edit', href: `/categories/${category.id}/edit` },
    ];

    const form = useForm<{ type: TransactionTypeValue; name: string }>({
        type: category.type,
        name: category.name,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('categories.update', category.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit category" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Edit category" description="Update your personal category" />
                <FintechFormShell as="form" onSubmit={submit}>
                    <div className="space-y-2">
                        <Label>Category type</Label>
                        <div className="grid grid-cols-2 gap-2" role="group" aria-label="Category type">
                            {typeOptions.map((option) => {
                                const selected = form.data.type === option.value;
                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => form.setData('type', option.value as TransactionTypeValue)}
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

                    <div className="grid gap-1.5">
                        <Label htmlFor="name">Category name</Label>
                        <Input
                            id="name"
                            className="cc-field"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            maxLength={100}
                            required
                            disabled={form.processing}
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <FintechFormActions>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('categories.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </FintechFormActions>
                </FintechFormShell>
            </FintechPage>
        </AppLayout>
    );
}
