# Transactions Domain — Campus Coin

**Phase:** 3 + 4 + 8  
**Status:** Implemented with categories (Phase 4) and optional AI suggestions (Phase 8)

## Ownership rule

Never trust client-supplied `user_id`. Always create via:

```php
$request->user()->transactions()->create($validated);
```

Policies enforce owner-only view/update/delete. Admin role does **not** grant cross-user transaction access.

## Categories

- `category_id` is nullable for **historical** Phase 3 rows → displayed as **Uncategorized**
- **New** create/update requires a valid `category_id`
- Category must be accessible (system default or owner personal) and `category.type === transaction.type`
- History supports a user-scoped `category_id` filter

See `docs/architecture/categories.md`.

## Money precision

`amount` is `DECIMAL(12,2)`, always stored as a positive number. Direction comes from `type` (`income` | `expense`).

## Posted transaction safety rules

### No future dates

Posted transactions (`transactions` table) cannot have `transaction_date` after the application calendar date (`config('app.timezone')` via `now()`).

- Enforced on create/update FormRequests (`before_or_equal:today`)
- Create/edit forms set the date input `max` to server-provided `today`
- CSV import marks future-dated rows as **invalid** during preview

Recurring **definitions** may still store a future `next_run_date` — that is a schedule, not a posted transaction.

### Insufficient balance (expenses)

Available balance = sum(income) − sum(expense) for the authenticated user (soft-deleted excluded).

- New expense is rejected when `amount > available balance`
- Expense edit compares against balance **excluding the transaction being edited** (so the original amount can be replaced)
- Income create is not balance-gated
- Income **edit/reduction** is rejected when the resulting available balance would be negative
- Client shows an accessible dialog before submit when possible; **server always re-checks**
- CSV: preview simulates running balance in row order and marks insufficient expenses invalid; confirm also skips unaffordable expenses

Invalid expenses are never persisted (so they never appear on dashboard/reports/budgets).

## Soft delete / archive

Normal user “Archive” soft-deletes the row (`deleted_at`). Soft delete retains the record for history integrity and excludes it from active lists, balances, and totals.

- Active list: default SoftDeletes scope (non-trashed)
- Archived list: `?status=archived` → `onlyTrashed()`
- Restore (`POST /transactions/{transaction}/restore`): clears `deleted_at` (same row — no duplicate)
- Archiving **income** is rejected when remaining posted expenses would leave available balance negative
- Restoring an **expense** is rejected when available balance cannot cover it
- Archived transactions are read-only (edit/update blocked until restored)
- Show allows viewing archived rows (`withTrashed`) for the owner

Soft delete is **not** a full before/after edit audit log.

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/transactions` | `transactions.index` |
| GET | `/transactions/create` | `transactions.create` |
| POST | `/transactions/suggest-category` | `transactions.suggest-category` |
| POST | `/transactions` | `transactions.store` |
| GET | `/transactions/{transaction}` | `transactions.show` |
| GET | `/transactions/{transaction}/edit` | `transactions.edit` |
| PUT/PATCH | `/transactions/{transaction}` | `transactions.update` |
| DELETE | `/transactions/{transaction}` | `transactions.destroy` (soft archive) |
| POST | `/transactions/{transaction}/restore` | `transactions.restore` |
| GET | `/transactions/import` | `transactions.import.create` |
| POST | `/transactions/import/preview` | `transactions.import.preview` |
| POST | `/transactions/import/confirm` | `transactions.import.confirm` |

Middleware: `auth` + `verified`.

## Pagination labels

Laravel pagination link labels may contain HTML entities. The UI renders **plain text only** via `PaginationNav` — no `dangerouslySetInnerHTML`.

## Related Phase 4 docs

- Recurring: `docs/architecture/recurring-transactions.md`
- CSV import: `docs/setup/csv-import.md`

## Dashboard (Phase 5)

User-scoped analytics over non-deleted transactions: see `docs/architecture/dashboard.md`.

## AI categorization (Phase 8)

Optional description-based category suggestions on create (auto) and edit (manual description changes only if enabled). Never auto-submits. See `docs/architecture/ai-categorization.md`.
