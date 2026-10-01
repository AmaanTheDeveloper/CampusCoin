# Recurring Transactions — Campus Coin

**Phase:** 4 + approval workflow (implemented)

## Model

`App\Models\RecurringTransaction` (soft deletes)

| Field | Notes |
|---|---|
| `user_id` | Owner (server-controlled) |
| `type` | income / expense |
| `category_id` | Must be accessible + type-matched |
| `amount` | DECIMAL(12,2), > 0 |
| `frequency` | `weekly` \| `monthly` (`RecurringFrequency`) |
| `start_date` / `end_date` | **start_date ≥ application today**; end optional, ≥ start |
| `next_run_date` | Next scheduled occurrence (may be in the future) |
| `last_generated_at` | Last time a pending occurrence was created |
| `is_active` | Pause/resume |

## Occurrences (approval ledger)

`App\Models\RecurringTransactionOccurrence`

| Field | Notes |
|---|---|
| `occurrence_date` | Calendar date for this occurrence |
| `status` | `pending_approval` \| `approved` \| `rejected` |
| `transaction_id` | Set only after explicit Approve |
| `last_reminded_at` | Idempotent reminder day tracking |
| `decided_at` | Last approve/reject timestamp |

Unique `(recurring_transaction_id, occurrence_date)`.

**Invariant:** A pending/rejected occurrence is **not** a posted transaction. Only Approve creates the financial row.

## Generation (no auto-post)

Command:

```bash
php artisan campuscoin:generate-recurring
php artisan campuscoin:generate-recurring --date=2026-09-01
```

Scheduled daily at `00:15` app timezone via `routes/console.php`.

Service: `App\Services\RecurringTransactionGenerator`

1. Clamp as-of date to **today**
2. Select **active** rules with `next_run_date <= asOf`
3. For each due date without an occurrence row: create **pending_approval** occurrence + notify user
4. Advance `next_run_date` (schedule continues; money is not deducted)
5. Send **at most one reminder per day** for actionable (pending/rejected) occurrences whose `last_reminded_at` is before today
6. Re-running cannot duplicate occurrences (unique constraint + lock)

Paused rules are never selected.

### Approval / rejection

`App\Services\RecurringOccurrenceApprovalService`

- **Approve:** ownership check, lock row, AvailableBalance for expenses, create exactly one `Transaction`, mark `approved` (idempotent)
- **Reject:** mark `rejected`, no transaction, no balance change; next-day reminder continues until approved
- Routes: `recurring-occurrences.approve` / `recurring-occurrences.reject`

Insufficient balance on approve: validation error, occurrence stays actionable.

## Monthly / weekly date rules

Unchanged: monthly clamps preferred day; weekly uses `addWeek()`.

## Ownership

`RecurringTransactionPolicy` — owner-only. Occurrence actions re-check rule `user_id`.
