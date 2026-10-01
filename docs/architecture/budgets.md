# Budgets — Campus Coin

**Phase:** 6 (implemented)

## SRS vs implementation

| Item | Source |
|---|---|
| Monthly category budgets | SRS |
| Real-time consumption on page load | SRS (“real-time” = recalculate on request; no WebSockets) |
| Progress indicators | SRS |
| In-app near / exceeded alerts | SRS |
| 80% near-limit threshold | **Implementation choice** (not an SRS literal) |
| Month stored as first calendar day | **Implementation choice** |

## Model

`budgets`

| Column | Notes |
|---|---|
| `user_id` | Owner (server-controlled; not mass-assignable from client) |
| `category_id` | Expense category only; accessible default or personal |
| `month` | **First calendar day** of the month (`YYYY-MM-01`, DATE) |
| `limit_amount` | `DECIMAL(12,2)`, must be `> 0` |

Unique: `(user_id, category_id, month)`.

## Calculations

```
spent       = SUM(expense transactions) for user + category + month
remaining   = max(limit − spent, 0)   (never negative in the UI)
over_budget = max(spent − limit, 0)
percentage  = spent / limit × 100   (may exceed 100%)
```

Uses `transaction_date` with:

`month_start <= transaction_date < next_month_start`

Soft-deleted and income transactions are excluded. Future-dated expenses inside the budget month count toward spent.

When `status = exceeded`, the budgets UI shows **Over Budget** instead of a negative Remaining value.

## Expense overrun confirmation

Saving an expense that would push category/month spending above the budget limit requires explicit confirmation (`confirm_budget_overrun`). This is a soft warning — it does **not** bypass the available-balance hard stop. Choosing “Review Your Budget” navigates to `/budgets` without saving.

## Status (implementation choice)

Centralized in `App\Enums\BudgetStatus`:

| Usage | Status |
|---|---|
| &lt; 80% | Healthy |
| ≥ 80% and &lt; 100% | Near Limit |
| ≥ 100% | Exceeded |

## Historical / future months

Users may create budgets for past or future calendar months. Past months show historical spending for that month. Future months normally show `spent = 0` unless future-dated expense transactions exist in that month.

## Category delete

Categories referenced by budgets cannot be deleted (same as transactions/recurring). No cascade delete of budgets.

## Ownership

`BudgetPolicy` — owner-only. Admin does **not** bypass ownership in Phase 6.

Category access reuses the existing Category access abstraction (expense + default/own personal).

## Service

`App\Services\BudgetService` — aggregation (`SUM` / `GROUP BY`), serialization, alert evaluation. Spending is not computed with per-budget N+1 transaction loads.
