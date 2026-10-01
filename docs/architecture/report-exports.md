# Report Exports — Campus Coin

**Phase:** 7 (implemented)

## Approach

**Client-side** export from a dedicated off-screen export DOM (`report-export-container` pattern):

| Format | Library |
|---|---|
| PNG image | `html2canvas` (scale 2) |
| PDF | `jspdf` + same canvas capture |

One calculation contract (`ReportService` → Inertia props). Exports never recalculate totals.

## Export content

Light document layout (not a browser screenshot):

- Campus Coin + tagline
- Report period + generated timestamp (app timezone)
- Active filter labels
- Summary metrics
- Category / daily / weekly / six-month charts
- Transaction detail sample (first 40 rows)

No sidebar, buttons, nav, tooltips, user IDs, or framework metadata.

## Empty data

Export buttons are **disabled** when `has_data` is false (page shows the empty-state UI instead). The off-screen export document still renders a “No transactions found” message if ever captured with empty data.

## Capture readiness

Before `html2canvas` runs, the client waits for:

- document fonts (best-effort)
- non-zero export surface size
- Recharts containers with non-zero width/height (`exportMode` fixed chart sizes)
- SVG surfaces painted

Exports always force a white background via `html2canvas` options + `onclone` styles so dark mode UI does not produce unreadable output.

## Tailwind / oklch note

`html2canvas` cannot parse Tailwind v4 `oklch()` colors. The export clone **removes stylesheets** and the export document uses **inline hex styles** only so capture succeeds.

## Filenames

Sanitized examples:

- `campus-coin-report-2026-09.pdf`
- `campus-coin-report-2026-09-01-to-2026-09-15.png`

## Security

No public export URLs. Data stays in the authenticated browser session. Cross-user data cannot appear because the Inertia payload is owner-scoped.

## Limitations

Automated tests cover the data contract and auth. Pixel-perfect PDF/PNG output requires **manual browser QA**.

## Print

`@media print` hides chrome; interactive dark mode is allowed, but exports always use a white document background.
