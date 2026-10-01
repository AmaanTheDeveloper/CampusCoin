import { AdminTipsVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { AdminTipRow, Paginated, TypeOption } from '@/types/admin';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Saving Tips', href: '/admin/saving-tips' },
];

type Props = {
    tips: Paginated<AdminTipRow>;
    filters: { status: 'all' | 'active' | 'inactive' };
    impact_options: TypeOption[];
};

type EditorState = { mode: 'create' } | { mode: 'edit'; tip: AdminTipRow } | null;

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export default function AdminSavingTipsIndex({ tips, filters, impact_options }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [editor, setEditor] = useState<EditorState>(null);
    const [deleteTarget, setDeleteTarget] = useState<AdminTipRow | null>(null);
    const [saving, setSaving] = useState(false);

    const form = useForm<{
        title: string;
        body: string;
        category_label: string;
        impact_level: string;
        is_active: boolean;
    }>({
        title: '',
        body: '',
        category_label: '',
        impact_level: 'medium',
        is_active: true,
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const setStatusFilter = (status: Props['filters']['status']) => {
        router.get(
            route('admin.saving-tips.index'),
            { status: status === 'all' ? undefined : status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openCreate = () => {
        form.setData({ title: '', body: '', category_label: '', impact_level: 'medium', is_active: true });
        form.clearErrors();
        setEditor({ mode: 'create' });
    };

    const openEdit = (tip: AdminTipRow) => {
        form.setData({
            title: tip.title,
            body: tip.body,
            category_label: tip.category_label ?? '',
            impact_level: tip.impact_level,
            is_active: tip.is_active,
        });
        form.clearErrors();
        setEditor({ mode: 'edit', tip });
    };

    const submitEditor = (event: FormEvent) => {
        event.preventDefault();
        if (!editor) return;

        const payload = {
            title: form.data.title,
            body: form.data.body,
            category_label: form.data.category_label || null,
            impact_level: form.data.impact_level,
            is_active: form.data.is_active,
        };

        if (editor.mode === 'create') {
            setSaving(true);
            router.post(route('admin.saving-tips.store'), payload, {
                onError: (errs) => form.setError(errs as Record<string, string>),
                onSuccess: () => setEditor(null),
                onFinish: () => setSaving(false),
            });
            return;
        }

        setSaving(true);
        router.put(route('admin.saving-tips.update', editor.tip.id), payload, {
            onError: (errs) => form.setError(errs as Record<string, string>),
            onSuccess: () => setEditor(null),
            onFinish: () => setSaving(false),
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('admin.saving-tips.destroy', deleteTarget.id), {
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Saving Tips" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Content"
                    title="Saving Tips"
                    description="Manage tip templates surfaced in student insights."
                    visual={<AdminTipsVisual />}
                    actions={
                        <Button className="cc-dash-btn-primary" onClick={openCreate}>
                            <Plus className="mr-2 size-4" aria-hidden />
                            New tip
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <div className="cc-admin-period" role="group" aria-label="Status filter">
                    {(['all', 'active', 'inactive'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            className={cn('cc-admin-period__btn', filters.status === value && 'is-active')}
                            onClick={() => setStatusFilter(value)}
                        >
                            {value === 'all' ? 'All' : value === 'active' ? 'Active' : 'Inactive'}
                        </button>
                    ))}
                </div>

                {tips.data.length === 0 ? (
                    <FintechEmpty title="No saving tips" description="Create tip templates for student insights." />
                ) : (
                    <>
                        <div className="grid gap-3">
                            {tips.data.map((tip) => (
                                <article key={tip.id} className="cc-admin-mobile-card space-y-2">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h2 className="text-base font-semibold">{tip.title}</h2>
                                            <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{tip.body}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge variant="outline">{tip.impact_level}</Badge>
                                            <Badge variant={tip.is_active ? 'default' : 'outline'}>{tip.is_active ? 'Active' : 'Inactive'}</Badge>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {tip.category_label ? `${tip.category_label} · ` : ''}
                                        Updated {formatDate(tip.updated_at)}
                                        {tip.creator ? ` · ${tip.creator}` : ''}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(tip)}>
                                            <Pencil className="mr-1 size-3.5" aria-hidden />
                                            Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => setDeleteTarget(tip)}>
                                            <Trash2 className="mr-1 size-3.5" aria-hidden />
                                            Delete
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <PaginationNav links={tips.links} />
                    </>
                )}
            </FintechPage>

            <Dialog open={editor !== null} onOpenChange={(open) => !open && setEditor(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editor?.mode === 'edit' ? 'Edit saving tip' : 'New saving tip'}</DialogTitle>
                        <DialogDescription>Tip templates power student insight suggestions.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEditor} className="space-y-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="tip-title">Title</Label>
                            <Input
                                id="tip-title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                required
                                maxLength={160}
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="tip-body">Body</Label>
                            <textarea
                                id="tip-body"
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                                required
                                maxLength={5000}
                                rows={5}
                                className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[100px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            />
                            <InputError message={form.errors.body} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="tip-category">Category label</Label>
                            <Input
                                id="tip-category"
                                value={form.data.category_label}
                                onChange={(e) => form.setData('category_label', e.target.value)}
                                maxLength={80}
                                placeholder="Optional"
                            />
                            <InputError message={form.errors.category_label} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Impact level</Label>
                            <Select value={form.data.impact_level} onValueChange={(value) => form.setData('impact_level', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {impact_options.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.impact_level} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
                            Active
                        </label>
                        <InputError message={form.errors.is_active} />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditor(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" className="cc-dash-btn-primary" disabled={saving}>
                                {editor?.mode === 'edit' ? 'Save changes' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete saving tip?</DialogTitle>
                        <DialogDescription>Delete “{deleteTarget?.title}”? This cannot be undone.</DialogDescription>
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
