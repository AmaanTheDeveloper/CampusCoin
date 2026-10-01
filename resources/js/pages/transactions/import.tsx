import { FintechFlash, FintechFormShell, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '/transactions' },
    { title: 'Import', href: '/transactions/import' },
];

type Props = {
    limits: {
        max_size_kb: number;
        max_rows: number;
    };
};

export default function ImportTransactions({ limits }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const form = useForm<{ csv: File | null }>({
        csv: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing || !form.data.csv) {
            return;
        }

        form.post(route('transactions.import.preview'), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import transactions" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Import CSV" description="Upload a CSV to preview rows before anything is saved." />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <FintechFormShell as="form" onSubmit={submit}>
                    <div className="grid gap-1.5">
                        <Label htmlFor="csv">CSV file</Label>
                        <Input
                            id="csv"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => form.setData('csv', e.target.files?.[0] ?? null)}
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.csv)}
                        />
                        <p className="cc-text-secondary text-xs">
                            Headers: date, type, amount, description, category. Max {limits.max_size_kb} KB · up to {limits.max_rows} rows.
                        </p>
                        <InputError message={form.errors.csv} />
                    </div>
                    <Button type="submit" disabled={form.processing || !form.data.csv} aria-busy={form.processing}>
                        {form.processing ? 'Uploading…' : 'Upload & preview'}
                    </Button>
                </FintechFormShell>
            </FintechPage>
        </AppLayout>
    );
}
