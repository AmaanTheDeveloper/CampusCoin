# Monthly Money Review — Campus Coin

Standalone monthly “wow screen” at `/monthly-review`.

**Additive only.** Does not modify Money Pulse, Spending Alerts, Month Forecast, Savings Goal, Duplicate Review, Transactions, or Budgets logic.

Narratives and actions are **deterministic** (not AI). Separate from Phase 9 `/insights` AI monthly insights.

## Entry points

| Entry | Route |
|---|---|
| Sidebar **Monthly Review** | `GET /monthly-review` |
| Dashboard **✨ Review My Month** | same |

Optional query: `?month=YYYY-MM` (cannot select future months).

## Default month

1. Current calendar month if it has any transactions  
2. Else latest month with activity  
3. Else current month (empty state)

Timezone: `config('app.timezone')`. Bounds: month start → month end inclusive (`transaction_date`).

## Formulas

| Metric | Formula |
|---|---|
| Income / Expenses | Same as `DashboardAnalyticsService::summary` for the selected month |
| Saved | `Income − Expenses` (dashboard savings/balance semantics) |
| Savings rate | `Saved / Income × 100` when income > 0; else unavailable |
| Top category | Highest expense category amount; ties broken by category name ASC |
| MoM category change | `((current − previous) / previous) × 100`; previous = 0 → “New spending category” (no Infinity) |
| Budget within limits | spent ≤ limit (`status !== exceeded`); label `within / total` |
| Overall stable | \|expense MoM %\| < `OVERALL_STABLE_PERCENT` (5%) |

### Materiality (biggest change)

Constants on `MonthlyReviewService`:

- `CHANGE_MIN_AMOUNT` = Rs. 500 current spend  
- `CHANGE_MIN_PERCENT` = 15%  

If nothing qualifies → “Spending patterns were relatively stable this month.”

## Insight (“What happened?”)

Deterministic 2–4 sentences from:

- overall expense MoM statement  
- biggest category change (if any)  
- optional second-half spike for top category (≥25% vs first half)  
- budget overruns  

Evidence chips list supporting figures. No fabricated AI copy for empty users.

## Actions (max 3)

Priority order:

1. Budget exceeded categories  
2. Meaningful category increase (Food / Entertainment / Subscriptions wording)  
3. Subscriptions review  
4. Category concentration ≥ 40%  
5. Low savings rate (< 10% with income)

Wording is suggestive (“consider / try / review”), not commanding.

## Reuse

- `DashboardAnalyticsService::summary` / `categoryBreakdown`  
- `BudgetService::enrichBudgets` (**not** `currentMonthBudgetVsActual`, which triggers alerts)

## Security

Authenticated user only; all queries scoped via `$user->transactions()` / `$user->budgets()`. Month is never taken from another user’s data.
