# AI Monthly Insights & Saving Tips — Campus Coin

**Phase:** 9 (implemented)

## Principles

- Database facts are authoritative.
- AI interprets aggregates only; it never invents amounts/categories/budgets.
- Dashboard loads factual data + existing insight + deterministic tips **without** calling AI.
- Generation/regeneration is explicit and rate-limited.

## Architecture

```
InsightDataBuilder (facts from DashboardAnalyticsService)
        ↓
MonthlyInsightService
        ↓
AiInsightProvider (OpenAI | Gemini | Null | Fake)
        ↓
Validated DTO → ai_monthly_insights
```

Tips:

```
SavingTipsService (deterministic candidates + rank_score)
        ↓
saving_tips (tip_key + period unique per user)
```

Bookmarks: polymorphic `bookmarks` table for insights and tips.

## Period

`YYYY-MM` with unique `(user_id, period)` for insights.

## Tip ranking (explainable)

Higher `rank_score` first. Examples:

| Tip | Score band |
|---|---|
| Negative balance | ~950 |
| Budget exceeded | 900+ |
| Savings goal gap | 850 |
| Budget near limit | 700+ |
| Expense up vs previous month | 600+ |
| Category concentration ≥40% | 500+ |

## Rate limit

`AI_INSIGHT_RATE_LIMIT_PER_MINUTE` (default 5).

## Fallback

If AI unavailable/invalid/timeout → factual headline/summary from backend calculations.

## Routes

| Method | Path | Purpose |
|---|---|---|
| GET | `/insights` | History + current |
| POST | `/insights/regenerate` | Generate/refresh |
| POST | `/saving-tips/{id}/dismiss` | Dismiss tip |
| GET | `/bookmarks` | Bookmarks list |
| POST | `/bookmarks/toggle` | Bookmark/unbookmark |
| DELETE | `/bookmarks/{id}` | Remove bookmark |
