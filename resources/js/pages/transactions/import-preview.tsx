import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '/transactions' },
    { title: 'Import', href: '/transactions/import' },
    { title: 'Preview', href: '#' },
];

type AiSuggestion = {
    category_id: number;
    category_name: string;
    confidence: string;
    reason: string;
    source: string;
};

type PreviewRow = {
    row_number: number;
    date: string | null;
    type: string | null;
    amount: string | null;
    description: string | null;
    category_name: string | null;
    category_id: number | null;
    ai_suggestion: AiSuggestion | null;
    status: 'valid' | 'warning' | 'error';
    messages: string[];
};

type Props = {
    token: string;
    rows: PreviewRow[];
    summary: {
        total: number;
        valid: number;
        invalid: number;
        warnings: number;
    };
    categoryOptions?: CategoryOption[];
    aiAvailable?: boolean;
};

export default function ImportPreview({ token, rows, summary, categoryOptions = [], aiAvailable = false }: Props) {
    const idempotencyKey = useMemo(() => crypto.randomUUID(), []);

    const initialOverrides = useMemo(() => {
        const map: Record<string, string> = {};
        rows.forEach((row) => {
            if (row.category_id) {
                map[String(row.row_number)] = String(row.category_id);
            } else if (row.ai_suggestion) {
                map[String(row.row_number)] = '';
            } else {
                map[String(row.row_number)] = '';
            }
        });
        return map;
    }, [rows]);

    const [overrides, setOverrides] = useState<Record<string, string>>(initialOverrides);

    const form = useForm({
        token,
        idempotency_key: idempotencyKey,
        category_overrides: {} as Record<string, number | null>,
    });

    const acceptHighConfidence = () => {
        setOverrides((current) => {
            const next = { ...current };
            rows.forEach((row) => {
                if (!row.category_id && row.ai_suggestion?.confidence === 'high') {
                    next[String(row.row_number)] = String(row.ai_suggestion.category_id);
                }
            });
            return next;
        });
    };

    const confirmImport = () => {
        const payload: Record<string, number | null> = {};
        Object.entries(overrides).forEach(([rowNumber, value]) => {
            payload[rowNumber] = value === '' ? null : Number(value);
        });
        form.transform(() => ({
            token,
            idempotency_key: idempotencyKey,
            category_overrides: payload,
        }));
        form.post(route('transactions.import.confirm'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import preview" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Import preview"
                    description="Review rows before importing. Nothing is saved until you confirm."
                />

                <div className="grid gap-3 sm:grid-cols-4">
                    <Stat label="Rows detected" value={summary.total} />
                    <Stat label="Valid rows" value={summary.valid} />
                    <Stat label="Invalid rows" value={summary.invalid} />
                    <Stat label="Warnings" value={summary.warnings} />
                </div>

                {aiAvailable ? (
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={acceptHighConfidence}>
                            Accept all high-confidence AI suggestions
                        </Button>
                        <p className="text-muted-foreground self-center text-xs">AI suggestions are optional — review before importing.</p>
                    </div>
                ) : null}

                <div className="border-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 border-border border-b">
                            <tr>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Amount</th>
                                <th className="px-3 py-2 font-medium">Description</th>
                                <th className="px-3 py-2 font-medium">Current</th>
                                <th className="px-3 py-2 font-medium">AI suggested</th>
                                <th className="px-3 py-2 font-medium">Final category</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const key = String(row.row_number);
                                const typeCategories = categoryOptions.filter((c) => !row.type || c.type === row.type);

                                return (
                                    <tr key={row.row_number} className="border-border border-b align-top last:border-0">
                                        <td className="px-3 py-2 whitespace-nowrap">{row.date ?? '—'}</td>
                                        <td className="px-3 py-2 capitalize">{row.type ?? '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">{row.amount ?? '—'}</td>
                                        <td className="px-3 py-2">{row.description ?? '—'}</td>
                                        <td className="px-3 py-2">{row.category_name ?? 'Uncategorized'}</td>
                                        <td className="px-3 py-2">
                                            {row.ai_suggestion ? (
                                                <div>
                                                    <p>{row.ai_suggestion.category_name}</p>
                                                    <p className="text-muted-foreground text-xs capitalize">{row.ai_suggestion.confidence}</p>
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.status === 'error' ? (
                                                '—'
                                            ) : (
                                                <select
                                                    className="border-input bg-background h-9 w-full min-w-[140px] rounded-md border px-2 text-sm"
                                                    value={overrides[key] ?? ''}
                                                    onChange={(e) => setOverrides((prev) => ({ ...prev, [key]: e.target.value }))}
                                                    aria-label={`Final category for row ${row.row_number}`}
                                                >
                                                    <option value="">Uncategorized</option>
                                                    {typeCategories.map((category) => (
                                                        <option key={category.id} value={category.id}>
                                                            {category.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={row.status === 'error' ? 'destructive' : row.status === 'warning' ? 'secondary' : 'default'}
                                            >
                                                {row.status}
                                            </Badge>
                                            {row.messages.length > 0 && (
                                                <ul className="text-muted-foreground mt-1 list-disc pl-4 text-xs">
                                                    {row.messages.map((message) => (
                                                        <li key={message}>{message}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Button type="button" disabled={form.processing || summary.valid === 0} onClick={confirmImport}>
                        {form.processing ? 'Importing…' : `Import ${summary.valid} valid row(s)`}
                    </Button>
                    <Button type="button" variant="outline" asChild>
                        <Link href={route('transactions.import.create')}>Back</Link>
                    </Button>
                </div>
            </FintechPage>
        </AppLayout>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="border-border rounded-lg border px-4 py-3">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}
