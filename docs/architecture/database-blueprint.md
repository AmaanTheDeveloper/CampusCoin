# Database Blueprint — Campus Coin

**Status:** Living blueprint (Phase 4 domain tables implemented)  
**Implementation:** Categories, transactions, recurring, CSV import, dashboard analytics, budgets, and in-app notifications are live. Reports/AI remain future phases.

## Principles (mandatory for all future financial data)

1. **Never trust user-supplied ownership IDs.** Scope every query through the authenticated user (or an admin policy), e.g. `$request->user()->transactions()`.
2. **Monetary amounts use `DECIMAL(12,2)`** (or higher precision if multi-currency is later justified). Do **not** store money as `FLOAT`/`DOUBLE`.
3. **Foreign keys** enforce relationships; define intentional `ON DELETE` behavior.
4. **Indexes** support ownership + date/month filters used by dashboards and reports.
5. **Date/month semantics** must be explicit (store transaction `date` as `date`; derive month in queries via `YEAR`/`MONTH` or a dedicated `month_key` if needed).
6. **Unique/duplicate rules** must be intentional (e.g. one budget per user+category+month).
7. **Nullable fields** require justification.
8. **Soft deletes** (if used later) must not leak deleted rows into totals unless intended.

## Target engine

MySQL 8+ with `utf8mb4`. See `docs/setup/mysql.md`.

## Entity overview

```
User
 ├── Categories (personal) + reads system default categories
 ├── Transactions
 ├── Budgets
 ├── Insights
 ├── Saving tips / tip interactions
 ├── Bookmarks
 ├── Notifications
 └── Imports

Category
 ├── optional user_id (null = system/default)
 └── has many Transactions / Budgets

Transaction
 ├── belongs to User
 └── belongs to Category

Budget
 ├── belongs to User
 └── belongs to Category

Insight → belongs to User
Notification → belongs to User
Import → belongs to User
Bookmark → belongs to User (+ morph/target tip or insight)
Announcement → system-wide (admin)
Tip Template → system-wide (admin)
Activity Log → optional audit trail
```

## Planned tables (future phases)

### users (exists — extend carefully)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR | |
| email | VARCHAR unique | |
| password | VARCHAR | hashed |
| role | VARCHAR / enum string | **Phase 1:** `student` default, `admin` reserved |
| email_verified_at | TIMESTAMP nullable | |
| academic_year | VARCHAR/nullable | Phase 2 profile |
| monthly_allowance | DECIMAL(12,2) nullable | Phase 2 profile |
| savings_goal | DECIMAL(12,2) nullable | Phase 2 profile |
| timestamps | | |

### categories (**Phase 4 implemented**)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK nullable | null = system default; cascade on user delete for personal |
| name | VARCHAR(100) | |
| type | string enum | `income` / `expense` |
| is_default | boolean | system defaults |
| timestamps | | |
| unique | (user_id, type, name) | personal uniqueness; seeder enforces default uniqueness |

Default income: Allowance, Part-time Job, Scholarship, Gift, Other Income.  
Default expense: Food, Transport, Hostel/Rent, Academics, Subscriptions, Entertainment, Miscellaneous.

### transactions (**Phase 3 + 4**)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK → users cascade | ownership required; never client-controlled |
| category_id | FK → categories nullable, nullOnDelete | required for **new** rows; historical may be null (Uncategorized) |
| type | string (`income` / `expense`) | `App\Enums\TransactionType` |
| amount | DECIMAL(12,2) | always positive; type determines direction |
| description | VARCHAR(255) nullable | optional note |
| transaction_date | DATE | calendar date of the entry |
| timestamps | | |
| softDeletes | | Normal UI excludes deleted; not a full edit-audit log |

**Indexes:** `(user_id, transaction_date)`, `(user_id, type, transaction_date)`, `(user_id, category_id)`.

**Soft delete policy:** Soft delete preserves the row with `deleted_at` for history integrity. Soft delete is **not** a complete before/after audit trail of every edit. Future reports/dashboard must use non-deleted transactions only.

### recurring_transactions (**Phase 4 implemented**)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK cascade | |
| type | income/expense | |
| category_id | FK restrict | |
| amount | DECIMAL(12,2) | |
| description | nullable | |
| frequency | weekly/monthly | |
| start_date / end_date | date / nullable date | |
| next_run_date | date | |
| last_generated_at | timestamp nullable | |
| is_active | boolean | |
| softDeletes | | |

### recurring_transaction_occurrences (**Phase 4**)

Unique `(recurring_transaction_id, occurrence_date)` prevents duplicate generation. Short FK names used for MySQL identifier limits.

### budgets (Phase 6 — implemented)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK → users | restrict / cascade per migration |
| category_id | FK → categories | restrict on delete |
| month | DATE | first day of month (`YYYY-MM-01`) |
| limit_amount | DECIMAL(12,2) | must be &gt; 0 |
| timestamps | | |
| unique | (user_id, category_id, month) | core integrity rule |

### budget_alerts (Phase 6 — implemented)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| budget_id | FK → budgets | cascade on budget delete |
| alert_type | string | `near_limit` \| `exceeded` |
| triggered_at | timestamp | |
| unique | (budget_id, alert_type) | deduplication |

### insights (Phase 9)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK | |
| month | CHAR(7) | |
| content | TEXT | AI/advisory narrative |
| metadata | JSON nullable | patterns, suggestions |
| timestamps | | |

AI output is **advisory**, not certified financial advice.

### saving_tips / tip_interactions (Phase 9)

System tip templates + per-user generated/dismissed/pinned tips.

### bookmarks (Phase 9)

User bookmarks of tips and/or insights (polymorphic or typed target columns).

### transaction_activities (Phase 10 — implemented)

Recently viewed / edited transactions. Unique `(user_id, transaction_id, activity_type)`; soft-deleted transactions excluded from active lists.

### transaction_signals (Phase 10 — implemented)

Review signals: `large_transaction` | `duplicate_transaction`. Statuses `open` | `dismissed` | `reviewed`. Unique `(user_id, fingerprint)`. Never auto-mutates financial rows.

### notifications (Phase 6 — implemented)

Standard Laravel `notifications` table (UUID id, `notifiable` morph, `type`, `data` JSON, `read_at`). Database channel only.

### CSV imports (Phase 4 — session workflow)

Import uses a secure upload → preview → confirm flow (session + temp files). Persistent import job table is optional later; see `docs/setup/csv-import.md`.

### announcements (later / admin panel)

Admin system-wide announcements.

### tip_templates (later / admin panel)

Admin-managed tip templates.

### activity_logs (optional / later)

Audit of sensitive admin or import actions.

## Ownership enforcement pattern (future code)

```php
// Preferred
$request->user()->transactions()->findOrFail($id);

// Avoid
Transaction::findOrFail($id); // then hoping the UI only linked the owner's IDs
```

Admin access must use explicit policies, never “skip ownership because admin UI exists.”

## Deletion behavior (to decide when implementing)

| Parent deleted | Recommended default |
|---|---|
| User | Cascade or anonymize per privacy policy — decide before admin reset |
| Category with transactions | Restrict delete or reassign — do not orphan |
| Transaction | Soft or hard delete; totals must stay consistent |

## Out of scope for this blueprint

- Real bank integrations
- Payment processing
- Bank account verification

Transactions are manually entered or CSV-imported only.
