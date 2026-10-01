import { SavingsGoalVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { FintechFlash, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { SavingsGoalDetail, SavingsGoalTrackerPanel } from '@/types/dashboard';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Savings Goal', href: '/savings-goal' },
];

type Props = {
    panel: SavingsGoalTrackerPanel | null;
    error: string | null;
};

export default function SavingsGoalPage({ panel, error }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [scenarioOpen, setScenarioOpen] = useState(false);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Savings Goal" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Objective"
                    title="Savings Goal"
                    description="A living long-term goal with progress, completion estimate, and a spending scenario — separate from your profile monthly savings goal used by Money Pulse."
                    visual={<SavingsGoalVisual percent={panel?.goal?.progress_percent ?? 0} className="size-16" />}
                    actions={
                        <Button type="button" variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Back to dashboard</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Savings goal unavailable</p>
                        <p className="mt-1 text-[#b8b8b8]">{error}</p>
                    </div>
                ) : null}

                {panel && !panel.has_goal ? <CreateGoalForm message={panel.empty_message} /> : null}
                {panel?.goal ? (
                    <GoalDetail goal={panel.goal} scenarioOpen={scenarioOpen} onToggleScenario={() => setScenarioOpen((open) => !open)} />
                ) : null}
            </FintechPage>
        </AppLayout>
    );
}

function CreateGoalForm({ message }: { message?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        target_amount: '',
        starting_amount: '0',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('savings-goal.store'));
    };

    return (
        <FintechFormShell className="mx-auto w-full max-w-lg">
            <div className="mb-1 flex justify-center">
                <SavingsGoalVisual percent={0} className="size-20" />
            </div>
            <h2 className="text-lg font-semibold text-white">Create a Savings Goal</h2>
            <p className="cc-text-secondary mt-1 text-sm">{message}</p>
            <form onSubmit={submit} className="mt-4 space-y-4">
                <div className="grid gap-1.5">
                    <Label htmlFor="goal-name">Goal name</Label>
                    <Input
                        id="goal-name"
                        className="cc-field"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="New Laptop Fund"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="goal-target">Target amount</Label>
                    <Input
                        id="goal-target"
                        type="number"
                        min="0.01"
                        step="0.01"
                        className="cc-field"
                        value={data.target_amount}
                        onChange={(e) => setData('target_amount', e.target.value)}
                        placeholder="100000.00"
                    />
                    <InputError message={errors.target_amount} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="goal-start">Starting saved amount</Label>
                    <Input
                        id="goal-start"
                        type="number"
                        min="0"
                        step="0.01"
                        className="cc-field"
                        value={data.starting_amount}
                        onChange={(e) => setData('starting_amount', e.target.value)}
                    />
                    <InputError message={errors.starting_amount} />
                </div>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Creating…' : 'Create goal'}
                </Button>
            </form>
        </FintechFormShell>
    );
}

