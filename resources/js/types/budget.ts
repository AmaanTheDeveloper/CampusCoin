export type BudgetStatusValue = 'healthy' | 'near_limit' | 'exceeded';

export type BudgetItem = {
    id: number;
    category: {
        id: number;
        name: string;
        is_default: boolean;
    };
    month: string;
    month_label: string;
    limit: string;
    spent: string;
    remaining: string;
    over_budget: string;
    percentage: number;
    status: BudgetStatusValue;
    status_label: string;
};

export type AppNotification = {
    id: string;
    type: string;
    title?: string | null;
    message: string;
    category_name: string | null;
    budget_id: number | null;
    occurrence_id?: number | null;
    transaction_id?: number | null;
    action_url?: string | null;
    action_label?: string | null;
    month_label: string | null;
    percentage: number | null;
    play_sound?: boolean;
    read_at: string | null;
    created_at: string | null;
    is_unread: boolean;
};
