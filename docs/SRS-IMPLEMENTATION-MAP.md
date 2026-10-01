# SRS Implementation Map — Campus Coin

Lightweight mapping of SRS areas to planned phases.  
This is **not** a claim of completion.

| SRS area | Planned phase | Current status (after Phase 10) |
|---|---|---|
| Student registration / login / logout / session | Existing + Phase 2 | Available |
| Password recovery / reset | Existing | Available |
| Email verification enforcement | Phase 2 | Available |
| Separate administrator login / access | Phase 2 (+ later admin UI) | Foundation available; **no admin dashboard** |
| Profile: editable name | Existing | Available |
| Profile: academic year, allowance, savings goal | Phase 2 | Available |
| Transactions CRUD (income/expense, amount, description, date) | Phase 3 | Available |
| Soft-delete / retain deleted records | Phase 3 | Available (soft deletes; not full edit-audit log) |
| Default + personal categories | Phase 4 | Available |
| Transaction ↔ category linkage | Phase 4 | Available (historical may be Uncategorized) |
| Recurring transactions | Phase 4 | Available |
| Optional CSV historical import | Phase 4 | Available |
| Personalized dashboard (finance metrics) | Phase 5 | Available |
| Monthly reports + filters | Phase 7 | Available |
| PDF / image export | Phase 7 | Available (client-side html2canvas + jsPDF) |
| Optional AI expense categorization | Phase 8 | Available (optional; mocked in tests) |
| Optional AI monthly insights | Phase 9 | Available (facts-first; AI optional) |
| Personalized saving tips | Phase 9 | Available (deterministic + dismiss/bookmark) |
| Budgets & alerts | Phase 6 | Available (in-app DB notifications; no email/push) |
| Budget vs Actual on dashboard | Phase 6 | Available |
| Bookmarks / notes / sharing | Phase 9 | Bookmarks available (notes/sharing not implemented) |
| Optional system intelligence | Phase 10 | Available (activity, forecast, large/duplicate signals) |
| Admin panel | Later | Not implemented |
| Dark mode | Existing | Available |
| Homepage sitemap | Phase 1 | Available |
| Competition documentation pack | Phase 14 | Foundation docs only |
| No bank / payment integration | All phases | Constraint respected |

## Labels

- **Available** — present in the codebase and usable  
- **Foundation only** — supporting structure without the full SRS feature  
- **Not implemented** — planned for a later phase
