import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { RecurringTransactionForm } from '@/components/recurring/recurring-transaction-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption, type FrequencyOption, type TypeOption } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recurring', href: '/recurring-transactions' },
    { title: 'Create', href: '/recurring-transactions/create' },
];

type Props = {
    typeOptions: TypeOption[];
    frequencyOptions: FrequencyOption[];
    categories: CategoryOption[];
    today: string;
};

export default function CreateRecurring({ typeOptions, frequencyOptions, categories, today }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create recurring transaction" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Create recurring" description="Schedule a repeating income or expense" />
                <RecurringTransactionForm
                    typeOptions={typeOptions}
                    frequencyOptions={frequencyOptions}
                    categories={categories}
                    today={today}
                    submitLabel="Create"
                    cancelHref={route('recurring-transactions.index')}
                    onSubmit={(form) => {
                        if (form.processing) {
                            return;
                        }

                        form.transform((data) => ({
                            ...data,
                            end_date: data.end_date?.trim() ? data.end_date : null,
                        }));
                        form.post(route('recurring-transactions.store'));
                    }}
                />
            </FintechPage>
        </AppLayout>
    );
}
