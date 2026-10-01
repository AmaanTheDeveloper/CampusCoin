# CampusCoin

**Smart Spending, Student Style**

CampusCoin is a student-focused personal finance management platform built for university and competition evaluation. It helps students track irregular income and campus spending, set budgets and savings goals, understand patterns through deterministic intelligence and optional AI assistance, and—where enabled—discover and manage student loan products offered by partner banks on the platform.

This README is a **judge-ready guide**: install, run, log in, and open every available dashboard/role using only features and credentials that exist in the current codebase.

---

## 1. Project Overview

### Problem

Students often receive money from multiple sources (allowance, part-time work, scholarships, gifts) and spend across food, transport, hostel, academics, subscriptions, and entertainment—without a clear picture of where it went or what pressure is coming next.

### Target users

| Audience | Purpose |
|---|---|
| **Students** | Personal finance tracking, budgets, intelligence, loan applications |
| **Platform administrators** | User/platform governance, default categories, tips, bank partner approval |
| **Partner banks** | Offer student loan products, review applications, disburse and track repayments |

### Main objective

Provide a **single student-first product** that covers:

1. Track income and expenses  
2. Organize with categories, budgets, and goals  
3. Understand money with Money Pulse / forecast / alerts / monthly review  
4. Optionally apply for published student loan products through approved banks  

### Core financial workflow (student)

```text
Register / Log in
    → Dashboard overview
    → Record transactions (manual, CSV import, or Smart Scan)
    → Categories & budgets
    → Intelligence (pulse, forecast, alerts, review)
    → Optional: Student Loans → apply → agreement → repayment (sandbox)
```

CampusCoin does **not** connect to real external bank accounts for everyday tracking. Lending payments use a **sandbox** payment flow for demo/evaluation.

---

## 2. Technology Stack

Verified from `composer.json`, `package.json`, and project configuration:

| Layer | Technology |
|---|---|
| Backend | **PHP 8.2+**, **Laravel 12** |
| Frontend | **React 19**, **Inertia.js 2**, TypeScript |
| Routing helpers | Ziggy |
| UI | **Tailwind CSS 4**, Radix UI / shadcn-style components, Lucide icons |
| Charts | Recharts |
| Build | **Vite** (`laravel-vite-plugin`) |
| Database (app target) | **MySQL** (see `.env.example` / `docs/setup/mysql.md`) |
| Database (automated tests) | **SQLite in-memory** (`phpunit.xml`) |
| Auth | Session-based Laravel authentication |
| Queues / cache / sessions | Database drivers (per `.env.example`) |
| Tests | **Pest** (`pestphp/pest`, Laravel plugin) |
| Code style | Laravel Pint |
| Optional AI | OpenAI-compatible API and/or **Google Gemini** (server-side keys only) |
| Optional OCR fallback | Tesseract (Smart Scan), when configured |
| Export | Client-side **html2canvas** + **jsPDF** on reports |

---

## 3. User Roles & Dashboards

Application roles are defined in `App\Enums\Role`:

| Role value | Label | Primary dashboard |
|---|---|---|
| `student` | Student | `/dashboard` |
| `admin` | Administrator (platform Super Admin) | `/admin` |
| `bank` | Bank staff user | `/bank` |

Bank staff **membership** roles (within a bank tenant) are defined in `App\Enums\Loans\BankStaffRole`:

| Membership | Typical use |
|---|---|
| `owner` | Bank portal ownership / setup |
| `admin` | Bank operations |
| `officer` | Application review operations |

All bank memberships authenticate as role `bank` and are scoped to their bank tenant.

### Student Dashboard

| Item | Detail |
|---|---|
| Role | `student` |
| Login | `/login` |
| After login | Redirect to `/dashboard` |
| Middleware | `auth` + `verified` |
| Capabilities | Personal transactions, budgets, categories, recurring, reports, intelligence, savings goals, loans as borrower, settings |
| Restrictions | Cannot access `/admin` or `/bank` portals; cannot underwrite loans |

### Admin Dashboard (platform Super Admin)

| Item | Detail |
|---|---|
| Role | `admin` |
| Login | **`/admin/login`** (separate from student/bank login) |
| After login | Always `/admin` (intended URL is cleared) |
| Middleware | `auth` + `verified` + `admin` |
| Capabilities | Users, banks (approve/reject/suspend), default categories, announcements, saving-tip templates, analytics, admin intelligence, admin notifications, admin settings |
| Restrictions | Does **not** underwrite individual student loans for a bank |

