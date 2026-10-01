import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { RecurringTransactionForm } from '@/components/recurring/recurring-transaction-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption, type FrequencyOption, type TypeOption } from '@/types';
import { Head } from '@inertiajs/react';

type Props = {
    recurringTransaction: {
        id: number;
        type: 'income' | 'expense';
        category_id: number;
        amount: string;
        description: string | null;
        frequency: string;
        start_date: string;
        end_date: string | null;
        is_active: boolean;
    };
    typeOptions: TypeOption[];
    frequencyOptions: FrequencyOption[];
    categories: CategoryOption[];
    today: string;
};

export default function EditRecurring({ recurringTransaction, typeOptions, frequencyOptions, categories, today }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Recurring', href: '/recurring-transactions' },
        { title: 'Edit', href: `/recurring-transactions/${recurringTransaction.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit recurring transaction" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Edit recurring" description="Update this repeating rule" />
                <RecurringTransactionForm
                    typeOptions={typeOptions}
                    frequencyOptions={frequencyOptions}
                    categories={categories}
                    today={today}
                    initial={{
                        type: recurringTransaction.type,
                        category_id: String(recurringTransaction.category_id),
                        amount: String(recurringTransaction.amount),
                        description: recurringTransaction.description ?? '',
                        frequency: recurringTransaction.frequency,
                        start_date: recurringTransaction.start_date,
                        end_date: recurringTransaction.end_date ?? '',
                        is_active: recurringTransaction.is_active,
                    }}
                    submitLabel="Save changes"
                    cancelHref={route('recurring-transactions.index')}
                    onSubmit={(form) => {
                        if (form.processing) {
                            return;
                        }

                        form.transform((data) => ({
                            ...data,
                            end_date: data.end_date?.trim() ? data.end_date : null,
                        }));
                        form.put(route('recurring-transactions.update', recurringTransaction.id));
                    }}
                />
            </FintechPage>
        </AppLayout>
    );
}
