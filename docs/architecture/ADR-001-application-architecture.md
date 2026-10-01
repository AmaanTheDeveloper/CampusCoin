# ADR-001: Application Architecture

**Status:** Accepted  
**Date:** 2026-09-25  
**Phase:** 1 — Foundation

## Context

Phase 0 confirmed that Campus Coin is built on the Laravel React Starter Kit:

- Laravel 12 backend
- React 19 frontend
- Inertia.js as the bridge (server-driven page visits and shared props)
- Ziggy for route helpers
- Tailwind CSS + shadcn/Radix UI components

An earlier planning brief assumed a separate React SPA + Laravel REST API (`routes/api.php`, Sanctum/Passport). That architecture is **not** what this repository implements.

## Decision

**Retain Laravel + React + Inertia.js as the application architecture.**

Do **not** rewrite into a standalone SPA with React Router and a public REST API for Campus Coin’s primary UI.

## Why Inertia is retained

1. **Already implemented and working** — authentication, settings, layouts, and tests are built around Inertia.
2. **Faster delivery for competition** — avoids a costly SPA rewrite before domain features exist.
3. **Simpler security model for the UI** — session cookies, CSRF, and Laravel middleware already protect web routes.
4. **Less client complexity** — no separate API client, auth token store, or React Query layer required for the main app.
5. **Fits the product** — Campus Coin is a multi-page student web app, not a third-party API platform.

## Why a full SPA rewrite is not being performed

- Domain features (transactions, budgets, reports, AI, admin) are not yet implemented.
- Rewriting the shell would delay SRS delivery without improving student value.
- Introducing Sanctum/Passport, Axios, and React Query “for the future” would add unused surface area.

## Current request flow

```
Browser (React pages)
    ↓  Inertia visits / form posts
Laravel web routes (routes/web.php, auth.php, settings.php)
    ↓  middleware (web, auth, …) + HandleInertiaRequests
Controllers / Form Requests
    ↓
Eloquent models
    ↓
MySQL (competition/production target)
```

## Implications for future development

- New student and admin pages should be Inertia React pages under `resources/js/pages/`.
- New endpoints should primarily be **web routes** returning Inertia responses or redirects.
- Shared data (auth user, flash messages, brand name) continues via `HandleInertiaRequests`.
- Authorization will use Laravel policies/gates on controllers (Phase 2+), not a separate API gateway.
- Validation stays in Form Requests; business logic may later move into services without changing the transport layer.

## How domain data should flow (within Inertia)

1. User navigates or submits a form via Inertia.
2. Laravel controller validates, authorizes, and loads/scopes Eloquent data **for the authenticated user**.
3. Controller returns `Inertia::render('page-name', [...props])`.
4. React page renders props; mutations use Inertia `useForm` / `router` helpers.

Optional JSON endpoints may be added later for narrow cases (e.g. lightweight autocomplete), but they are not the default architecture.

## When a separate API would become necessary

Consider a dedicated REST/JSON API only if one of these becomes a hard requirement:

- A native mobile app or third-party integration needs the same backend.
- A public developer API is an explicit product goal.
- Real-time/offline clients cannot use session-based Inertia visits.

Until then, Inertia remains the source of truth for the Campus Coin web application.

## Consequences

**Positive**

- Preserves working auth and tests.
- Keeps the stack coherent for TechWiz End-to-End Web Solutions.
- Reduces accidental dual-stack drift (SPA routes vs Laravel routes).

**Trade-offs**

- Not a pure “API-first” backend.
- Charting/export/AI features will still run through Laravel (pages, jobs, or occasional JSON), not a separate SPA API layer.

## Related documents

- `docs/architecture/database-blueprint.md`
- `docs/setup/mysql.md`
- `docs/ROADMAP.md`
