import { AdminIntelligenceVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'AI Intelligence', href: '/admin/intelligence' },
];

type Props = {
    intelligence: {
        categorization: {
            has_data: boolean;
            suggestions: number;
            accepted: number;
            corrected: number;
            acceptance_rate: number | null;
            disclaimer: string;
        };
        confusion_pairs: Array<{ from: string; to: string; count: number }>;
        signals: {
            open_duplicates: number;
            open_unusual: number;
            resolved_duplicates: number;
        };
        recent_feedback: Array<{
            id: number;
            accepted: boolean;
            suggested: string | null;
            selected: string | null;
            created_at: string | null;
        }>;
    };
};

export default function AdminIntelligence({ intelligence }: Props) {
    const { categorization, confusion_pairs, signals, recent_feedback } = intelligence;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Intelligence" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Insights"
                    title="AI Intelligence"
                    description="Categorization feedback quality and open intelligence signals across the student base."
                    visual={<AdminIntelligenceVisual />}
                />

                <p className="text-muted-foreground max-w-3xl text-sm">{categorization.disclaimer}</p>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Suggestions</p>
                        <p className="cc-admin-kpi__value">{categorization.suggestions.toLocaleString()}</p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Accepted</p>
                        <p className="cc-admin-kpi__value">{categorization.accepted.toLocaleString()}</p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Corrected</p>
                        <p className="cc-admin-kpi__value">{categorization.corrected.toLocaleString()}</p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Acceptance rate</p>
                        <p className="cc-admin-kpi__value">{categorization.acceptance_rate === null ? '—' : `${categorization.acceptance_rate}%`}</p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Open duplicates</p>
                        <p className="cc-admin-kpi__value">{signals.open_duplicates.toLocaleString()}</p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Open unusual</p>
                        <p className="cc-admin-kpi__value">{signals.open_unusual.toLocaleString()}</p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Resolved duplicates</p>
                        <p className="cc-admin-kpi__value">{signals.resolved_duplicates.toLocaleString()}</p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <FintechPanel>
                        <FintechSectionHeader title="Confusion pairs" meta="Suggested vs selected categories" />
                        <div className="mt-3">
                            {!categorization.has_data || confusion_pairs.length === 0 ? (
                                <FintechEmpty title="No confusion pairs yet" description="Pairs appear when students correct category suggestions." />
                            ) : (
                                <ul className="space-y-2">
                                    {confusion_pairs.map((pair, index) => (
                                        <li
                                            key={`${pair.from}-${pair.to}-${index}`}
                                            className="flex items-center justify-between gap-3 border-b border-white/5 py-2 text-sm last:border-0"
                                        >
                                            <span>
                                                <span className="font-medium">{pair.from}</span>
                                                <span className="text-muted-foreground"> → </span>
                                                <span className="font-medium">{pair.to}</span>
                                            </span>
                                            <Badge variant="outline" className="tabular-nums">
                                                {pair.count}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </FintechPanel>

                    <FintechPanel>
                        <FintechSectionHeader title="Recent feedback" meta="Latest suggestion outcomes" />
                        <div className="mt-3">
                            {recent_feedback.length === 0 ? (
                                <FintechEmpty title="No feedback yet" description="Student responses to category suggestions will show here." />
                            ) : (
                                <ul className="space-y-2">
                                    {recent_feedback.map((item) => (
                                        <li key={item.id} className="border-b border-white/5 py-2 text-sm last:border-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant={item.accepted ? 'default' : 'outline'}>
                                                    {item.accepted ? 'Accepted' : 'Corrected'}
                                                </Badge>
                                                {item.created_at ? (
                                                    <time className="text-muted-foreground text-xs" dateTime={item.created_at}>
                                                        {new Date(item.created_at).toLocaleString()}
                                                    </time>
                                                ) : null}
                                            </div>
                                            <p className="mt-1">
                                                <span className="text-muted-foreground">Suggested: </span>
                                                {item.suggested ?? '—'}
                                            </p>
                                            <p>
                                                <span className="text-muted-foreground">Selected: </span>
                                                {item.selected ?? '—'}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </FintechPanel>
                </div>
            </FintechPage>
        </AdminLayout>
    );
}
