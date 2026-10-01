# Notifications — Campus Coin

**Phase:** 6 (implemented)

## Channel

Laravel **database** notifications only (in-app).

No email. No push.

## SRS vs implementation

| Item | Source |
|---|---|
| In-app alerts when category nears / exceeds budget | SRS |
| 80% / 100% thresholds | **Implementation choice** |
| Dedup via `budget_alerts` | **Implementation choice** |
| No reversal notifications when spending drops | **Implementation choice** |

## Budget alerts

| Type | When |
|---|---|
| `budget_near_limit` | Usage ≥ 80% (also recorded when jumping straight to exceeded) |
| `budget_exceeded` | Usage ≥ 100% |

## Deduplication

Table `budget_alerts` unique on `(budget_id, alert_type)`.

Refreshing the dashboard/budget page does **not** create duplicate alerts.

If the budget limit is raised and usage falls back to Healthy, alert lock rows are cleared so thresholds can fire again later. Existing notification messages remain stored.

Falling below a threshold does **not** create reversal notifications.

## Center

`/notifications` — list, mark one read, mark all read.

Opening “mark as read” for a budget-related notification redirects to `/budgets` when a `budget_id` is present.

Unread count is shared via Inertia (`unread_notifications_count`) for the bell badge.

Student inbox and badge use `App\Services\Notifications\StudentNotificationService`, which queries `$user->notifications()` and **excludes** rows with `data.audience = admin`. Admin operational alerts stay on `AdminNotificationService` / the admin center only.