Student credentials cannot authenticate through `/admin/login`.

### Bank Portal

| Item | Detail |
|---|---|
| Role | `bank` (+ bank membership) |
| Login | `/login` (same general login as students) |
| After login | `/bank` **only if** the user’s primary bank is **Approved/Active** |
| Middleware | `auth` + `verified` + `bank` |
| Capabilities | Products, applications, loans/disbursement, staff (as permitted) |
| Pending / rejected / suspended banks | Login is blocked with a status message; portal returns 403 |

---

## 4. Judge Demo Access

**DEVELOPMENT / DEMO ONLY.** Do not publish these credentials on the public website or in production.

Seed the lending demo (idempotent):

```bash
php artisan campuscoin:seed-lending-demo
# or
npm run seed:demo
```

Credentials are defined in `DEMO_CREDENTIALS.md` and `App\Support\Demo\LendingDemoIds` (verified plaintext).

| Role | Login URL | Email | Password | Lands on |
|---|---|---|---|---|
| Student | `/login` | `student.demo@campusbank.test` | `StudentDemo@2026!` | `/dashboard` |
| Bank owner | `/login` | `bank.admin@campusbank.test` | `CampusBankDemo@2026!` | `/bank` |
| Bank officer | `/login` | `bank.officer@campusbank.test` | `CampusOfficerDemo@2026!` | `/bank` |
| Platform admin | `/admin/login` | `admin.demo@platform.test` | `AdminDemo@2026!` | `/admin` |

**Admin seeding note:** The demo admin is created only when **no** administrator already exists. If another admin is present, that account is kept and `admin.demo@platform.test` may not be created.

### Optional DatabaseSeeder account

`DatabaseSeeder` may also create:

| Role | Email | Password source | Notes |
|---|---|---|---|
| Student (factory default) | `test@example.com` | Factory default `password` (`UserFactory`) | Created if missing; not the lending-demo student |

### Demo bank / product (after lending seed)

| Item | Value |
|---|---|
| Bank | Campus Demo Bank (Approved) |
| Product | Campus Student Support Loan (published/active) |
| Walkthrough application | `APP-DEMO-WALKTHROUGH` (Submitted) |
| Active repayment demo | `APP-DEMO-ACTIVE` |

Full lending walkthrough: `DEMO_LOAN_FLOW.md`.

---

## 5. Exact Login Procedure

### Step 1 — Dependencies

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env` (see `docs/setup/mysql.md`).

### Step 2 — Database

```bash
php artisan migrate
php artisan db:seed                  # default categories + optional local demo
php artisan campuscoin:seed-lending-demo   # recommended for judge lending accounts
```

### Step 3 — Run the app

**Option A — concurrent (recommended):**

```bash
composer run dev
```

This starts `php artisan serve`, the queue listener, and `npm run dev`.

**Option B — separate terminals:**

```bash
php artisan serve
npm run dev
```

### Step 4 — Open the application

Default local URL from Laravel: **`http://127.0.0.1:8000`** (or `APP_URL` from `.env`).

Public home: `/`

### Step 5 — Log in by role

**Student / Bank staff**

1. Open `/login`  
2. Enter the demo email and password from the table above  
3. Submit  

**Platform admin**

1. Open `/admin/login`  
2. Enter `admin.demo@platform.test` / `AdminDemo@2026!` (if seeded)  
3. Submit  

### Step 6 — Post-login redirects (verified)

| Who | Redirect |
|---|---|
| Admin | `/admin` |
| Bank user with active bank | `/bank` |
| Bank user with pending/inactive bank | back to `/login` with status message |
| Student (and other non-admin/non-bank) | `/dashboard` |

---

## 6. Student Dashboard

Authenticated students (`auth` + `verified`) can access:

