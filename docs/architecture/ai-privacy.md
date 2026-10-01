# AI Privacy — Campus Coin

**Phases:** 8–9

## What is sent to the AI provider

### Categorization (Phase 8)

- transaction type (`income` | `expense`)
- transaction description text
- allowed category names for the authenticated user and type

### Monthly insights (Phase 9)

Aggregate facts only, for example:

- period label
- income / expense / balance / savings rate
- top category totals and percentages
- budget status rows (category name, limit, spent, %)
- previous-month totals when available
- `insufficient_data` flag

## What is NOT sent

- email, name, password
- authentication tokens
- user ID (as a model identifier to the provider)
- full transaction history or descriptions (for insights)
- notification content
- API keys in responses or frontend assets

## What is stored locally

| Store | Contents |
|---|---|
| `ai_category_feedback` | categorization accept/correction events |
| `ai_monthly_insights` | validated headline/summary/trends/actions + factual snapshot metadata |
| `saving_tips` | deterministic tip title/body/impact/rank for a period |
| `bookmarks` | user-owned polymorphic links to insights/tips |
| Cache (optional) | short-lived categorization suggestion hashes |

Provider API keys live only in server env (`AI_API_KEY` / `GEMINI_API_KEY`).

## Campus Coin AI assistant (AI-1)

Sent (minimized aggregates):

- profile baselines (academic year, monthly allowance, savings goal)
- current-period income/expenses/available balance
- top categories, budget status
- previous/recent month aggregates
- recurring upcoming + pending approvals (labels/amounts only)

Not sent:

- passwords, tokens, API keys
- raw full transaction history
- other users' data
- internal authorization IDs as authority

## Retention claims

Campus Coin does not control third-party provider retention. Configure provider account settings separately. This documentation does not claim zero provider retention.

## Cross-user isolation

All AI and tip/bookmark records are user-scoped. Admin roles do not bypass student ownership for insights, tips, or bookmarks.
