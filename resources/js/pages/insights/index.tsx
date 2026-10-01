import { FintechEmpty, FintechPage } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { MonthlyInsightCard, MonthlyInsightPanel } from '@/types/dashboard';
import { Head, Link, router } from '@inertiajs/react';
import { Bookmark, BookmarkCheck, RefreshCw } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Insights', href: '/insights' }];

type Props = {
    insights: MonthlyInsightCard[];
    current: MonthlyInsightPanel;
    aiAvailable: boolean;
};

export default function InsightsIndex({ insights, current, aiAvailable }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monthly insights" />
            <FintechPage>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Monthly insights</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            AI interpretation of your spending facts — advisory only.
                            {!aiAvailable ? ' AI is currently unavailable; factual snapshots remain available.' : ''}
                        </p>
                    </div>
                    <Button type="button" onClick={() => router.post(route('insights.regenerate'))}>
                        <RefreshCw className="mr-2 size-4" aria-hidden />
                        Generate / refresh {current.period_label}
                    </Button>
                </div>

                <section className="border-border bg-card rounded-xl border p-4 shadow-sm">
                    <p className="text-muted-foreground text-xs uppercase">Current period</p>
                    <h2 className="text-lg font-semibold">{current.period_label}</h2>
                    {current.insight ? (
                        <InsightBlock insight={current.insight} />
                    ) : (
                        <div className="mt-3">
                            <h3 className="font-medium">{current.factual_fallback.headline}</h3>
                            <p className="text-muted-foreground mt-2 text-sm">{current.factual_fallback.summary}</p>
                        </div>
                    )}
                </section>

                <section>
                    <h2 className="mb-3 font-semibold">History</h2>
                    {insights.length === 0 ? (
                        <FintechEmpty title="No saved insights yet" description="Generate a monthly insight to start building your history." />
                    ) : (
                        <ul className="space-y-3">
                            {insights.map((insight) => (
                                <li key={insight.id} className="border-border bg-card rounded-xl border p-4 shadow-sm">
                                    <InsightBlock insight={insight} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <div>
                    <Button asChild variant="outline" className="w-auto">
                        <Link href={route('bookmarks.index')}>View bookmarks</Link>
                    </Button>
                </div>
            </FintechPage>
        </AppLayout>
    );
}

function InsightBlock({ insight }: { insight: MonthlyInsightCard }) {
    return (
        <div className="mt-2">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-muted-foreground text-xs">{insight.period_label}</p>
                    <h3 className="font-medium">{insight.headline}</h3>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="secondary">{insight.generated_source}</Badge>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        aria-pressed={insight.is_bookmarked}
                        aria-label={insight.is_bookmarked ? 'Remove bookmark' : 'Bookmark insight'}
                        onClick={() =>
                            router.post(route('bookmarks.toggle'), {
                                type: 'insight',
                                id: insight.id,
                            })
                        }
                    >
                        {insight.is_bookmarked ? <BookmarkCheck className="size-4" /> : <Bookmark className="size-4" />}
                    </Button>
                </div>
            </div>
            <p className="text-muted-foreground mt-2 text-sm">{insight.summary}</p>
            {insight.trends.length > 0 ? (
                <ul className="mt-3 space-y-1 text-sm">
                    {insight.trends.map((trend) => (
                        <li key={`${insight.id}-${trend.label}`}>
                            <Badge variant="outline" className="mr-2">
                                {trend.direction}
                            </Badge>
                            {trend.label}
                        </li>
                    ))}
                </ul>
            ) : null}
            {insight.actions.length > 0 ? (
                <ul className="mt-3 list-disc space-y-1 pl-5 text-sm">
                    {insight.actions.map((action) => (
                        <li key={`${insight.id}-${action}`}>{action}</li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
