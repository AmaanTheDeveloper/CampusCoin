export type AdminPeriod = '7d' | '30d' | '90d' | '6m' | '12m';

export type PeriodOption = { value: AdminPeriod; label: string };

export type KpiComparison = {
    label: string;
    percent: number | null;
    direction: 'up' | 'down' | null;
};

export type TypeOption = { value: string; label: string };

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

export type AdminKpiNumber = {
    label: string;
    value: number;
    href: string;
    comparison: KpiComparison | null;
    supporting: string;
};

export type AdminKpiCategory = {
    label: string;
    value: string;
    numeric_value: number;
    href: string;
    comparison: null;
    supporting: string;
};

export type AdminCharts = {
    user_growth: Array<{ date: string; label: string; count: number }>;
    transaction_activity: Array<{ date: string; label: string; count: number }>;
    income_vs_expense: {
        income_total: string;
        expense_total: string;
        income_count: number;
        expense_count: number;
        points: Array<{ label: string; income: number; expense: number }>;
    };
    category_popularity: Array<{
        id: number;
        name: string;
        type: 'income' | 'expense';
        count: number;
        percent: number;
    }>;
};

export type AdminAlert = {
    id: string;
    severity: 'warning' | 'info';
    title: string;
    detail: string;
};

export type AdminActivityItem = {
    id: string;
    title: string;
    context: string | null;
    occurred_at: string | null;
    icon: 'shield' | 'user' | 'megaphone' | 'lightbulb';
};

export type AdminIntelligenceSummary = {
    has_data: boolean;
    suggestions: number;
    accepted: number;
    corrected: number;
    acceptance_rate: number | null;
    open_duplicates: number;
    open_unusual: number;
    href: string;
};

export type AdminDashboardPayload = {
    period: AdminPeriod;
    period_options: PeriodOption[];
    active_definition: string;
    kpis: {
        total_students: AdminKpiNumber;
        active_students: AdminKpiNumber;
        total_transactions: AdminKpiNumber;
        income_records: AdminKpiNumber;
        expense_records: AdminKpiNumber;
        most_used_category: AdminKpiCategory;
    };
    charts: AdminCharts;
    alerts: AdminAlert[];
    activity: AdminActivityItem[];
    intelligence_summary: AdminIntelligenceSummary;
    quick_links: Array<{ label: string; href: string }>;
};

export type AdminUserListItem = {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'disabled';
    transactions_count: number;
    income_count: number;
    expense_count: number;
    joined_at: string | null;
    last_activity_at: string | null;
};

export type AdminCategoryRow = {
    id: number;
    name: string;
    type: 'income' | 'expense';
    is_default: boolean;
    is_active: boolean;
    usage_count: number;
    is_system: true;
};

export type AnnouncementPriority = 'low' | 'normal' | 'high';
export type AnnouncementStatus = 'draft' | 'scheduled' | 'active' | 'expired';

export type AdminAnnouncementRow = {
    id: number;
    title: string;
    message: string;
    priority: AnnouncementPriority;
    status: AnnouncementStatus;
    starts_at: string | null;
    ends_at: string | null;
    updated_at: string | null;
    creator: string | null;
};

export type TipImpactLevel = 'low' | 'medium' | 'high';

export type AdminTipRow = {
    id: number;
    title: string;
    body: string;
    category_label: string | null;
    impact_level: TipImpactLevel;
    is_active: boolean;
    updated_at: string | null;
    creator: string | null;
};
