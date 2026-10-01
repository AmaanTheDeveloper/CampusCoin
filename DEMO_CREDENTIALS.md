# Demo credentials

**DEVELOPMENT / DEMO ONLY**

These accounts are for local walkthroughs of the CampusCoin multi-bank lending system.
Do **not** use them in production. Do **not** publish them on the public website.

Seed command:

```bash
php artisan campuscoin:seed-lending-demo
# or
npm run seed:demo
```

Reset the bank-review walkthrough application back to `SUBMITTED`:

```bash
php artisan campuscoin:seed-lending-demo --reset-walkthrough
# or
npm run seed:demo:reset
```

---

## Student

```text
Email:
student.demo@campusbank.test

Password:
StudentDemo@2026!
```

Name: `Demo Student`  
Role: Student

---

## Bank owner / admin

```text
Email:
bank.admin@campusbank.test

Password:
CampusBankDemo@2026!
```

Role: Bank staff (`owner` membership)  
Tenant: **Campus Demo Bank**

---

## Bank officer

```text
Email:
bank.officer@campusbank.test

Password:
CampusOfficerDemo@2026!
```

Role: Bank staff (`officer` membership)  
Tenant: **Campus Demo Bank**

---

## Super Admin

Only seeded when **no** administrator already exists in the database.

```text
Email:
admin.demo@platform.test

Password:
AdminDemo@2026!
```

Role: Administrator  
Admin login route: `/admin/login`

If an admin already exists, that account is preserved and this demo admin is **not** created.

---

## Demo bank & product

| Item | Value |
|---|---|
| Bank | Campus Demo Bank |
| Status | Approved |
| Official email | demo@campusbank.test |
| Registration | DEMO-BANK-001 |
| Product | Campus Student Support Loan |
| Product status | Active / published |
| Walkthrough application | `APP-DEMO-WALKTHROUGH` (Submitted) |
| Active repayment demo | `APP-DEMO-ACTIVE` → real Active loan (no seeded payments) |

---

## Security notes

- Passwords are demo-only `.test` accounts.
- Payment provider secrets stay in `.env` — never commit real secrets.
- Seeding does **not** bypass authentication, authorization, or the loan state machine.
