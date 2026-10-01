/**
 * Tunable thresholds for Smart Scan card auto-detect.
 *
 * Strict card-identity layers (geometry + visual pattern + tracking).
 * Soft image-quality floors (usable webcam OK — not studio sharpness).
 * Field presence is validated server-side after one capture.
 */

export const CARD_SCAN_CONFIG = {
    /** Sample interval (~5 fps). */
    sampleIntervalMs: 180,

    /** Continuous valid hold required before capture (mandatory 5s). */
    readyStableMs: 5000,

    /** Give up waiting for a valid card. */
    timeoutMs: 45_000,

    /** Guide ROI as fractions of the video frame (ISO 7810-ish card window). */
    roi: { x: 0.12, y: 0.22, w: 0.76, h: 0.48 },

    /** Downscaled analysis size (width); height derived from card aspect. */
    sampleWidth: 128,
    cardAspect: 1.586,

    /**
     * Foreground mass relative to ROI (real adaptive mass only — no mid-tone boost).
     */
    minFillRatio: 0.34,
    maxFillRatio: 0.88,
    /** Below this → searching (barely any object mass). */
    minDetectFillRatio: 0.18,

    /** Content bounding-box aspect (object shape, not canvas size). ISO ~1.586. */
    minContentAspect: 1.32,
    maxContentAspect: 1.92,

    /** Density of FG pixels inside the content bbox (rejects sparse noise). */
    minBboxDensity: 0.42,

    /** Laplacian variance proxy (higher = sharper). Mild blur is OK. */
    minBlurScoreReady: 0.04,
    minBlurScoreWarning: 0.018,

    /** Fraction of near-white pixels that counts as severe glare only. */
    maxGlareRatio: 0.5,

    /** Contrast / edge floors for ready (usable, not perfect). */
    minContrastReady: 0.14,
    minEdgeEnergyReady: 0.065,
    minPerimeterEdgeReady: 0.04,

    /** Soft floors that still count as a candidate object (not yet a card). */
    minContrastDetect: 0.12,
    minEdgeEnergyDetect: 0.05,

    /** Horizontal information-band edge energy (PAN / expiry-like zones). */
    minPanBandEnergy: 0.055,
    minExpiryBandEnergy: 0.04,

    /** Mean luminance (0–255) below this → improve lighting. */
    minMeanLuminance: 28,

    /** Centroid offset from ROI center (fraction of ROI) before position hints. */
    positionTolerance: 0.18,

    /** Border mass fraction that suggests the card is clipped by the guide. */
    borderMassWarn: 0.28,

    /** L5 tracking: max deltas vs locked track key during the 5s hold. */
    maxCenterDelta: 0.08,
    maxSizeDelta: 0.12,
    maxAspectDelta: 0.12,
} as const;

export type CardScanConfig = typeof CARD_SCAN_CONFIG;
