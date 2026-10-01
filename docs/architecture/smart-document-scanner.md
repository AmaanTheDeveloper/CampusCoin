# Smart Document Scanner — Implementation Report

## Summary

Student Dashboard **Smart Scan** supports two workflows:

1. **Scan Card** — layered local `CardCandidateValidator` → GREEN only when valid → **mandatory 5-second continuous hold** with track consistency → best-frame capture → one `process` → server field-presence validation → masked review with **Card verified**. Never creates a transaction. CVV digits are never returned or stored.
2. **Scan Bill / Receipt** — manual capture/upload → OCR → confirm → transaction (unchanged).

## Card validation (strict identity, tolerant quality)

| Layer | Behavior |
|---|---|
| L1 Candidate | Corner-background FG mass (no mid-tone fill boost) |
| L2 Geometry | **Content bbox** aspect ~ISO 7810, size, density, position, not clipped |
| L3 Pattern | PAN-like + expiry-like horizontal band edge energy (not OCR) |
| L4 Usability | Soft blur/glare/light floors for webcam |
| L5 Track | Position/size/aspect consistency across the 5s hold |
| Hold | `readyStableMs = 5000`; move/invalid → reset countdown |
| Server | `document_type=card` required (no unknown→card coercion) + number/expiry/CVV-**field** presence |
| Upload | Same client validator before POST; then same server path |

## Security

- CVV/PAN scrubbed via `SensitiveCardFieldScrubber`
- Cache stores presence booleans + masked fields only
- Cards cannot confirm as transactions
- Sound plays only after countdown + final validation + capture

## Verification

| Check | Command |
|---|---|
| Detector unit | `npx --yes tsx scripts/qa-card-detector-unit.ts` |
| Pest | `php artisan test --filter=DocumentScan` |
| Browser QA | `DOCUMENT_SCAN_FAKE=true` + `node scripts/qa-smart-scan.mjs` |

## Explicit non-goals

No Tesseract/MediaPipe, no per-frame OCR API, no bill-scan redesign, no CVV storage/display, no dashboard redesign.
