# Reports — Campus Coin

**Phase:** 7 (implemented)

## SRS vs implementation

| Item | Source |
|---|---|
| Category-wise monthly spending | SRS |
| Six-month income vs expense | SRS |
| Daily spending summaries | SRS |
| Weekly spending summaries | SRS |
| Date / category / income-source filters | SRS |
| PDF + image export | SRS |
| Default = current calendar month | **Implementation choice** |
| Week starts Monday (ISO) | **Implementation choice** |
| Max date range 24 months | **Implementation choice** |
| Income-source filter does not filter expense charts | **Implementation choice** |
| Six-month trend independent of primary date filter | **Implementation choice** |

## Route

`GET /reports` — `auth` + `verified`. Inertia page `reports/index`.

## Source of truth

Authenticated user’s non-deleted `transactions` (`transaction_date`). Soft deletes excluded. Categories are grouping metadata only.

## Filter semantics

| Filter | Affects |
|---|---|
| `from` / `to` | Summary, category breakdown, daily, weekly, detail table |
| `category` | Expense totals/charts/rows only (`uncategorized` supported) |
| `income_source` | Income totals and income rows only |
| *(none on six-month)* | Last 6 calendar months, user-scoped only |

## Formulas

Same as dashboard:

```
balance = income − expense
savings_rate = (balance / income) × 100   // null if income = 0
```

Category percentages use **filtered total expenses**.

## Weekly grouping

Monday–Sunday calendar weeks clipped to the selected range. Partial first/last weeks are included.

## Query groups

Typically ~6–8: summary (2), category, daily, weekly, six-month, paginated transactions, category option lists.

## Reconciliation

Unfiltered current-month report totals match dashboard. Category/month expense matches `BudgetService` actuals.

See also: `docs/architecture/report-exports.md`.
