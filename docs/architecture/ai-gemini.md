# Campus Coin — Gemini AI (Phase AI-1)

## Principle

Extend the existing AI provider abstraction. Do not rewrite categorization, monthly insights, or saving tips.

## Identity

Campus Coin AI is the **Student Dashboard Assistant** and personal financial coach for authenticated students — not a general chatbot and not a fixed question menu.

Priority: **Campus Coin data → deterministic coach recommendation → dashboard knowledge → Gemini natural explanation**.

## Provider switch

| Setting | Value |
|---|---|
| `AI_PROVIDER` | `openai` (categorization/insights) or `gemini` |
| `GEMINI_API_KEY` | server-only; required for Campus Coin AI assistant |
| `GEMINI_MODEL` | **`gemini-3.5-flash` only** |
| `GEMINI_API_VERSION` | `v1beta` |
| Transport | Laravel HTTP → `generateContent` + `x-goog-api-key` |

## Architecture

```
FinancialAssistantService
  → social / OOS / mutation gates
  → FinancialContextBuilder
  → CoachRecommendationEngine (P0–P4)
  → AssistantDomainResolver (semantic domain families)
  → CampusCoinDashboardKnowledge (verified student pages + links)
  → concise deterministic fact/nav answers OR coach + optional Gemini narration
  → Answer / facts_used / next_action / links[]
```

### CampusCoinDashboardKnowledge

Application-controlled map of real student routes (`/dashboard`, `/transactions`, `/budgets`, `/recurring-transactions`, `/intelligence`, `/monthly-review`, …). Methods: `all()`, `forDomain()`, `sliceForDomains()`, `link()`, `resolveNavPage()`.

Links are attached **only** via `link()` / named routes that exist. Gemini never invents URLs; invented paths in model text are stripped server-side.

### AssistantDomainResolver

Synonym clusters (not exact prompt menus): `navigation`, `balance`, `spending`, `category_focus`, `budgets`, `recurring`, `savings`, `coach`, `monthly_review`, `forecast`, `alerts`, `feature_explain`, `open`.

### CoachRecommendationEngine

Deterministic priorities:

- **P0** safety (balance / recurring pressure)
- **P1** budget pressure (over / near)
- **P2** savings opportunity
- **P3** largest named-category optimization
- **P4** healthy maintain

Optional `suggested_page` id (e.g. `intelligence`, `transactions`) for verified UI links.

Uncategorized is never the primary named recommendation — only a data-quality note.

### Natural language

Intent uses **semantic domain families**. Exact-string matching is not the primary mechanism. When Gemini is available, open/coach questions receive `coach_recommendation` + a sliced `dashboard_knowledge` payload for explanation.

### Concise modes

| Ask type | Behavior |
|---|---|
| Navigation / feature explain | Short purpose + `links[]` to the real page |
| Balance / category / budgets / etc. | Deterministic fact from context builders + verified link |
| Coach / save more | CoachRecommendationEngine + `/intelligence` link |
| Open / ambiguous | Gemini with knowledge slice; server still attaches links |

### Out of scope

Exact reply (unchanged):

> I'm sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.

Social greetings (Hello / How are you? / What is your name? / Who are you?) and off-topic chat map to this reply and must not produce financial advice or links.

### Conversation history

Optional `history` max **8** turns `{role, content}`. Frontend renders a ChatGPT-style USER / ASSISTANT thread; history is session-only (no DB table). Follow-ups include Why? / How much? / What about X? / Where can I see it?.

## Response contract (UI)

| Field | UI label |
|---|---|
| `answer` | Answer |
| `facts_used` | Based on (hidden when empty / short nav-factual modes) |
| `next_action` | Suggested action |
| `links` | View in Campus Coin (Inertia links; verified routes only) |
| `response_type` | financial_action / financial_explanation / financial_follow_up / income_plan / general_out_of_context |

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/intelligence` | Intelligence + Campus Coin AI panel |
| POST | `/intelligence/assistant` | Ask (`message` + optional `history`) |

## Income allocation

`IncomeAllocationPlanner` order:

1. Recurring commitments (capped)
2. Savings (goal-aware or starter share)
3. Named categories by history/budget **weights**, each **need-capped** to remaining budget room or observed category spend
4. Buffer receives surplus (and rounding remainder)

Uncategorized is never an allocation target. Totals always equal incoming amount via `MoneyMath`.

## Security

- API keys never sent to Inertia/Vite
- Ownership from session only
- Assistant is read-only
- XSS: strip tags; React text rendering
- Links never invent user IDs or unregistered routes

## Tests

All automated tests use `FakeAssistantProvider` / `Http::fake` — never live Gemini.

See `tests/Feature/Ai/DashboardAwareAssistantTest.php` for domain, knowledge-route, nav href, OOS, coach+intelligence link, and Gemini-failure link coverage.
