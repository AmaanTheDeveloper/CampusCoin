# Demo loan flow walkthrough

**DEVELOPMENT / DEMO ONLY**

This document uses the application's **real** routes, auth, bank tenancy, loan state machine, repayment schedule engine, and sandbox payment adapter.

## 1. Prepare the database

```bash
# Local / development only
php artisan migrate
php artisan campuscoin:seed-lending-demo
# equivalent:
npm run seed:demo
```

Idempotent: running the seed twice does not duplicate the demo bank, users, product, or applications.

Optional full local refresh (destructive to DB — never use in production):

```bash
php artisan migrate:fresh --seed
php artisan campuscoin:seed-lending-demo
```

Reset only the bank-review walkthrough (`APP-DEMO-WALKTHROUGH` → `SUBMITTED`):

```bash
php artisan campuscoin:seed-lending-demo --reset-walkthrough
```

## 2. Run the app

```bash
composer run dev
# or separately:
php artisan serve
npm run dev
```

Public home: [http://127.0.0.1:8000](http://127.0.0.1:8000)

## 3. What the seed creates

| Entity | Detail |
|---|---|
| Bank | Campus Demo Bank (`approved`) |
| Owner | `bank.admin@campusbank.test` |
| Officer | `bank.officer@campusbank.test` |
| Student | `student.demo@campusbank.test` |
| Product | Campus Student Support Loan (published) |
| Application A | `APP-DEMO-WALKTHROUGH` → **Submitted** (bank review demo) |
| Application B | `APP-DEMO-ACTIVE` → real workflow → **Active** loan + finance **Loan** income transaction (no fake payments) |

Credentials: see `DEMO_CREDENTIALS.md`.

## 4. Important routes

| Surface | Path / named route |
|---|---|
| Public home | `/` · `route('home')` |
| Public student loans | `/student-loans` · `route('student-loans')` |
| Student login | `/login` · `route('login')` |
| Student dashboard | `/dashboard` · `route('dashboard')` |
| Student loan center | `/loans` · `route('loans.index')` |
| Loan products | `/loans/products` · `route('loans.products.index')` |
| Bank dashboard | `/bank` · `route('bank.dashboard')` |
| Bank applications | `/bank/applications` · `route('bank.applications.index')` |
| Bank loans | `/bank/loans` · `route('bank.loans.index')` |
| Admin login | `/admin/login` · `route('admin.login')` |
| Admin banks | `/admin/banks` · `route('admin.banks.index')` |
| Sandbox checkout | `/loans/payments/{payment}/sandbox` |

## 5. Full bank-review walkthrough (Application A)

1. Open `/student-loans` — confirm **Campus Student Support Loan** from **Campus Demo Bank** is listed.
2. Login as student (`student.demo@campusbank.test`).
3. Open `/loans` (Loan Center).
4. Open walkthrough application `APP-DEMO-WALKTHROUGH` **or** start a new application from Products if you prefer a fresh draft.
5. Logout.
6. Login as bank owner (`bank.admin@campusbank.test`) → lands on `/bank`.
7. Confirm dashboard tiles show real counts (new applications, etc.).
8. Open **Applications** → open `APP-DEMO-WALKTHROUGH`.
9. Review applicant, request, documents, history.
10. **Approve** (in-panel confirmation shows amount / tenure / rate).
11. Open the created loan → confirm agreement is pending until the student accepts.
12. Logout → login as student → open the loan → **Accept agreement**.
13. Student should see **Awaiting disbursement**.
14. Logout → login as bank → open the loan → **Confirm disbursement** (only after funds would actually be sent; this is a demo confirmation).
15. Loan becomes **Active** with a real repayment schedule from the schedule engine.
16. Confirm a **Loan** income transaction was posted (see §5b).
17. Logout → login as student → `/loans` → open active loan → **Pay installment**.
18. Complete sandbox checkout (see §7).
19. Verify payment result panel, installment paid, outstanding balance down, **one** Loan Repayment expense transaction.
20. Login as bank → open loan → verify payment appears and installment status updated.

### 5b. After bank confirms disbursement — finance ledger check

1. Login as Demo Student
2. Open `/loans`
3. Open the Active Loan
4. Confirm **Loan disbursed** panel shows the disbursed amount
5. Click **View transaction** (or open `/transactions`)
6. Confirm Type = Income / inflow
7. Confirm Category = **Loan**
8. Confirm amount matches the disbursement
9. Confirm dashboard / Money Pulse cash-flow reflects the loan inflow (borrowed funds — not salary)
10. Pay an installment via sandbox
11. Open `/transactions` again
12. Confirm a **Loan Repayment** expense for the installment amount
13. Confirm installment is Paid and outstanding balance decreased
14. Confirm only one expense transaction exists for that payment
15. Login as bank and confirm payment/disbursement operational state

## 6. Quick repayment / payment demo (Application B)

After seeding with the default command, the student already has an **Active** demo loan from `APP-DEMO-ACTIVE`.

1. Login as `student.demo@campusbank.test`.
2. Open `/loans` → open the active loan.
3. **Pay installment** → sandbox checkout → success or failure as below.
4. Login as `bank.admin@campusbank.test` → `/bank/loans` → open the same loan → verify payment monitoring.

## 7. Sandbox payment

Uses the real sandbox adapter — full PAN/CVV fields are **display-only** and never stored.

### Success

1. On sandbox page, leave outcome as **succeeded** (or choose succeeded).
2. Optional last-four sync from the display PAN (default ends in `4242`).
3. Confirm → webhook handled by `LoanPaymentService` (HMAC/idempotency rules apply in non-local signed mode).
4. Expect: installment paid / partial, balance updated, one expense transaction, payment history entry.

### Failure

1. Choose outcome **failed** on the sandbox form.
2. Confirm.
3. Expect: error / payment-failed result, installment **not** marked paid, outstanding balance unchanged, retry available, no success expense for that attempt.

### Duplicate webhook

Replaying the same provider `event_id` must not create a second payment event or duplicate expense (covered by automated tests).

## 8. Role boundaries to spot-check

| Actor | Expectation |
|---|---|
| Student | Cannot open `/bank` |
| Bank staff | Cannot open `/admin/banks` |
| Bank A staff | Cannot open another bank's application |
| Student | Cannot approve applications |
| Super Admin | Can manage bank onboarding only — cannot underwrite student loans |

Use real login/logout — there is no insecure “switch role” control.

## 9. Repeat the approval journey

```bash
php artisan campuscoin:seed-lending-demo --reset-walkthrough
```

This clears loan/payment trees for `APP-DEMO-WALKTHROUGH` only and restores **Submitted**, so bank review → approve → agreement → disbursement can be practiced again.

## 10. Automated verification

```bash
php artisan test --filter=LendingDemo
php artisan test --filter=Loan
npm run build
```
