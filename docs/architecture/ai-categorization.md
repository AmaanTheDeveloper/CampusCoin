# AI Categorization — Campus Coin

**Phase:** 8 (implemented)

## Carried forward from Phase 7

Interactive PDF/PNG pixel QA and mobile/dark-mode visual QA for reports were **not** interactively verified in Phase 7. That remains a manual QA item; Phase 8 does not expand report scope.

## Principle

AI is an assistant. The database and the user remain the source of truth. Suggestions never auto-submit transactions.

## Provider

| Setting | Default |
|---|---|
| Provider | `openai` (default) or `gemini` via `AI_PROVIDER` |
| Model | OpenAI: `gpt-4o-mini` · Gemini: **`gemini-3.5-flash` only** |
| Transport | Laravel HTTP client (server-side only) |
| Timeout | 8 seconds |
| Rate limit | 20 requests / user / minute |
| CSV batch size | 20 descriptions / provider call |

No browser AI SDKs. `AI_API_KEY` must never appear in Vite/frontend.

When `AI_ENABLED=false` or the API key is empty, `NullCategorizationProvider` is used and the app boots normally.

## Architecture

```
POST /transactions/suggest-category
        ↓
AiCategorizationService
        ↓
AiCategorizationProvider (contract)
        ↓
OpenAi | Null | Fake (tests)
```

## Input contract (to provider)

- transaction type
- description
- allowed category **names** for that user + type

Not sent: email, balances, budgets, history, user IDs, profile fields.

## Output contract

Provider returns structured JSON suggestions. Service validates:

1. parse JSON  
2. category name exists in allow-list  
3. category accessible to user  
4. category type matches  
5. confidence ∈ high|medium|low  
6. sanitize reason (plain text)

Invalid output → safe null suggestion + neutral message.

## Confidence

Qualitative: High / Medium / Low. Suggestion signal only — not a calibrated accuracy claim.

## Learning

Table `ai_category_feedback` stores accept/correction events per user.

After **2** corrections with the same normalized description → same selected category, future suggestions for that exact normalized description short-circuit to the learned category (user-scoped only).

## CSV

Priority: exact CSV category match → manual override → learned → AI → Uncategorized.

Exact matches skip AI. AI failure leaves rows importable.

## Prompt injection

Descriptions are delimited as untrusted DATA. System rules forbid inventing categories or following instruction-like text in descriptions.

## Fallback

Timeouts, rate limits, malformed responses, and missing keys leave manual category selection fully functional.
