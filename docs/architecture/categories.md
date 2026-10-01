# Categories — Campus Coin

**Phase:** 4 (implemented)

## Model

`App\Models\Category`

| Field | Notes |
|---|---|
| `user_id` | `NULL` = system/default; otherwise personal owner |
| `name` | Max 100; trimmed; not whitespace-only |
| `type` | `income` / `expense` (`TransactionType`) |
| `is_default` | `true` for system categories |

## Visibility query

```sql
WHERE user_id IS NULL OR user_id = :auth_id
```

Implemented as `Category::accessibleTo($user)` — never filter another user's personal categories in PHP after a full-table fetch.

## Defaults (SRS)

Seeded by idempotent `Database\Seeders\DefaultCategorySeeder`:

**Income:** Allowance, Part-time Job, Scholarship, Gift, Other Income, Loan  
**Expense:** Food, Transport, Hostel/Rent, Academics, Subscriptions, Entertainment, Miscellaneous, Loan Repayment

`Loan` is for disbursement inflows (borrowed funds — not earned income).  
`Loan Repayment` is for installment outflows.

Running the seeder multiple times does not create duplicates.

## Authorization

`CategoryPolicy`:

- Defaults: visible/usable; not editable/deletable by students
- Personal: owner-only view/update/delete
- Students cannot mass-assign `is_default` or `user_id`

## Delete safety

If a personal category is referenced by transactions **or** recurring rules, deletion is **blocked** with:

> This category is being used by existing transactions.

No cascade delete of financial records. FK on transactions uses `nullOnDelete` as a last-resort safety net; product flow prefers block-first.

## Duplicate rules

Personal categories: unique `(user_id, type, name)`.  
Names matching a system default of the same type are rejected.
