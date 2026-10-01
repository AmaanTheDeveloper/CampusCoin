import { AdminAnnouncementsVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { AdminAnnouncementRow, AnnouncementStatus, Paginated, TypeOption } from '@/types/admin';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Announcements', href: '/admin/announcements' },
];

type Props = {
    announcements: Paginated<AdminAnnouncementRow>;
    filters: { status: 'all' | AnnouncementStatus };
    priority_options: TypeOption[];
    status_options: TypeOption[];
};

type EditorState = { mode: 'create' } | { mode: 'edit'; item: AdminAnnouncementRow } | null;

function toLocalInput(value: string | null) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export default function AdminAnnouncementsIndex({ announcements, filters, priority_options, status_options }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [editor, setEditor] = useState<EditorState>(null);
    const [deleteTarget, setDeleteTarget] = useState<AdminAnnouncementRow | null>(null);
    const [saving, setSaving] = useState(false);

    const form = useForm({
        title: '',
        message: '',
        priority: 'normal',
        status: 'draft',
        starts_at: '',
        ends_at: '',
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const setStatusFilter = (status: Props['filters']['status']) => {
        router.get(
            route('admin.announcements.index'),
            { status: status === 'all' ? undefined : status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openCreate = () => {
        form.setData({ title: '', message: '', priority: 'normal', status: 'draft', starts_at: '', ends_at: '' });
        form.clearErrors();
        setEditor({ mode: 'create' });
    };

    const openEdit = (item: AdminAnnouncementRow) => {
        form.setData({
            title: item.title,
            message: item.message,
            priority: item.priority,
            status: item.status,
            starts_at: toLocalInput(item.starts_at),
            ends_at: toLocalInput(item.ends_at),
        });
        form.clearErrors();
        setEditor({ mode: 'edit', item });
    };

    const submitEditor = (event: FormEvent) => {
        event.preventDefault();
        if (!editor) return;

        const payload = {
            title: form.data.title,
            message: form.data.message,
            priority: form.data.priority,
            status: form.data.status,
            starts_at: form.data.starts_at || null,
            ends_at: form.data.ends_at || null,
        };

        if (editor.mode === 'create') {
            setSaving(true);
            router.post(route('admin.announcements.store'), payload, {
                onError: (errs) => form.setError(errs as Record<string, string>),
                onSuccess: () => setEditor(null),
                onFinish: () => setSaving(false),
            });
            return;
        }

        setSaving(true);
        router.put(route('admin.announcements.update', editor.item.id), payload, {
            onError: (errs) => form.setError(errs as Record<string, string>),
            onSuccess: () => setEditor(null),
            onFinish: () => setSaving(false),
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('admin.announcements.destroy', deleteTarget.id), {
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Announcements" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Content"
                    title="Announcements"
                    description="Publish campus-wide notices with priority and scheduling."
                    visual={<AdminAnnouncementsVisual />}
                    actions={
                        <Button className="cc-dash-btn-primary" onClick={openCreate}>
                            <Plus className="mr-2 size-4" aria-hidden />
                            New announcement
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <div className="cc-admin-period" role="group" aria-label="Status filter">
                    <button
                        type="button"
                        className={cn('cc-admin-period__btn', filters.status === 'all' && 'is-active')}
                        onClick={() => setStatusFilter('all')}
                    >
                        All
                    </button>
                    {status_options.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            className={cn('cc-admin-period__btn', filters.status === option.value && 'is-active')}
                            onClick={() => setStatusFilter(option.value as AnnouncementStatus)}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                {announcements.data.length === 0 ? (
                    <FintechEmpty title="No announcements" description="Create an announcement to reach students." />
                ) : (
                    <>
                        <div className="grid gap-3">
                            {announcements.data.map((item) => (
                                <article key={item.id} className="cc-admin-mobile-card space-y-2">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h2 className="text-base font-semibold">{item.title}</h2>
                                            <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{item.message}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge variant="outline">{item.priority}</Badge>
                                            <Badge>{item.status}</Badge>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Updated {formatDate(item.updated_at)}
                                        {item.creator ? ` · ${item.creator}` : ''}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(item)}>
                                            <Pencil className="mr-1 size-3.5" aria-hidden />
                                            Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => setDeleteTarget(item)}>
                                            <Trash2 className="mr-1 size-3.5" aria-hidden />
                                            Delete
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <PaginationNav links={announcements.links} />
                    </>
                )}
            </FintechPage>

            <Dialog open={editor !== null} onOpenChange={(open) => !open && setEditor(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editor?.mode === 'edit' ? 'Edit announcement' : 'New announcement'}</DialogTitle>
                        <DialogDescription>Set title, message, priority, and lifecycle status.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEditor} className="space-y-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="announcement-title">Title</Label>
                            <Input
                                id="announcement-title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                required
                                maxLength={160}
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="announcement-message">Message</Label>
                            <textarea
                                id="announcement-message"
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                                required
                                maxLength={5000}
                                rows={5}
                                className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[100px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            />
                            <InputError message={form.errors.message} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label>Priority</Label>
                                <Select value={form.data.priority} onValueChange={(value) => form.setData('priority', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priority_options.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.priority} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Status</Label>
                                <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {status_options.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.status} />
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="starts-at">Starts at</Label>
                                <Input
                                    id="starts-at"
                                    type="datetime-local"
                                    value={form.data.starts_at}
                                    onChange={(e) => form.setData('starts_at', e.target.value)}
                                />
                                <InputError message={form.errors.starts_at} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ends-at">Ends at</Label>
                                <Input
                                    id="ends-at"
                                    type="datetime-local"
                                    value={form.data.ends_at}
                                    onChange={(e) => form.setData('ends_at', e.target.value)}
                                />
                                <InputError message={form.errors.ends_at} />
                            </div>
                        </div>
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
                        <DialogTitle>Delete announcement?</DialogTitle>
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
