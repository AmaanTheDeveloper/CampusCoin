# Authentication & Authorization — Campus Coin

**Phase:** 2  
**Status:** Implemented foundation (no admin dashboard, no finance modules)

## Email verification policy

- `User` implements Laravel `MustVerifyEmail`.
- Protected application routes use middleware: `auth` + `verified`.
- Unverified users may access verification routes and logout only (plus guest/public pages).
- Changing email on the profile page clears `email_verified_at` and requires re-verification.
- Verification uses Laravel signed temporary URLs (not a custom token system).

## Roles

- Central enum: `App\Enums\Role` (`student`, `admin`).
- Default registration role is always `student` (server-assigned).
- `role` is **not** mass-assignable.
- Gate: `access-admin` → `$user->isAdmin()`.
- Middleware alias: `admin` → `EnsureUserIsAdmin`.

## Admin access boundary

| Path | Purpose |
|---|---|
| `GET/POST /admin/login` | Separate admin login (no registration, no role picker) |
| `GET /admin` | Minimal foundation page proving authorization |
| `POST /admin/logout` | Admin logout |

Student credentials cannot authenticate through `/admin/login` (same generic auth failure message).  
Students receive **403** on `/admin`.  
Unverified admins are sent to the verification notice before `/admin`.

**Not included:** admin dashboard, user management, analytics, CRUD.

## Student profile fields

| Field | Type | Notes |
|---|---|---|
| `name` | string | Required |
| `email` | string | Unique; change resets verification |
| `academic_year` | enum string nullable | Controlled options via `App\Enums\AcademicYear` |
| `monthly_allowance` | `DECIMAL(12,2)` nullable | Non-negative |
| `savings_goal` | `DECIMAL(12,2)` nullable | Non-negative |

Registration stays lean (name/email/password). Profile fields are completed in Settings → Profile.

## Ownership

Profile updates always use `$request->user()`. Client-supplied `user_id` / `role` are ignored.

## Shared Inertia auth props

Shared globally: `id`, `name`, `email`, `role`, `email_verified_at`, `academic_year`.  
**Not** shared globally: `monthly_allowance`, `savings_goal`, password, tokens.
