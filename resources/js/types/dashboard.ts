export type DashboardGreeting = {
    period: 'morning' | 'afternoon' | 'evening';
    name: string;
};

export type DashboardPeriod = {
    label: string;
    start: string;
    end: string;
    today: string;
    timezone: string;
};

export type DashboardSummary = {
    income: string;
    expense: string;
    /** Current-month income − expenses (analytical; may be negative). */
    balance: string;
    savings: string;
    savings_rate: number | null;
    /** Lifetime spendable wallet = AvailableBalance (transaction safety source of truth). */
    available_balance: string;
    transaction_count: number;
    income_count: number;
    expense_count: number;
};

export type CategoryBreakdownItem = {
    category_id: number | null;
    name: string;
    amount: string;
    percentage: number | null;
};

export type DailySpendingPoint = {
    date: string;
    amount: string;
};

export type MonthlyFinancialPoint = {
    month: string;
    label: string;
    income: string;
    expense: string;
    balance: string;
};

export type DashboardRecentTransaction = {
    id: number;
    type: 'income' | 'expense';
    amount: string;
    description: string | null;
    transaction_date: string;
    category: { id: number; name: string } | null;
};

export type LargestExpense = {
    id: number;
    description: string;
    amount: string;
    transaction_date: string;
    category: string;
};

export type DashboardHighlight = {
    id: string;
    message: string;
};

export type DashboardBudgetItem = {
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
    percentage: number;
    status: 'healthy' | 'near_limit' | 'exceeded';
    status_label: string;
};

export type MonthlyInsightTrend = {
    label: string;
    direction: 'up' | 'down' | 'stable' | string;
    significance: 'high' | 'medium' | 'low' | string;
};

export type MonthlyInsightCard = {
    id: number;
    period: string;
    period_label: string;
    headline: string;
    summary: string;
    trends: MonthlyInsightTrend[];
    actions: string[];
    generated_source: string;
    status: string;
    generated_at: string | null;
    is_bookmarked: boolean;
};

export type MonthlyInsightPanel = {
    period: string;
    period_label: string;
    ai_available: boolean;
    insight: MonthlyInsightCard | null;
    factual_fallback: {
        headline: string;
        summary: string;
        trends: MonthlyInsightTrend[];
        actions: string[];
    };
    can_generate: boolean;
};

export type SavingTipCard = {
    id: number;
    tip_key: string;
    period: string;
    title: string;
    body: string;
    category_id: number | null;
    impact_level: 'high' | 'medium' | 'low' | string;
    rank_score: number;
    is_bookmarked: boolean;
    is_dismissed: boolean;
};

export type MoneyPulseMetric = {
    available: boolean;
    score: number | null;
    label: string;
    summary: string;
    explanation: string;
    formula: string;
    detail: Record<string, unknown>;
};

export type MoneyPulseTrend = {
    available: boolean;
    label: 'Improving' | 'Stable' | 'Increasing' | string | null;
    change_percentage: number | null;
    recent_expense: string | null;
    previous_expense: string | null;
    recent_label?: string;
    previous_label?: string;
    explanation: string;
    formula: string;
};

export type MoneyPulseOverall = {
    status: 'healthy' | 'watchful' | 'needs_attention' | 'insufficient' | string;
    status_label: string;
    score: number | null;
    indicator: 'green' | 'amber' | 'red' | 'neutral' | string;
    explanation: string;
    formula: string;
    metrics_used: number;
};

export type MoneyPulsePanel = {
    overall: MoneyPulseOverall;
    metrics: {
        savings_consistency: MoneyPulseMetric;
        budget_discipline: MoneyPulseMetric;
        spending_stability: MoneyPulseMetric;
        goal_progress: MoneyPulseMetric;
    };
    spending_trend: MoneyPulseTrend;
    disclaimer: string;
    period_label: string;
};

export type MonthForecastSeriesPoint = {
    date: string;
    label: string;
    actual: string | null;
    forecast: string | null;
    phase: string;
};

export type MonthForecastBudgetAlert = {
    available: boolean;
    kind: 'already_over' | 'projected_over' | string;
    category: string;
    budget: string;
    spent: string;
    forecast: string;
    overrun: string;
    message: string;
};

export type MonthForecastCategoryRow = {
    budget_id: number | null;
    category_name: string;
    limit: string;
    spent: string;
    forecast: string;
    already_over: boolean;
    current_overrun: string;
    projected_over: boolean;
    projected_overrun: string;
    status: string;
};

export type MonthForecastPanel = {
    available: boolean;
    status: string;
    status_label: string;
    status_indicator: string;
    status_message: string;
    period: {
        month: string;
        label: string;
        days_elapsed: number;
        days_in_month: number;
        days_remaining: number;
        today: string;
        early_month: boolean;
    };
    actual: {
        expenses: string;
        income: string;
    };
    pace: {
        daily_expenses: string | null;
    };
    forecast: {
        expenses: string | null;
        expected_savings: string | null;
        expected_savings_available: boolean;
    };
    budget_alert: MonthForecastBudgetAlert | null;
    category_forecasts: MonthForecastCategoryRow[];
    series: MonthForecastSeriesPoint[];
    explanation: string;
    savings_explanation: string | null;
    formula: string;
    disclaimer: string;
    early_month_note: string | null;
};

export type DashboardData = {
    period: DashboardPeriod;
    greeting: DashboardGreeting;
    has_transactions: boolean;
    summary: DashboardSummary;
    category_breakdown: CategoryBreakdownItem[];
    top_category: CategoryBreakdownItem | null;
    daily_expense_trend: DailySpendingPoint[];
    monthly_trend: MonthlyFinancialPoint[];
    recent_transactions: DashboardRecentTransaction[];
    largest_expense: LargestExpense | null;
    highlights: DashboardHighlight[];
    budgets: DashboardBudgetItem[];
    monthly_insight?: MonthlyInsightPanel;
    saving_tips?: SavingTipCard[];
    intelligence?: import('@/types/intelligence').DashboardIntelligence;
    money_pulse?: MoneyPulsePanel;
    month_forecast?: MonthForecastPanel;
    savings_goal_tracker?: SavingsGoalTrackerPanel;
};

export type SavingsGoalContributionRow = {
    id: number;
    amount: string;
    note: string | null;
    contributed_at: string | null;
};

export type SavingsGoalSmartScenario = {
    available: boolean;
    category_name: string;
    category_monthly_average: string;
    suggested_reduction: string;
    baseline_monthly_savings: string;
    scenario_monthly_savings: string;
    baseline_months: number;
    scenario_months: number;
    months_saved: number;
    baseline_label: string;
    scenario_label: string;
    headline: string;
    explanation: string;
    formula: string;
};

export type SavingsGoalDetail = {
    id: number;
    name: string;
    target_amount: string;
    current_amount: string;
    remaining_amount: string;
    progress_percent: number;
    status: string;
    status_label: string;
    is_completed: boolean;
    savings_rate: {
        available: boolean;
        monthly_rate: string | null;
        source: string | null;
        explanation: string | null;
    };
    completion_estimate: {
        months: number;
        label: string;
        date: string;
        explanation: string;
    } | null;
    smart_scenario: SavingsGoalSmartScenario | null;
    contributions: SavingsGoalContributionRow[];
    disclaimer: string;
};

export type SavingsGoalTrackerPanel = {
    has_goal: boolean;
    goal: SavingsGoalDetail | null;
    empty_message?: string;
    cta?: string;
};
