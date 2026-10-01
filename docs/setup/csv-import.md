# CSV Transaction Import — Campus Coin

**Phase:** 4 + 8 (implemented)

## Flow

1. Upload CSV (`GET/POST /transactions/import`)
2. Validate file + parse rows into preview (session-stored; **no DB writes**)
3. Confirm with token + idempotency key
4. Atomic import of valid/warning rows inside a DB transaction
5. Summary flash: imported / skipped / errors / warnings

## Format

Required headers (aliases accepted for common variants):

```text
date,type,amount,description,category
```

Example:

```csv
date,type,amount,description,category
2026-09-01,income,50000,Monthly allowance,Allowance
2026-09-02,expense,1200,Lunch,Food
```

## Limits

Configured in `config/campuscoin.php`:

| Limit | Default |
|---|---|
| Max file size | 512 KB |
| Max data rows | 500 |

## Category matching

Matches only system defaults + current user's personal categories (name, case-insensitive, type must match).

- Unknown category → **warning**, imported as Uncategorized (`category_id` null)
- Never auto-creates categories
- Never matches another user's personal category
- `user_id` column in CSV is ignored for ownership
- Phase 8: when category is unmatched, optional AI suggestions appear in preview; final category is chosen by the user before confirm (`category_overrides`). Exact CSV matches skip AI.

## Security

- Extension + MIME + size checks
- Randomized server filename under `storage/app/csv-imports`
- Temp file deleted after confirm
- No path traversal from original filename
- Double-submit protected via session idempotency key
- Values starting with `= + - @` are prefixed with `'` before storage (formula-injection hardening for future exports); values are never executed

## Atomicity

Confirm runs inside `DB::transaction`. On critical DB failure, the batch is rolled back (no intentional partial save on the happy path).
