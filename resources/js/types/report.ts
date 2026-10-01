export type ReportFiltersState = {
    from: string;
    to: string;
    category: number | 'uncategorized' | null;
    income_source: number | null;
};

export type ReportPeriod = {
    label: string;
    from: string;
    to: string;
    timezone: string;
    generated_at: string;
    generated_label: string;
    week_starts_on: 'monday';
    max_range_months: number;
};

export type ReportSummary = {
    income: string;
    expense: string;
    balance: string;
    savings: string;
    savings_rate: number | null;
    transaction_count: number;
    income_count: number;
    expense_count: number;
};

export type CategoryReportItem = {
    category_id: number | null;
    name: string;
    amount: string;
    percentage: number | null;
};

export type DailyReportPoint = {
    date: string;
    amount: string;
};

export type WeeklyReportItem = {
    week: number;
    label: string;
    start: string;
    end: string;
    amount: string;
};

export type MonthlyTrendItem = {
    month: string;
    label: string;
    income: string;
    expense: string;
    balance: string;
};

export type ReportTransaction = {
    id: number;
    type: 'income' | 'expense';
    amount: string;
    description: string | null;
    transaction_date: string;
    category: { id: number; name: string } | null;
};

export type ReportCategoryOption = {
    id: number | 'uncategorized';
    name: string;
    type: string;
    is_default: boolean;
};

export type ReportTransactionsPage = {
    data: ReportTransaction[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

export type ReportData = {
    filters: ReportFiltersState;
    filter_labels: string[];
    period: ReportPeriod;
    has_data: boolean;
    has_any_history: boolean;
    summary: ReportSummary;
    category_breakdown: CategoryReportItem[];
    top_category: CategoryReportItem | null;
    daily_summary: DailyReportPoint[];
    weekly_summary: WeeklyReportItem[];
    six_month_trend: MonthlyTrendItem[];
    six_month_note: string;
    transactions: ReportTransactionsPage;
    expense_categories: ReportCategoryOption[];
    income_sources: ReportCategoryOption[];
    filter_semantics: {
        category: string;
        income_source: string;
        date_range: string;
    };
};
