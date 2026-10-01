import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminCategoriesVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { AdminCategoryRow, TypeOption } from '@/types/admin';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Categories', href: '/admin/categories' },
];

type Props = {
    income: AdminCategoryRow[];
    expense: AdminCategoryRow[];
    type_options: TypeOption[];
};

type EditorState = { mode: 'create' } | { mode: 'edit'; category: AdminCategoryRow } | null;

export default function AdminCategoriesIndex({ income, expense, type_options }: Props) {
    const { flash, errors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [editor, setEditor] = useState<EditorState>(null);
    const [deleteTarget, setDeleteTarget] = useState<AdminCategoryRow | null>(null);
    const [toggleTarget, setToggleTarget] = useState<AdminCategoryRow | null>(null);
    const [toggleProcessing, setToggleProcessing] = useState(false);

    const form = useForm<{ name: string; type: 'income' | 'expense'; is_active: boolean }>({
        name: '',
        type: 'expense',
        is_active: true,
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const openCreate = () => {
        form.setData({ name: '', type: 'expense', is_active: true });
        form.clearErrors();
        setEditor({ mode: 'create' });
    };

    const openEdit = (category: AdminCategoryRow) => {
        form.setData({ name: category.name, type: category.type, is_active: category.is_active });
        form.clearErrors();
        setEditor({ mode: 'edit', category });
    };

    const submitEditor = (event: FormEvent) => {
        event.preventDefault();
        if (!editor) return;

        if (editor.mode === 'create') {
            form.post(route('admin.categories.store'), {
                onSuccess: () => setEditor(null),
            });
            return;
        }

        form.put(route('admin.categories.update', editor.category.id), {
            onSuccess: () => setEditor(null),
        });
    };

    const confirmToggle = () => {
        if (!toggleTarget) return;
        setToggleProcessing(true);
        router.put(
            route('admin.categories.update', toggleTarget.id),
            {
                name: toggleTarget.name,
                type: toggleTarget.type,
                is_active: !toggleTarget.is_active,
            },
            {
                onFinish: () => {
                    setToggleProcessing(false);
                    setToggleTarget(null);
                },
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('admin.categories.destroy', deleteTarget.id), {
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Categories" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Management"
                    title="Categories"
                    description="System income and expense categories available to all students."
                    visual={<AdminCategoriesVisual />}
                    actions={
                        <Button className="cc-dash-btn-primary" onClick={openCreate}>
                            <Plus className="mr-2 size-4" aria-hidden />
                            Add category
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                <InputError message={errors?.category} />

                <CategorySection title="Income" items={income} onEdit={openEdit} onToggle={setToggleTarget} onDelete={setDeleteTarget} />
                <CategorySection title="Expense" items={expense} onEdit={openEdit} onToggle={setToggleTarget} onDelete={setDeleteTarget} />
            </FintechPage>

            <Dialog open={editor !== null} onOpenChange={(open) => !open && setEditor(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editor?.mode === 'edit' ? 'Edit category' : 'Add category'}</DialogTitle>
                        <DialogDescription>System categories appear for every student account.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEditor} className="space-y-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="category-name">Name</Label>
                            <Input
                                id="category-name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                                maxLength={80}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="category-type">Type</Label>
                            <Select value={form.data.type} onValueChange={(value: 'income' | 'expense') => form.setData('type', value)}>
                                <SelectTrigger id="category-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {type_options.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
                            Active
                        </label>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditor(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" className="cc-dash-btn-primary" disabled={form.processing}>
                                {editor?.mode === 'edit' ? 'Save changes' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={toggleTarget !== null} onOpenChange={(open) => !open && !toggleProcessing && setToggleTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{toggleTarget?.is_active ? 'Deactivate category?' : 'Activate category?'}</DialogTitle>
                        <DialogDescription>
                            {toggleTarget?.is_active
                                ? 'This will hide this system category from students. Existing category history is not deleted.'
                                : 'This will make this system category available to students again.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setToggleTarget(null)} disabled={toggleProcessing}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={confirmToggle} disabled={toggleProcessing}>
                            {toggleProcessing ? 'Saving…' : toggleTarget?.is_active ? 'Deactivate' : 'Activate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete category?</DialogTitle>
                        <DialogDescription>
                            Delete “{deleteTarget?.name}”? Categories with transactions cannot be deleted — deactivate them instead.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

function CategorySection({
    title,
    items,
    onEdit,
    onToggle,
    onDelete,
}: {
    title: string;
    items: AdminCategoryRow[];
    onEdit: (category: AdminCategoryRow) => void;
    onToggle: (category: AdminCategoryRow) => void;
    onDelete: (category: AdminCategoryRow) => void;
}) {
    return (
        <section className="space-y-3">
            <FintechSectionHeader title={title} meta={`${items.length} categories`} />
            {items.length === 0 ? (
                <FintechEmpty
                    className="cc-admin-empty-compact py-8"
                    visual={<AdminCategoriesVisual className="size-12" />}
                    title={`No ${title.toLowerCase()} categories`}
                    description="Add a system category to make it available to every student account."
                />
            ) : (
                <>
                    <div className="cc-admin-table-wrap hidden md:block">
                        <table className="cc-admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((category) => (
                                    <tr key={category.id}>
                                        <td className="font-medium">{category.name}</td>
                                        <td>
                                            <AdminStatusBadge status={category.is_active ? 'active' : 'inactive'} />
                                        </td>
                                        <td className="tabular-nums">{category.usage_count}</td>
                                        <td>
                                            <div className="flex flex-wrap justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => onEdit(category)}>
                                                    <Pencil className="size-4" aria-hidden />
                                                    <span className="sr-only">Edit</span>
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => onToggle(category)}>
                                                    {category.is_active ? 'Deactivate' : 'Activate'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => onDelete(category)}
                                                    disabled={category.usage_count > 0}
                                                >
                                                    <Trash2 className="size-4" aria-hidden />
                                                    <span className="sr-only">Delete</span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="cc-admin-mobile-card-list grid gap-3 md:hidden">
                        {items.map((category) => (
                            <div key={category.id} className="cc-admin-mobile-card space-y-3">
                                <div className="flex min-w-0 items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium" title={category.name}>
                                            {category.name}
                                        </p>
                                        <p className="text-muted-foreground text-xs capitalize">{category.type}</p>
                                    </div>
                                    <AdminStatusBadge status={category.is_active ? 'active' : 'inactive'} />
                                </div>
                                <p className="cc-admin-mobile-card__meta text-muted-foreground text-xs tabular-nums">{category.usage_count} uses</p>
                                <div className="cc-admin-mobile-card__actions grid grid-cols-3 gap-2">
                                    <Button variant="outline" size="sm" className="min-h-11" onClick={() => onEdit(category)}>
                                        Edit
                                    </Button>
                                    <Button variant="outline" size="sm" className="min-h-11" onClick={() => onToggle(category)}>
                                        {category.is_active ? 'Deactivate' : 'Activate'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="min-h-11"
                                        onClick={() => onDelete(category)}
                                        disabled={category.usage_count > 0}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}
