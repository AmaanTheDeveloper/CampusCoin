# System Intelligence — Campus Coin

**Phase:** 10 (implemented)

Deterministic, user-scoped intelligence. **No AI required.** Database facts remain authoritative. Signals never auto-edit, delete, or merge transactions.

## Features

1. **Recent activity** — recently viewed (edit screen open) and recently edited (successful update) transactions
2. **Next-month forecast** — estimated income/expense/balance from completed historical months
3. **Large transaction signals** — amount vs category historical average
4. **Duplicate candidates** — expense, same day (or tiny window), exact amount, normalized description → `/duplicate-review`

## Architecture

```
RecentActivityService
ForecastService
LargeTransactionDetectionService
DuplicateDetectionService
        ↓
TransactionSignalService / IntelligenceService
        ↓
Dashboard + /intelligence (Inertia)
```

## Recent activity

- Table: `transaction_activities` (`viewed` | `edited`)
- Unique: `(user_id, transaction_id, activity_type)` — upserts `occurred_at`
- Soft-deleted transactions excluded via `whereHas('transaction')`
- Limit: `INTELLIGENCE_ACTIVITY_LIMIT` (default 10)
- View tracking failures never block the transaction page

## Forecast methodology

- Uses up to **N completed calendar months** (`INTELLIGENCE_FORECAST_LOOKBACK_MONTHS`, default 3)
- **Current month excluded** (avoids comparing partial months to full months)
- Estimate = arithmetic mean of usable months (months with ≥1 transaction)
- Category projections = category expense totals ÷ months used
- Recurring templates are **not** added separately (occurrences already appear in history)
- Confidence:
  - 0 usable months → Insufficient data
  - 1 → Low confidence
  - 2+ → Moderate confidence
- Labels use “Estimated / Forecast”; not guaranteed outcomes
- Computed on demand (not persisted)

## Large / unusual spending detection

Deterministic **Unusual Spending Detector** used by Intelligence and **Spending Alerts**.

- **Expenses only** (income never flagged)
- Category-scoped recent average (excluding the transaction itself)
- History window: last `INTELLIGENCE_LARGE_LOOKBACK_DAYS` days (default **90**) ending on the transaction date
- Requires `INTELLIGENCE_LARGE_MIN_OBSERVATIONS` (default **5**) prior same-category expenses in-window
- Flag when `amount >= multiplier × average` **AND** `amount >= absolute floor`
- Defaults: multiplier `2.5`, floor `500` (stricter than a raw 2× / 3-observation rule to reduce false positives)
- Zero average → never flagged
- Explanation includes amount, baseline average, difference, and deviation ratio (e.g. `2.7×`)
- Duplicate prevention: unique `(user_id, fingerprint)`; dismissed/reviewed fingerprints are not recreated
- Evaluation runs after create/update/CSV import; failures never roll back a valid save
- Immediate UX: successful create may flash `spending_alert` to the transactions page

Terminology: “Unusual Expense” / review suggested — never “fraud” or “AI detected”.

## Duplicate detection

Deterministic **Duplicate Transaction Detector** used by Intelligence and **Duplicate Review** (`/duplicate-review`).

### Candidate rule (high confidence)

```
same authenticated user
+ expense
+ normalized merchant/description match
+ exact amount (MoneyMath)
+ same calendar day
```

### Moderate confidence (optional)

When `INTELLIGENCE_DUPLICATE_DATE_WINDOW_DAYS` > 0: same user/expense/normalized description/amount within ± window days (not months apart).

### Normalization

1. trim  
2. lowercase  
3. strip apostrophe/quote variants  
4. strip remaining punctuation  
5. collapse whitespace  

Empty normalized descriptions are **not** matched (avoids vague false positives).

### Fingerprint

`sha256(duplicate|user_id|min(id)|max(id)|match_type)` — direction-independent; prevents A→B and B→A duplicates. Resolved fingerprints are not recreated on re-import.

### Resolution (Duplicate Review)

| Action | Status | Effect |
|---|---|---|
| Keep Both | `kept_both` | Transactions unchanged |
| Remove Duplicate | `removed_duplicate` | Soft-archives the **user-selected** transaction (default suggestion = newer / higher id) after confirm |
| Intelligence dismiss/review | `dismissed` / `reviewed` | Still available on `/intelligence` |

Never auto-deletes. Soft-archived peers invalidate open duplicate candidates.

### Import

After CSV confirm, `TransactionSignalService::evaluateMany` compares new rows against existing DB peers **and** against each other (each new id is evaluated). Import succeeds even when candidates exist; flash includes `possible_duplicates` count + link to `/duplicate-review`.

## Signal lifecycle

Statuses: `open` → `dismissed` | `reviewed` | `kept_both` | `removed_duplicate`

Uniqueness: `(user_id, fingerprint)`

Triggers: after create/update; after CSV import confirm (post-commit, non-blocking)

Soft-deleted transactions: signals excluded from open actionable lists; archive resolves open duplicate pairs

## Privacy / security

- All queries authenticated-user scoped
- No cross-user baselines or duplicate peers
- Admin role does not bypass student ownership
- No external provider calls for Phase 10 arithmetic

## Routes

| Method | Path | Purpose |
|---|---|---|
| GET | `/intelligence` | Forecast, signals, activity |
| POST | `/intelligence/signals/{id}/dismiss` | Dismiss |
| POST | `/intelligence/signals/{id}/review` | Mark reviewed |
| GET | `/spending-alerts` | Unusual spending alerts (expense large signals) |
| GET | `/duplicate-review` | Possible duplicate review |
| POST | `/duplicate-review/{signal}/keep-both` | Keep both transactions |
| POST | `/duplicate-review/{signal}/remove` | Confirm soft-archive of one pair member |

## Configuration

See `config/intelligence.php` and optional `INTELLIGENCE_*` env keys.
