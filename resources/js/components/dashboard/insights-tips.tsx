import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MonthlyInsightPanel, SavingTipCard } from '@/types/dashboard';
import { Link, router } from '@inertiajs/react';
import { Bookmark, BookmarkCheck, RefreshCw, X } from 'lucide-react';

export function MonthlyInsightSection({ panel }: { panel: MonthlyInsightPanel }) {
    const display = panel.insight ?? {
        ...panel.factual_fallback,
        id: 0,
        period: panel.period,
        period_label: panel.period_label,
        generated_source: 'fallback',
        status: 'ready',
        generated_at: null,
        is_bookmarked: false,
    };

    const isPersisted = Boolean(panel.insight);

    return (
        <section className="border-border bg-card rounded-xl border p-4 shadow-sm" aria-labelledby="ai-insight-heading">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">AI monthly insight</p>
                    <h2 id="ai-insight-heading" className="text-lg font-semibold">
                        {panel.period_label}
                    </h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        {display.generated_source === 'ai'
                            ? 'AI interpretation of your factual spending data — advisory only.'
                            : 'Factual snapshot from your transactions (AI interpretation unavailable or not generated).'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" size="sm" variant="outline" asChild>
                        <Link href={route('insights.index')}>History</Link>
                    </Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => router.post(route('insights.regenerate'))}>
                        <RefreshCw className="mr-2 size-4" aria-hidden />
                        {isPersisted ? 'Regenerate' : 'Generate'}
                    </Button>
                    {isPersisted ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            aria-pressed={display.is_bookmarked}
                            aria-label={display.is_bookmarked ? 'Remove insight bookmark' : 'Bookmark insight'}
                            onClick={() =>
                                router.post(route('bookmarks.toggle'), {
                                    type: 'insight',
                                    id: display.id,
                                })
                            }
                        >
                            {display.is_bookmarked ? <BookmarkCheck className="size-4" /> : <Bookmark className="size-4" />}
                        </Button>
                    ) : null}
                </div>
            </div>

            <h3 className="font-medium">{display.headline}</h3>
            <p className="text-muted-foreground mt-2 text-sm">{display.summary}</p>

            {display.trends.length > 0 ? (
                <ul className="mt-4 space-y-1 text-sm">
                    {display.trends.map((trend) => (
                        <li key={`${trend.label}-${trend.direction}`} className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{trend.direction}</Badge>
                            <span>{trend.label}</span>
                        </li>
                    ))}
                </ul>
            ) : null}

            {display.actions.length > 0 ? (
                <div className="mt-4">
                    <p className="text-xs font-medium tracking-wide uppercase">Suggested actions</p>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                        {display.actions.map((action) => (
                            <li key={action}>{action}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}

export function SavingTipsSection({ tips }: { tips: SavingTipCard[] }) {
    if (tips.length === 0) {
        return (
            <section className="border-border bg-card rounded-xl border p-4 shadow-sm" aria-labelledby="tips-heading">
                <h2 id="tips-heading" className="font-semibold">
                    Personalized saving tips
                </h2>
                <p className="text-muted-foreground mt-2 text-sm">No tips right now. Keep tracking spending to unlock personalized suggestions.</p>
            </section>
        );
    }

    return (
        <section className="border-border bg-card rounded-xl border p-4 shadow-sm" aria-labelledby="tips-heading">
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 id="tips-heading" className="font-semibold">
                    Personalized saving tips
                </h2>
                <Button type="button" size="sm" variant="outline" asChild>
                    <Link href={route('bookmarks.index')}>Bookmarks</Link>
                </Button>
            </div>
            <ul className="space-y-3">
                {tips.map((tip) => (
                    <li key={tip.id} className="border-border rounded-lg border p-3">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                    <Badge variant={tip.impact_level === 'high' ? 'default' : 'secondary'}>{tip.impact_level} impact</Badge>
                                    <h3 className="font-medium">{tip.title}</h3>
                                </div>
                                <p className="text-muted-foreground text-sm">{tip.body}</p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    aria-label={tip.is_bookmarked ? 'Remove tip bookmark' : 'Bookmark tip'}
                                    aria-pressed={tip.is_bookmarked}
                                    onClick={() =>
                                        router.post(route('bookmarks.toggle'), {
                                            type: 'tip',
                                            id: tip.id,
                                        })
                                    }
                                >
                                    {tip.is_bookmarked ? <BookmarkCheck className="size-4" /> : <Bookmark className="size-4" />}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    aria-label={`Dismiss tip: ${tip.title}`}
                                    onClick={() => router.post(route('saving-tips.dismiss', tip.id))}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
            <p className="text-muted-foreground mt-3 text-xs">Tips are advisory suggestions based on your data — not certified financial advice.</p>
        </section>
    );
}
