import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { TransactionForm } from '@/components/transactions/transaction-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption, type TransactionTypeValue, type TypeOption } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '/transactions' },
    { title: 'Add', href: '/transactions/create' },
];

type CreateProps = {
    typeOptions: TypeOption[];
    categories: CategoryOption[];
    initialType?: TransactionTypeValue | null;
    aiAvailable?: boolean;
    today: string;
    available_balance: string;
};

export default function CreateTransaction({ typeOptions, categories, initialType, aiAvailable = false, today, available_balance }: CreateProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add transaction" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Add transaction" description="Record an income or expense entry" />
                <TransactionForm
                    typeOptions={typeOptions}
                    categories={categories}
                    aiAvailable={aiAvailable}
                    autoSuggest
                    today={today}
                    availableBalance={available_balance}
                    initial={{
                        type: initialType ?? 'expense',
                        transaction_date: today,
                    }}
                    submitLabel="Add transaction"
                    cancelHref={route('transactions.index')}
                    onSubmit={(form) => {
                        form.post(route('transactions.store'));
                    }}
                />
            </FintechPage>
        </AppLayout>
    );
}
