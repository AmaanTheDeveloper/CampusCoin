export type IntelligenceActivityItem = {
    id: number;
    transaction_id: number;
    description: string;
    amount: string;
    type: 'income' | 'expense' | string;
    category: string | null;
    transaction_date: string;
    occurred_at: string | null;
};

export type IntelligenceSignalTransaction = {
    id: number;
    description: string;
    amount: string;
    type: string;
    category: string | null;
    transaction_date: string;
};

export type IntelligenceSignal = {
    id: number;
    signal_type: 'large_transaction' | 'duplicate_transaction' | string;
    signal_label: string;
    severity: string;
    status: string;
    reason: string;
    detected_at: string | null;
    is_unread?: boolean;
    explanation?: {
        amount?: string | null;
        baseline_average?: string | null;
        difference?: string | null;
        deviation_ratio?: number | null;
        threshold?: string | null;
        observations?: number | null;
        lookback_days?: number | null;
        category_name?: string | null;
        multiplier?: number | null;
    };
    transaction: IntelligenceSignalTransaction | null;
    comparison_transaction: IntelligenceSignalTransaction | null;
};

export type SpendingAlertsPayload = {
    unread: IntelligenceSignal[];
    earlier: IntelligenceSignal[];
    open_count: number;
    disclaimer: string;
    detection: {
        multiplier: number;
        min_observations: number;
        lookback_days: number;
        absolute_floor: string;
    };
};

export type IntelligenceForecastCategory = {
    category_id: number | null;
    name: string;
    estimated_amount: string;
};

export type IntelligenceForecast = {
    target_period: string | null;
    target_label: string | null;
    confidence: string;
    confidence_label: string;
    method: string | null;
    months_used: number;
    estimated_income: string | null;
    estimated_expense: string | null;
    estimated_balance: string | null;
    categories: IntelligenceForecastCategory[];
    disclaimer: string;
    empty_message: string | null;
    error?: boolean;
};

export type IntelligencePayload = {
    forecast: IntelligenceForecast;
    signals: IntelligenceSignal[];
    activity: {
        viewed: IntelligenceActivityItem[];
        edited: IntelligenceActivityItem[];
    };
};

export type DashboardIntelligence = {
    forecast: IntelligenceForecast;
    large_signals: IntelligenceSignal[];
    duplicate_signals: IntelligenceSignal[];
    recent_viewed: IntelligenceActivityItem[];
    recent_edited: IntelligenceActivityItem[];
    open_signal_count: number;
};
