# Dashboard — Campus Coin

**Phase:** 5–6 (implemented)

## Purpose

Personalized student financial command center for the authenticated user only.

## Ownership

All analytics use `$user->transactions()` (soft-deleted rows excluded by Eloquent).

Never:

- global `Transaction::query()` without ownership
- another user's data
- admin-wide aggregates

## Current month

Determined dynamically from `now()` in `config('app.timezone')`:

- `start` = start of current calendar month
- `end` = end of current calendar month
- `today` = current date

Do not hard-code a month.

### Totals vs daily chart

| Metric | Date rule |
|---|---|
| Income / expense / balance / category / largest / count | Full calendar month (`start` → `end`), including future-dated rows in that month |
| Daily spending trend | Month start → **today** only (missing days = `0.00`) |

Future-dated rows in the current month contribute to totals but do not appear as “already spent” points on the daily chart after today.

## Formulas

| Metric | Formula |
|---|---|
| Income | `SUM(amount)` where `type = income` (current calendar month) |
| Expenses | `SUM(amount)` where `type = expense` (current calendar month) |
| Period balance / savings | current-month income − expenses |
| Savings rate | `(period balance / income) × 100` |
| **Balance KPI (UI)** | **`AvailableBalance`** = lifetime income − lifetime expense (same source as expense create/edit guards) |

If income = `0.00`, savings rate is `null` and UI shows **N/A** (no division by zero). Extreme rates are displayed as **Not meaningful** on the frontend.

Period balance / rate may be negative when this month’s expenses exceed this month’s income (e.g. prior-month funds). That is an analytical month-net figure, **not** the spendable Balance KPI.

The Balance KPI uses `summary.available_balance` and must stay consistent with transaction safety. Soft-deleted rows are excluded from both period totals and AvailableBalance.

## Category analytics

Expense-only for the current month.

- Group by `category_id`
- `NULL` category → **Uncategorized**
- Percentage = category expense / total current-month expense × 100
- Top category = highest expense total (Uncategorized allowed)

## Monthly overview

Last **6** calendar months including the current month:

`month`, `income`, `expense`, `balance`

Uses `transaction_date`, not `created_at`.

## Recent transactions

Latest **5** non-deleted transactions for the user, with category eager-loaded.

## Highlights

Deterministic rule-based messages only. **Not AI.**

## Budget vs Actual (Phase 6)

Current-month budgets from `BudgetService`, injected by `DashboardAnalyticsService` as `budgets`.

Each row: category, limit, spent, remaining, percentage, status.

Empty state CTA → `/budgets/create`. No fake budgets. Only categories with an actual budget row are shown.

See `docs/architecture/budgets.md`.

## Your Money Pulse (Financial Health)

Deterministic, explainable health card from `FinancialHealthService`, attached in `DashboardController` as `money_pulse`.

Reuses dashboard `monthly_trend`, current-month `budgets`, `summary.balance`, and profile `savings_goal`. **No AI / no demo scores.**

| Metric | Formula |
|---|---|
| Savings consistency | `(months with balance > 0 ÷ months with income > 0) × 100` — balance = income − expenses (dashboard savings); needs ≥ 2 income months |
| Budget discipline | Average of `clamp(100 − max(0, spent/limit% − 100), 0, 100)` over current-month budgets |
| Spending stability | `clamp(100 × (1 − min(CV, 1)), 0, 100)` where `CV = stdev(expenses) ÷ mean` for months with expense > 0; needs ≥ 2 |
| Goal progress | `clamp((current-month balance ÷ savings_goal) × 100, 0, 100)` — monthly balance vs profile savings goal (same basis as saving tips); unavailable if no goal |
| Overall | Average of **available** metrics only. 80–100 Healthy; 60–79 Watchful; 0–59 Needs Attention |
| Spending trend | Current vs previous month expense; ±5% band = Stable; decrease = Improving; increase = Increasing |

Unavailable metrics are skipped (never forced to 0). Thresholds live as constants on `FinancialHealthService`.

## Month Forecast (current-month pace)

Deterministic “Where will I end this month?” from `MonthlyForecastService`, attached as `month_forecast` and at `/month-forecast`.

**Distinct from** Intelligence next-month `ForecastService` (historical completed-month averages).

| Piece | Formula |
|---|---|
| Days elapsed | `max(1, min(today.day, days_in_month))` — never divide by zero |
| Daily pace | `MTD expenses ÷ days elapsed` (expenses month-start → today) |
| Forecast expenses | `daily pace × days in month` |
| Expected savings | `MTD income − forecast expenses` (does **not** invent future income) |
| Category forecast | `(category MTD spend ÷ days elapsed) × days in month` |
| Projected overrun | `category forecast − budget` when > 0 and not already over |
| Alert priority | Already-over (largest current overrun) else largest projected overrun |

Zero MTD expenses → insufficient (no fake numbers). Early month (≤3 days) adds a transparency note. Chart series: actual cumulative through today, then dashed forecast pace to month-end.

## Savings Goal Tracker

Long-term living goal card from `SavingsGoalService`, attached as `savings_goal_tracker` and at `/savings-goal`.

**Distinct from** profile `users.savings_goal` (monthly Money Pulse / saving tips target). Do not merge the two.

See `docs/architecture/savings-goals.md` for progress, completion estimate, and smart scenario formulas.

## Monthly Money Review

Standalone report at `/monthly-review` (sidebar + dashboard **✨ Review My Month**). Deterministic summary, MoM changes, budgets, insight, and ≤3 actions. See `docs/architecture/monthly-review.md`.

Does **not** change dashboard formulas or other intelligence cards.

## Query architecture

`App\Services\DashboardAnalyticsService`

Typical query groups (~7–9):

1. Summary aggregates  
2. Category breakdown  
3. Daily expense trend  
4. Monthly trend  
5. Recent transactions  
6. Largest expense  
7. `exists` for empty-state flag  
8. Current-month budget vs actual (aggregated spending by category)  

Uses SQL `SUM` / `COUNT` / `GROUP BY`. Does not load full history into PHP for totals.

## Charts

Dependency: **recharts** (+ `react-is` peer).

- Daily expenses: area/line  
- Category breakdown: donut + accessible text list  
- Six-month overview: grouped bars  

## Quick actions

- `/transactions/create?type=income`  
- `/transactions/create?type=expense`  

CSV import link remains available on the empty state.

## Empty / first-use state

When the user has **no transactions and no budgets**, the UI shows a first-use empty state (no metric cards, no charts, no fake highlights). Payload zeros remain available for tests (`has_transactions = false`).

Greeting uses the authenticated user's name, or `there` when blank.

## Local financial reset

Development-only:

```bash
php artisan campuscoin:reset-local-financial-data --force
```

Clears financial/demo tables; preserves users and categories. Refuses outside `local` / `development` / `testing`, and refuses without `--force`.

## Compatibility

CSV-imported and recurring-generated rows are normal `transactions` and are included automatically.

## Reports (Phase 7)

`/reports` reuses the same ownership, soft-delete, `transaction_date`, and MoneyMath rules. Unfiltered current-month totals must match this dashboard. See `docs/architecture/reports.md`.
