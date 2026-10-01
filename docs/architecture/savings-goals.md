# Savings Goal Tracker — Campus Coin

Long-term living savings goal (one active goal per user).

**Distinct from** `users.savings_goal` (profile monthly target used by Money Pulse / saving tips). Money Pulse semantics are unchanged.

## Data

| Table | Purpose |
|---|---|
| `savings_goals` | name, target_amount, current_amount, status (`active` \| `completed`) |
| `savings_goal_contributions` | amount, note, contributed_at |

`current_amount` = sum of contributions (recomputed after each change).

## Formulas

| Metric | Formula |
|---|---|
| Progress % | `clamp((current / target) × 100, 0, 100)` |
| Remaining | `max(target − current, 0)` |
| Monthly savings rate | Prefer Month Forecast `expected_savings` when > 0; else average of ≥2 completed months with positive balance |
| Completion months | `ceil(remaining ÷ monthly_savings_rate)` |
| Scenario reduction | ~15% of category monthly average (last 90 days), rounded to Rs. 100, capped at Rs. 3,000 and ≤50% of average |
| Scenario rate | `baseline_rate + suggested_reduction` |
| Months saved | `baseline_months − scenario_months` (shown only if ≥ 1) |

## Smart scenario category selection

Prefer discretionary defaults: Entertainment, Subscriptions, Miscellaneous.  
Never suggest: Hostel/Rent, Academics, Food, Transport.  
Pick highest eligible average among preferred, else among non-excluded categories.

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/savings-goal` | `savings-goal.index` |
| POST | `/savings-goal` | `savings-goal.store` |
| PUT | `/savings-goal/{id}` | `savings-goal.update` |
| POST | `/savings-goal/{id}/contributions` | `savings-goal.contribute` |

Ownership via `SavingsGoalPolicy` (`user_id` match). Admin does not bypass.
