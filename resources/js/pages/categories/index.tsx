import { CategoryVisual } from '@/components/dashboard/visuals/CategoryVisual';
import { FintechFlash, FintechPage, FintechPageHeader, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData, type TypeOption } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Categories', href: '/categories' }];

type CategoryRow = {
    id: number;
    name: string;
    type: 'income' | 'expense';
    is_default: boolean;
    is_personal: boolean;
    can_edit: boolean;
    can_delete: boolean;
};

type IndexProps = {
    categories: CategoryRow[];
    typeOptions: TypeOption[];
};

export default function CategoriesIndex({ categories }: IndexProps) {
    const { flash, errors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const incomeDefaults = useMemo(() => categories.filter((c) => c.type === 'income' && c.is_default), [categories]);
    const incomePersonal = useMemo(() => categories.filter((c) => c.type === 'income' && c.is_personal), [categories]);
    const expenseDefaults = useMemo(() => categories.filter((c) => c.type === 'expense' && c.is_default), [categories]);
    const expensePersonal = useMemo(() => categories.filter((c) => c.type === 'expense' && c.is_personal), [categories]);

    const deleteCategory = (category: CategoryRow) => {
        if (!confirm(`Delete category “${category.name}”?`)) {
            return;
        }
        router.delete(route('categories.destroy', category.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Categories"
                    description="Organize income and expenses with system and personal categories"
                    visual={<CategoryVisual className="size-14" />}
                    actions={
                        <Button asChild>
                            <Link href={route('categories.create')}>
                                <Plus className="mr-2 size-4" aria-hidden />
                                Add category
                            </Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                <InputError message={errors?.category} />

                <section className="space-y-4" aria-labelledby="income-heading">
                    <FintechSectionHeader title={<span id="income-heading">Income categories</span>} />
                    <CategoryGroup title="System default" items={incomeDefaults} onDelete={deleteCategory} empty="No default income categories." />
                    <CategoryGroup title="Personal" items={incomePersonal} onDelete={deleteCategory} empty="No personal categories yet." />
                </section>

                <section className="space-y-4" aria-labelledby="expense-heading">
                    <FintechSectionHeader title={<span id="expense-heading">Expense categories</span>} />
                    <CategoryGroup title="System default" items={expenseDefaults} onDelete={deleteCategory} empty="No default expense categories." />
                    <CategoryGroup title="Personal" items={expensePersonal} onDelete={deleteCategory} empty="No personal categories yet." />
                </section>
            </FintechPage>
        </AppLayout>
    );
}

function CategoryGroup({
    title,
    items,
    empty,
    onDelete,
}: {
    title: string;
    items: CategoryRow[];
    empty: string;
    onDelete: (category: CategoryRow) => void;
}) {
    return (
        <div className="cc-elev-2 overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/8 px-4 py-2.5">
                <h3 className="text-sm font-medium text-[#cfcfcf]">{title}</h3>
                <Badge variant="secondary">{items.length}</Badge>
            </div>
            {items.length === 0 ? (
                <p className="cc-text-secondary px-4 py-6 text-sm">{empty}</p>
            ) : (
                <ul className="divide-y divide-white/6">
                    {items.map((category) => (
                        <li key={category.id} className="flex items-center justify-between gap-3 px-4 py-3">
                            <div className="min-w-0">
                                <p className="truncate font-medium text-white">{category.name}</p>
                                <p className="cc-text-tertiary text-xs">{category.is_default ? 'System default' : 'Personal'}</p>
                            </div>
                            {category.can_edit || category.can_delete ? (
                                <div className="flex shrink-0 gap-1">
                                    {category.can_edit && (
                                        <Button variant="ghost" size="icon" className="cc-icon-btn" asChild>
                                            <Link href={route('categories.edit', category.id)} aria-label={`Edit category ${category.name}`}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                    )}
                                    {category.can_delete && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            className="cc-icon-btn"
                                            aria-label={`Delete category ${category.name}`}
                                            onClick={() => onDelete(category)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