function GoalDetail({ goal, scenarioOpen, onToggleScenario }: { goal: SavingsGoalDetail; scenarioOpen: boolean; onToggleScenario: () => void }) {
    const [detailsOpen, setDetailsOpen] = useState(false);

    return (
        <div className="space-y-6">
            <section className="cc-card-hero p-6 md:p-8">
                <div className="flex flex-col items-center gap-6 md:flex-row md:items-center md:justify-between">
                    <div className="cc-hero-loop shrink-0">
                        <SavingsGoalVisual percent={goal.progress_percent} className="size-36 md:size-40" />
                    </div>
                    <div className="min-w-0 flex-1 text-center md:text-left">
                        <div className="flex flex-wrap items-center justify-center gap-2 md:justify-start">
                            <p className="cc-label">{goal.name}</p>
                            <Badge variant="outline">{goal.status_label}</Badge>
                        </div>
                        <p className="mt-3 text-5xl font-semibold tracking-tight text-white tabular-nums">{goal.progress_percent}%</p>
                        <p className="mt-3 text-lg font-semibold text-white tabular-nums">
                            {formatMoney(goal.current_amount)} <span className="cc-text-tertiary font-normal">/</span>{' '}
                            {formatMoney(goal.target_amount)}
                        </p>
                        <p className="cc-text-secondary mt-2 text-sm tabular-nums">{formatMoney(goal.remaining_amount)} remaining</p>
                        {goal.is_completed ? (
                            <p className="mt-4 text-sm font-medium text-white">Goal reached — great work!</p>
                        ) : goal.completion_estimate ? (
                            <p className="mt-4 text-sm text-white">
                                Estimated completion <span className="font-semibold text-[#E94F09]">{goal.completion_estimate.label}</span>
                            </p>
                        ) : (
                            <p className="cc-text-secondary mt-4 text-sm">{goal.savings_rate.explanation ?? 'Estimated completion unavailable.'}</p>
                        )}
                    </div>
                </div>

                <div className="mt-6 border-t border-white/8 pt-4">
                    <Button type="button" variant="ghost" size="sm" onClick={() => setDetailsOpen((o) => !o)}>
                        {detailsOpen ? 'Hide calculation details' : 'How this estimate is calculated'}
                    </Button>
                    {detailsOpen ? (
                        <div className="cc-text-secondary mt-3 space-y-2 text-xs leading-relaxed">
                            {goal.completion_estimate?.explanation ? <p>{goal.completion_estimate.explanation}</p> : null}
                            {goal.savings_rate.monthly_rate ? (
                                <p>
                                    Monthly savings rate: {formatMoney(goal.savings_rate.monthly_rate)} ({goal.savings_rate.source})
                                </p>
                            ) : null}
                            <p className="cc-text-tertiary">{goal.disclaimer}</p>
                        </div>
                    ) : null}
                </div>
            </section>

            <div className="grid gap-4 lg:grid-cols-2">
                {!goal.is_completed ? <ContributeForm goalId={goal.id} /> : null}
                <EditGoalForm goal={goal} />
            </div>

            {goal.smart_scenario?.available ? (
                <FintechPanel className="cc-elev-2">
                    <h3 className="font-semibold text-white">How can I reach it faster?</h3>
                    <p className="mt-2 text-sm text-[#cfcfcf]">{goal.smart_scenario.headline}</p>
                    <p className="cc-text-secondary mt-1 text-xs">{goal.smart_scenario.explanation}</p>
                    <Button type="button" variant="outline" size="sm" className="mt-3" onClick={onToggleScenario}>
                        {scenarioOpen ? 'Hide details' : 'Why this scenario?'}
                    </Button>
                    {scenarioOpen ? (
                        <dl className="mt-4 grid gap-2 border-t border-white/8 pt-4 text-sm sm:grid-cols-2">
                            <Detail label="Category average" value={formatMoney(goal.smart_scenario.category_monthly_average)} />
                            <Detail label="Suggested reduction" value={formatMoney(goal.smart_scenario.suggested_reduction)} />
                            <Detail label="Current savings rate" value={formatMoney(goal.smart_scenario.baseline_monthly_savings)} />
                            <Detail label="Scenario savings rate" value={formatMoney(goal.smart_scenario.scenario_monthly_savings)} />
                            <Detail label="Current estimate" value={goal.smart_scenario.baseline_label} />
                            <Detail label="Scenario estimate" value={goal.smart_scenario.scenario_label} />
                            <Detail label="Months saved" value={`${goal.smart_scenario.months_saved}`} />
                        </dl>
                    ) : null}
                    {scenarioOpen ? <p className="cc-text-tertiary mt-3 text-xs">{goal.smart_scenario.formula}</p> : null}
                </FintechPanel>
            ) : null}

            <FintechPanel>
                <h3 className="font-semibold text-white">Contribution history</h3>
                {goal.contributions.length === 0 ? (
                    <p className="mt-2 text-sm text-[#8a8a8a]">No contributions yet.</p>
                ) : (
                    <ul className="mt-3 space-y-2">
                        {goal.contributions.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-wrap items-baseline justify-between gap-2 border-b border-white/8 py-2 text-sm last:border-0"
                            >
                                <div>
                                    <p className="font-medium text-white tabular-nums">{formatMoney(row.amount)}</p>
                                    {row.note ? <p className="text-xs text-[#8a8a8a]">{row.note}</p> : null}
                                </div>
                                <p className="text-xs text-[#737373] tabular-nums">
                                    {row.contributed_at ? new Date(row.contributed_at).toLocaleString() : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </FintechPanel>
        </div>
    );
}

function ContributeForm({ goalId }: { goalId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        note: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('savings-goal.contribute', goalId), {
            onSuccess: () => reset('amount', 'note'),
        });
    };

    return (
        <FintechPanel>
            <h3 className="font-semibold text-white">Add contribution</h3>
            <form onSubmit={submit} className="mt-3 grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="contrib-amount">Amount</Label>
                    <Input
                        id="contrib-amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                    />
                    <InputError message={errors.amount} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="contrib-note">Note (optional)</Label>
                    <Input id="contrib-note" value={data.note} onChange={(e) => setData('note', e.target.value)} placeholder="Freelance payment" />
                    <InputError message={errors.note} />
                </div>
                <div className="sm:col-span-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Add to goal'}
                    </Button>
                </div>
            </form>
        </FintechPanel>
    );
}

function EditGoalForm({ goal }: { goal: SavingsGoalDetail }) {
    const { data, setData, put, processing, errors } = useForm({
        name: goal.name,
        target_amount: goal.target_amount,
    });

    useEffect(() => {
        setData({
            name: goal.name,
            target_amount: goal.target_amount,
        });
    }, [goal.name, goal.target_amount, setData]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('savings-goal.update', goal.id));
    };

    return (
        <FintechPanel>
            <h3 className="font-semibold text-white">Edit goal</h3>
            <form onSubmit={submit} className="mt-3 grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="edit-name">Name</Label>
                    <Input id="edit-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="edit-target">Target amount</Label>
                    <Input
                        id="edit-target"
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={data.target_amount}
                        onChange={(e) => setData('target_amount', e.target.value)}
                    />
                    <InputError message={errors.target_amount} />
                </div>
                <div className="sm:col-span-2">
                    <Button type="submit" variant="secondary" disabled={processing}>
                        {processing ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </form>
        </FintechPanel>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}
