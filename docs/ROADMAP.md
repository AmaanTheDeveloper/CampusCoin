# Campus Coin — Implementation Roadmap

Student-first budgeting and expense tracking: **Smart Spending Student Style**.

Architecture: Laravel + React + Inertia.js → Eloquent → MySQL (competition target).  
See `docs/architecture/ADR-001-application-architecture.md`.

| Phase | Focus | Status |
|---|---|---|
| **0** | Audit / discovery | Complete |
| **1** | Foundation, branding, docs, role column, MySQL strategy | Complete |
| **2** | Auth hardening, roles authorization, student profile fields | Complete |
| **3** | Transaction management | Complete |
| **4** | Categories, recurring transactions, CSV import | Complete |
| **5** | Personalized dashboard | Complete |
| **6** | Budgets, alerts, in-app notifications | Complete |
| **7** | Reports, filters, PDF/image export | Complete |
| **8** | Optional AI categorization | Complete |
| **9** | AI insights, saving tips, bookmarks | Complete |
| **10** | System intelligence (activity, forecast, large/duplicate) | **Current / Complete** |
| **11** | Admin panel | Not started |
| **12** | Security, performance, accessibility hardening | Not started |
| **13** | Testing / QA / edge cases | Not started |
| **14** | Competition polish, documentation, demo | Not started |

## Phase 10 notes

Phase 10 adds deterministic system intelligence: recent viewed/edited activity, next-month forecast from completed historical months, unusually large transaction flags, and possible-duplicate review signals. No AI dependency; no automatic financial mutations. See `docs/architecture/system-intelligence.md`.

## Phase 9 notes

Phase 9 adds AI monthly spending insights (facts-first + validated interpretation), deterministic personalized saving tips with dismiss/bookmark, and a bookmarks area. Dashboard never blocks on AI generation. See `docs/architecture/ai-insights-tips.md`.

## Phase 8 notes

Phase 8 adds optional AI expense/income categorization (server-side OpenAI-compatible provider), user-specific correction learning, and CSV batch suggestions. AI never auto-submits. See `docs/architecture/ai-categorization.md` and `docs/architecture/ai-privacy.md`.

## Phase 7 notes

Phase 7 adds `/reports` with date/category/income-source filters, category/daily/weekly summaries, six-month income vs expense, and client-side PDF/PNG export. See `docs/architecture/reports.md` and `docs/architecture/report-exports.md`.

**Manual QA carry-forward:** interactive PDF/PNG pixel review and mobile/dark-mode visual checks for exports were not completed in the Phase 7 agent session.

## Phase 6 notes

Phase 6 adds monthly category budgets, Budget vs Actual on the dashboard, and in-app database notifications (near-limit / exceeded) with deduplication. See `docs/architecture/budgets.md` and `docs/architecture/notifications.md`.

## Phase 5 notes

Phase 5 replaced the placeholder dashboard with real user-scoped analytics (summary, category breakdown, daily/monthly trends, recent transactions, rule-based highlights). Budget vs Actual is live as of Phase 6.

See `docs/architecture/dashboard.md`.

## Constraints (all phases)

- No real bank integration
- No real payment processing
- AI output is advisory only
- Never trust client-supplied ownership IDs
- Money stored as DECIMAL