| Feature | Route | Purpose |
|---|---|---|
| Dashboard | `/dashboard` | Overview, Money Pulse, budgets vs actual, highlights |
| Transactions | `/transactions` | List / create / show / edit / soft-delete / restore |
| Import CSV | `/transactions/import` | Historical CSV import with preview/confirm |
| Categories | `/categories` | Personal categories + default system categories |
| Budgets | `/budgets` | Monthly category budgets |
| Recurring | `/recurring-transactions` | Templates + approve/reject occurrences |
| Reports | `/reports` | Filters, analytics, client-side PDF/PNG export |
| Spending Alerts | `/spending-alerts` | Unusual spending signals |
| Month Forecast | `/month-forecast` | Deterministic next-month style forecast |
| Savings Goal | `/savings-goal` | Goals and contributions |
| Monthly Review | `/monthly-review` | Monthly financial review |
| Duplicate Review | `/duplicate-review` | Possible duplicate pairs |
| Intelligence | `/intelligence` | Pulse / forecast / signals + optional AI assistant |
| Insights | `/insights` | AI/facts-first monthly insights (regenerate) |
| Bookmarks | `/bookmarks` | Bookmarked tips/insights |
| Notifications | `/notifications` | In-app notification center |
| Student Loan Center | `/loans` | Borrower loan center |
| Loan products (auth) | `/loans/products` | Browse products to apply |
| Settings | `/settings/profile`, `/settings/password`, `/settings/appearance` | Profile & preferences |
| Smart Scan | `POST /document-scan/process`, `POST /document-scan/confirm` | Receipt/document → reviewable transaction (UI from student app) |

Public (guest) student-loan marketing/discovery: `/student-loans`.

---

## 7. Admin Dashboard

Admin login: `/admin/login` → Control Center `/admin`.

| Feature | Route | Purpose |
|---|---|---|
| Admin dashboard | `/admin` | Platform overview |
| Users | `/admin/users` | List/show; enable/disable/reset-access |
| Banks | `/admin/banks` | Partner banks |
| Pending bank requests | `/admin/banks?status=pending` | Registration queue |
| Bank review | `/admin/banks/{bank}` | Approve / reject / suspend / reactivate |
| Default categories | `/admin/categories` | System category management |
| Announcements | `/admin/announcements` | Platform announcements |
| Saving tips | `/admin/saving-tips` | Tip templates |
| Analytics | `/admin/analytics` | Admin analytics |
| AI Intelligence | `/admin/intelligence` | Admin intelligence view |
| Notifications | `/admin/notifications` | Admin notification center |
| Settings | `/admin/settings` (+ profile/password/appearance) | Admin account settings |
| Logout | `POST /admin/logout` | Ends session → `/admin/login` |

**Bank approval ≠ loan approval.** Super Admin activates banks; bank officers/admins underwrite student applications.

---

## 8. Bank Portal Dashboard

Login: `/login` with an approved bank staff account → `/bank`.

| Feature | Route | Purpose |
|---|---|---|
| Overview | `/bank` | Queues, onboarding checklist when no products |
| Applications | `/bank/applications` | Review queue |
| Application detail | `/bank/applications/{application}` | Under review / request info / approve / reject |
| Products | `/bank/products` | List products |
| Create product | `/bank/products/create` | Create draft product |
| Publish product | `POST /bank/products/{product}/publish` | Make product student-visible |
| Loans | `/bank/loans` | Active / disbursement queue |
| Confirm disbursement | `POST /bank/loans/{loan}/confirm-disbursement` | Posts loan income to student ledger when confirmed |
| Staff | `/bank/staff` | Invite/manage bank staff |

### Public bank registration (guest)

| Feature | Route | Purpose |
|---|---|---|
| Register Your Bank | `/bank/register` | Submit partnership request → **Pending** |
| Registration status | `/bank/registration/status?ref=BANK-REQ-…` | Safe public status page |

---

## 9. Core Features

Verified major capabilities:

- Session authentication, email verification, password reset  
- Student profile (name, academic year, allowance, savings fields)  
- Income/expense transactions with soft delete/restore  
- Default + personal categories (type-matched)  
- Monthly budgets + Budget vs Actual + in-app budget alerts  
- Recurring transactions (weekly/monthly) with pending approval occurrences  
- CSV import (preview → confirm)  
- Reports with filters and client-side PDF/PNG export  
- Deterministic intelligence: activity, forecast, large/duplicate signals  
- Optional AI categorization suggestions (user confirms)  
- Optional AI monthly insights + Campus Coin AI assistant (Gemini when configured)  
- Saving tips (dismiss/bookmark)  
- Savings goals + contributions  
- Smart Scan (document → draft transaction; confirm required)  
- Multi-tenant student lending: bank registration → admin approval → products → applications → agreement → sandbox payment → ledger posting  
- Public website (home, features, how it works, about, contact, student loans, intelligence preview)  
- Dark/light/system appearance  

---

## 10. Additional Implemented Features Beyond SRS

