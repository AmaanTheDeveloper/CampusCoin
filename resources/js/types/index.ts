import { type LucideIcon } from 'lucide-react';

export type UserRole = 'student' | 'admin';

export type AcademicYearValue = 'year_1' | 'year_2' | 'year_3' | 'year_4' | 'graduate' | 'other';

export type TransactionTypeValue = 'income' | 'expense';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badgeKey?: 'pending_duplicate_count';
    /** Quieter secondary destinations (e.g. Import under Finance) */
    secondary?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    unread_notifications_count?: number;
    recurring_notification_sound_id?: string | null;
    admin_unread_notifications_count?: number;
    admin_notifications_preview?: import('@/lib/admin-notifications').AdminNotificationItem[];
    pending_duplicate_count?: number;
    flash?: {
        success?: string | null;
        error?: string | null;
        payment_result?: {
            outcome: 'succeeded' | 'failed';
            amount?: string | null;
            reference?: string | null;
            installment_number?: number | null;
            paid_at?: string | null;
            remaining_balance?: string | null;
            reason?: string | null;
        } | null;
        contact_success?: string | null;
        archived_transaction_id?: number | null;
        spending_alert?: import('@/types/intelligence').IntelligenceSignal | null;
        import_result?: {
            imported?: number;
            skipped?: number;
            errors?: number;
            warnings?: number;
            possible_duplicates?: number;
        } | null;
        budget_overrun?: {
            exceeds: boolean;
            budget_id: number;
            category_name: string;
            month_label: string;
            limit: string;
            spent_before: string;
            expense_amount: string;
            resulting_spend: string;
            over_amount: string;
            already_exceeded: boolean;
            prior_over_amount: string;
            balance_sufficient: boolean;
        } | null;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    academic_year?: AcademicYearValue | null;
    email_verified_at: string | null;
    avatar?: string;
}

export interface Transaction {
    id: number;
    type: TransactionTypeValue;
    amount: string;
    description: string | null;
    transaction_date: string;
    category_id?: number | null;
    is_archived?: boolean;
    category?: {
        id: number;
        name: string;
        is_default?: boolean;
    } | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export type CategoryOption = {
    id: number;
    name: string;
    type: TransactionTypeValue;
    is_default: boolean;
};

export type FrequencyOption = {
    value: string;
    label: string;
};

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export type TypeOption = {
    value: string;
    label: string;
};
