import { FintechFlash, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock3, Landmark } from 'lucide-react';

type Props = {
    bank: {
        name: string;
        reference_code: string;
        status: string;
        status_label: string;
        submitted_at: string | null;
        rejection_reason: string | null;
    };
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export default function BankRegistrationStatus({ bank }: Props) {
    const isPending = bank.status === 'pending' || bank.status === 'under_review';

    return (
        <PublicLayout
            title="Registration submitted"
            description="Your bank registration request was submitted and is awaiting platform review."
        >
            <Head title="Registration submitted" />
            <div className="mx-auto w-full max-w-2xl px-4 py-10 md:py-14">
                <FintechPage stagger={false} className="p-0 md:p-0">
                    <FintechPageHeader
                        eyebrow="Bank registration"
                        title="Registration request submitted"
                        description={`${bank.name} is now in the platform review queue. You cannot open the Bank Portal until a Super Admin approves this request.`}
                        visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                    />

                    <FintechPanel>
                        <div className="flex items-start gap-3">
                            {isPending ? (
                                <Clock3 className="mt-0.5 size-5 text-[#E94F09]" aria-hidden />
                            ) : (
                                <CheckCircle2 className="mt-0.5 size-5 text-[#E94F09]" aria-hidden />
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="cc-text-tertiary text-xs uppercase tracking-wide">Current status</p>
                                <div className="mt-2">
                                    <LoanStatusBadge status={bank.status} label={bank.status_label} />
                                </div>
                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="cc-text-tertiary text-xs uppercase tracking-wide">Request ID</dt>
                                        <dd className="mt-1 font-mono text-white">{bank.reference_code}</dd>
                                    </div>
                                    <div>
                                        <dt className="cc-text-tertiary text-xs uppercase tracking-wide">Submitted</dt>
                                        <dd className="mt-1 text-white">{formatDate(bank.submitted_at)}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </FintechPanel>

                    {isPending ? (
                        <FintechFlash tone="warning">
                            A platform administrator must review and approve your bank before you can log in to the Bank Portal, create loan products, or
                            receive student applications.
                        </FintechFlash>
                    ) : null}

                    {bank.status === 'rejected' && bank.rejection_reason ? (
                        <FintechFlash tone="error">This request was rejected: {bank.rejection_reason}</FintechFlash>
                    ) : null}

                    {bank.status === 'approved' ? (
                        <FintechFlash>
                            Your bank is approved. You can log in with the representative email and password you created during registration.
                        </FintechFlash>
                    ) : null}

                    <div className="flex flex-wrap gap-2">
                        <Button asChild className="cc-dash-btn-primary min-h-11">
                            <Link href={route('home')}>Return to website</Link>
                        </Button>
                        {bank.status === 'approved' ? (
                            <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('login')}>Log in to Bank Portal</Link>
                            </Button>
                        ) : (
                            <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('login')}>Check later via Log in</Link>
                            </Button>
                        )}
                    </div>
                </FintechPage>
            </div>
        </PublicLayout>
    );
}