Compared against `docs/SRS-IMPLEMENTATION-MAP.md` and `docs/ROADMAP.md` (those docs may lag; this section reflects the **current codebase**):

### Covered by original SRS direction (now implemented)

Authentication, profile fields, transactions, categories, recurring, CSV import, dashboard, budgets/alerts, reports/exports, optional AI categorization/insights, tips/bookmarks, system intelligence, public marketing pages, dark mode.

### Additional / expanded functionality (present in code; not limited to early SRS map)

| Area | What exists |
|---|---|
| **Admin Control Center** | Full admin UI (users, banks, categories, announcements, tips, analytics, intelligence)—beyond the early “foundation only” SRS note |
| **Student lending ecosystem** | Banks, products, applications, agreement, disbursement, installments, sandbox payments, finance ledger categories `Loan` / `Loan Repayment` |
| **Bank Portal** | Separate tenant portal with staff roles |
| **Public bank registration** | `/bank/register` → pending → Super Admin review |
| **Smart Scan** | Document OCR/AI assist with user confirmation |
| **Money Pulse / Intelligence UX** | Dedicated student intelligence surfaces + public preview |
| **Campus Coin AI assistant** | Dashboard-aware Gemini coach (when `GEMINI_API_KEY` is set) |

### Explicit constraints still respected

- No live external bank-account aggregation for everyday tracking  
- Lending payments are **sandbox**, not live card rails in production sense  
- AI is advisory; it does not silently mutate ledgers without user confirmation flows  

---

## 11. Dashboard Intelligence

### Deterministic insights (no AI key required)

Configured via `INTELLIGENCE_*` in `.env.example`:

- Recent activity  
- Month / category forecast from historical months  
- Unusually large transaction signals  
- Possible duplicate detection + `/duplicate-review`  
- Spending alerts surface  
- Monthly review  

These features do **not** invent balances; they calculate from the user’s own transactions.

### AI features (optional)

| Capability | Notes |
|---|---|
| Category suggestions | `POST /transactions/suggest-category`; user must confirm |
| Monthly insights | `/insights` with regenerate; facts-first |
| Campus Coin AI assistant | `/intelligence` assistant endpoint; needs `GEMINI_API_KEY` |
| Providers | `AI_PROVIDER=openai` or `gemini`; keys are **server-side only** (never `VITE_*`) |
| Model examples | `AI_MODEL=gpt-4o-mini`; Gemini locked to `gemini-3.5-flash` in docs/config |
| Fallback | Safe coach / deterministic answers when providers fail |
| Mutations | AI does **not** auto-create transactions or change balances without confirmation |

---

## 12. Transactions

- Types: `income` / `expense` (`App\Enums\TransactionType`)  
- Linked to categories with type matching  
- Create / edit / soft-delete / restore  
- History and detail views  
- CSV import pipeline  
- Smart Scan confirmation pipeline  
- Ownership enforced server-side (policies + user-scoped queries)  
- Monetary amounts use decimal columns  

---

## 13. Categories

**Default system categories** (from `DefaultCategorySeeder` — 14 defaults):

**Income:** Allowance, Part-time Job, Scholarship, Gift, Other Income, Loan  

**Expense:** Food, Transport, Hostel/Rent, Academics, Subscriptions, Entertainment, Miscellaneous, Loan Repayment  

Students may create personal categories. Defaults are system-owned (`user_id` null, `is_default` true). Admins manage defaults at `/admin/categories`.

---

## 14. Budgets

- Monthly budgets per category  
- Budget vs Actual on the student dashboard  
- Near-limit / exceeded statuses and in-app notifications (`BudgetAlertType`)  
- Validation and ownership via budget policies/controllers  

---

## 15. Recurring Transactions

- Frequencies: **weekly**, **monthly** (`App\Enums\RecurringFrequency`)  
- Occurrences wait for **approve/reject** before posting  
- Artisan generator:

```bash
php artisan campuscoin:generate-recurring
# optional: --date=YYYY-MM-DD
```

- Scheduled daily at **00:15** (`routes/console.php`)  

---

## 16. Reports & Analytics

Route: `/reports`

Verified capabilities (see `docs/architecture/reports.md`):

- Date / category filters  
- Category, daily, weekly summaries  
- Multi-month income vs expense views  
- Client-side PDF and PNG export (html2canvas + jsPDF)  

---

## 17. Notifications & Alerts

- Student notification center: `/notifications`  
- Budget near-limit / exceeded notifications  
- Spending alerts: `/spending-alerts`  
- Admin notification center: `/admin/notifications`  
- In-app / database notifications (not email/push by default)  

