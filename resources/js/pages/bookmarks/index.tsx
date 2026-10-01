import { FintechEmpty, FintechPage } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Bookmarks', href: '/bookmarks' }];

type BookmarkItem = {
    id: number;
    type: string;
    bookmarkable_type: string;
    bookmarkable_id: number;
    title: string;
    summary: string;
    period: string | null;
    created_at: string | null;
};

type Props = {
    bookmarks: BookmarkItem[];
};

export default function BookmarksIndex({ bookmarks }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookmarks" />
            <FintechPage>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Bookmarks</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Saved insights and saving tips.</p>
                </div>

                {bookmarks.length === 0 ? (
                    <FintechEmpty
                        title="No bookmarks yet"
                        description="Save insights you want to revisit. They’ll show up here."
                        action={
                            <Button asChild>
                                <Link href={route('insights.index')}>Browse insights</Link>
                            </Button>
                        }
                    />
                ) : (
                    <ul className="space-y-3">
                        {bookmarks.map((bookmark) => (
                            <li key={bookmark.id} className="border-border bg-card rounded-xl border p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Badge variant="secondary" className="mb-2">
                                            {bookmark.type}
                                        </Badge>
                                        {bookmark.period ? <p className="text-muted-foreground text-xs">{bookmark.period}</p> : null}
                                        <h2 className="font-medium">{bookmark.title}</h2>
                                        <p className="text-muted-foreground mt-1 text-sm">{bookmark.summary}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.delete(route('bookmarks.destroy', bookmark.id))}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </FintechPage>
        </AppLayout>
    );
}
