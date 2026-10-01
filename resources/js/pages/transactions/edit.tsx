import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { TransactionForm } from '@/components/transactions/transaction-form';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CategoryOption, type Transaction, type TypeOption } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs = (id: number): BreadcrumbItem[] => [
    { title: 'Transactions', href: '/transactions' },
    { title: 'Edit', href: `/transactions/${id}/edit` },
];

type EditProps = {
    transaction: Transaction;
    typeOptions: TypeOption[];
    categories: CategoryOption[];
    aiAvailable?: boolean;
    today: string;
    available_balance: string;
};

export default function EditTransaction({ transaction, typeOptions, categories, aiAvailable = false, today, available_balance }: EditProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(transaction.id)}>
            <Head title="Edit transaction" />
            <FintechPage>
                <FintechPageHeader eyebrow="Finance" title="Edit transaction" description="Update this income or expense entry" />
                <TransactionForm
                    typeOptions={typeOptions}
                    categories={categories}
                    aiAvailable={aiAvailable}
                    autoSuggest={false}
                    today={today}
                    availableBalance={available_balance}
                    initial={{
                        type: transaction.type,
                        category_id: transaction.category_id ? String(transaction.category_id) : '',
                        amount: String(transaction.amount),
                        description: transaction.description ?? '',
                        transaction_date: transaction.transaction_date,
                    }}
                    submitLabel="Save changes"
                    cancelHref={route('transactions.index')}
                    onSubmit={(form) => {
                        form.put(route('transactions.update', transaction.id));
                    }}
                />
            </FintechPage>
        </AppLayout>
    );
}