---

## 18. Admin Control Center

See [Admin Dashboard](#7-admin-dashboard). Platform governance only—user access, content templates, bank partner lifecycle, analytics—not student loan underwriting.

---

## 19. Database

Major domain areas (Eloquent models / migrations; not exhaustive):

| Area | Examples |
|---|---|
| Identity | `users` |
| Finance | `categories`, `transactions`, `budgets`, `recurring_transactions`, recurring occurrences |
| Intelligence | signals, insights, saving tips / interactions, bookmarks |
| Notifications | Laravel notifications tables |
| Lending | `banks`, `bank_memberships`, `bank_verification_documents`, `loan_products`, `loan_applications`, `loans`, installments, disbursements, payments, audit logs |
| Content | announcements, tip templates |

Money is stored as **DECIMAL**, not float. Ownership is enforced in application queries/policies.

---

## 20. Business Rules & Validation (high level)

Verified patterns in the codebase:

- Authenticated + verified access for app finance routes  
- Role middleware: `admin`, `bank`  
- Policies for transactions, budgets, categories, recurring, signals, tips, bookmarks, savings goals, AI insights  
- Bank tenant isolation on bank portal routes  
- Pending banks cannot access `/bank`  
- Only published products from **active** banks appear publicly / for discovery  
- Loan state machine for apply → review → agreement → disbursement → repayment  
- Disbursement posts **Loan** income; successful repayment posts **Loan Repayment** expense (idempotent ledger service)  
- AI suggestions require explicit user confirmation before becoming financial records  
- CSRF protection (webhook path excepted for loan payment webhooks)  

---

## 21. Installation & Setup

### Requirements

- PHP **8.2+** with Composer  
- Node.js + npm (Vite toolchain)  
- MySQL **8+** for local/competition runs  
- Optional: Tesseract binary for OCR fallback; AI API keys for AI features  

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` database settings, then:

```bash
php artisan migrate
php artisan db:seed
php artisan campuscoin:seed-lending-demo
```

---

## 22. Environment Configuration

Important variables from `.env.example` (placeholders only—never commit real secrets):

| Variable | Purpose |
|---|---|
| `APP_URL` | Application URL |
| `DB_*` | MySQL connection |
| `AI_ENABLED`, `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` | Categorization / insights provider |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Campus Coin AI assistant |
| `DOCUMENT_SCAN_*` | Smart Scan / Tesseract fallback |
| `INTELLIGENCE_*` | Deterministic intelligence thresholds |

AI keys must remain **server-side**. Do not expose them to Vite as `VITE_` secrets.

---

## 23. Database Setup

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` calls:

1. `DefaultCategorySeeder` — system categories  
2. Optional `test@example.com` student (factory)  
3. `LendingDemoSeeder` in `local` / `development` environments  

Prefer the explicit lending demo command for judge accounts:

```bash
php artisan campuscoin:seed-lending-demo
php artisan campuscoin:seed-lending-demo --reset-walkthrough
```

Destructive local reset helper (preserves users/default categories; **local/dev only**):

```bash
php artisan campuscoin:reset-local-financial-data
```

---

## 24. Running the Project

```bash
composer run dev
```

Or:

```bash
php artisan serve
npm run dev
```

Open `http://127.0.0.1:8000` (unless `APP_URL` differs).

Production frontend build:

```bash
npm run build
```

---

## 25. Important Artisan Commands

| Command | Purpose |
|---|---|
| `php artisan campuscoin:seed-lending-demo` | Idempotent Campus Demo Bank lending scenario |
| `php artisan campuscoin:seed-lending-demo --reset-walkthrough` | Reset walkthrough application to Submitted |
| `php artisan campuscoin:generate-recurring` | Create pending recurring occurrences / reminders |
| `php artisan campuscoin:reset-local-financial-data` | Clear local financial/demo records (safe-guarded for local) |
| `php artisan schedule:work` / `schedule:run` | Run scheduler (includes daily recurring generation) |
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Run seeders |
| `php artisan test` | Run Pest test suite |
| `php artisan serve` | Local HTTP server |

---

## 26. Testing

```bash
php artisan test
```

- Framework: **Pest** + Laravel plugin  
- Test DB: SQLite `:memory:` (`phpunit.xml`)  
- Feature coverage includes auth, finance modules, public site, AI hardening paths, and lending ecosystem flows  

Do not rely on a hard-coded “test count” in docs—run the command for the current number.

Optional browser QA scripts may exist under `scripts/`; the primary automated suite is Pest.

---

## 27. Judge Demo Flow

1. `composer install` && `npm install` && configure `.env` && `php artisan migrate`  
2. `php artisan campuscoin:seed-lending-demo`  
3. `composer run dev`  
4. Open `/` — public website  
5. Open `/intelligence/preview` — public Intelligence showcase  
6. Open `/student-loans` — published products  
7. Open `/bank/register` — bank partnership entry (optional demo)  
8. Log in as **Student** → `/dashboard`  
9. Review Transactions, Budgets, Categories, Recurring, Reports  
10. Open `/intelligence`, `/month-forecast`, `/monthly-review`, `/spending-alerts`  
11. Open `/loans` — loan center; inspect walkthrough / active demo loans if seeded  
12. Log out  
13. Log in as **Bank officer** → `/bank/applications` — review `APP-DEMO-WALKTHROUGH`  
14. Log in as **Bank owner** → products / disbursement as needed  
15. Log in at `/admin/login` as **Admin** → `/admin/banks` pending/active partners  
16. Confirm admin cannot replace bank underwriting for student applications  

Detailed lending payment steps: `DEMO_LOAN_FLOW.md`.

---

## 28. Project Structure

```text
app/
  Console/Commands/     Custom Artisan commands
  Enums/                Roles, finance, lending enums
  Http/Controllers/     Web, Auth, Admin, Bank, Loans, PublicSite, …
  Models/               Eloquent models
  Policies/             Authorization policies
  Services/             Domain services (AI, loans, intelligence, …)
  Support/Demo/         Lending demo IDs + bootstrap
database/
  migrations/
  seeders/
  factories/
resources/js/
  pages/                Inertia React pages (welcome, dashboard, admin, bank, …)
  components/
  layouts/
routes/
  web.php               Student app + admin + public
  loans.php             Lending + bank register + bank portal
  auth.php / settings.php
  console.php           Scheduler
docs/                   Architecture + SRS maps + setup
tests/                  Pest feature/unit tests
DEMO_CREDENTIALS.md     Demo logins (dev only)
DEMO_LOAN_FLOW.md       Lending walkthrough
```

---

## 29. Security & Data Integrity

Verified protections:

- Session authentication; password hashing (bcrypt)  
- Email verification middleware on app routes  
- Role middleware (`admin`, `bank`)  
- Eloquent policies for owned resources  
- Bank tenant isolation  
- Server-side validation on forms/APIs  
- CSRF (except documented loan payment webhook)  
- Soft deletes on transactions where implemented  
- AI/OCR keys kept server-side  
- Demo passwords documented only for local evaluation  

This is not a claim of “perfect security”—treat demo credentials as non-production.

---

## 30. Future Enhancements

**Not currently claimed as shipped product features:**

- Native mobile apps  
- Live bank-account aggregation / Open Banking  
- Multi-currency wallets  
- Production card payment rails (beyond sandbox)  
- Push/email notification channels as primary delivery  
- Notes/sharing social features (bookmarks exist; full notes/sharing called out as incomplete in older SRS map)  

---

## 31. Project Status

Factual status based on the current repository:

- Student finance core (transactions → budgets → reports → intelligence) is implemented  
- Admin Control Center is implemented  
- Bank registration → approval → products → student applications → sandbox repayment → ledger posting is implemented  
- Optional AI/Gemini and Smart Scan depend on environment configuration  
- Automated tests: run `php artisan test`  
- Some roadmap/SRS docs may still describe older phase labels; prefer this README and the live routes for evaluation  

---

## 32. Conclusion

CampusCoin is a full-stack student finance product: track and plan money, understand it with deterministic (and optional AI) intelligence, and—when banks partner on the platform—discover and manage student loans through a real multi-tenant workflow. Judges can install the stack, seed the documented demo accounts, and exercise Student, Bank, and Admin dashboards using the credentials and routes listed above.

---

## Related documentation

| File | Contents |
|---|---|
| `DEMO_CREDENTIALS.md` | Demo emails/passwords |
| `DEMO_LOAN_FLOW.md` | End-to-end lending walkthrough |
| `docs/setup/mysql.md` | MySQL setup |
| `docs/SRS-IMPLEMENTATION-MAP.md` | Historical SRS mapping |
| `docs/architecture/` | Module architecture notes |
