/**
 * Shared client CardCandidateValidator (no CV libraries).
 *
 * Layers: L1 mass → L2 content geometry → L3 visual pattern bands →
 * L4 soft usability → L5 track consistency (controller).
 * Rectangle/mass alone never means "card detected".
 */

import { CARD_SCAN_CONFIG, type CardScanConfig } from '@/components/document-scan/card-scan-config';

export type CardUiState =
    | 'searching'
    | 'candidate'
    | 'adjusting'
    | 'ready'
    | 'countdown'
    | 'capturing'
    | 'failed';

/** @deprecated Prefer CardUiState — warning maps to adjusting/candidate. */
export type CardUiStateLegacy = CardUiState | 'warning';

export type CardDetectGuidance =
    | 'place_card'
    | 'move_closer'
    | 'move_back'
    | 'move_left'
    | 'move_right'
    | 'move_up'
    | 'move_down'
    | 'show_entire'
    | 'hold_steady'
    | 'hold_countdown'
    | 'card_moved'
    | 'reduce_glare'
    | 'improve_light'
    | 'adjust_card'
    | 'scanning'
    | 'failed';

export type CardTrackKey = {
    cx: number;
    cy: number;
    w: number;
    h: number;
    aspect: number;
    fill: number;
};

export type CardCandidateResult = {
    uiState: CardUiState;
    /** True only when L1–L4 pass (timer-eligible). */
    isValidCard: boolean;
    /** Soft object mass present (not yet a card). */
    isCandidate: boolean;
    /** Alias for isValidCard — kept for transitional call sites. */
    readyCandidate: boolean;
    /** Alias for isCandidate. */
    cardLike: boolean;
    contrast: number;
    edgeEnergy: number;
    perimeterEdge: number;
    fillRatio: number;
    blurScore: number;
    glareRatio: number;
    meanLuminance: number;
    centerOffsetX: number;
    centerOffsetY: number;
    contentAspect: number;
    panBandEnergy: number;
    expiryBandEnergy: number;
    qualityScore: number;
    guidance: CardDetectGuidance;
    trackKey: CardTrackKey | null;
    /** Seconds remaining during countdown (ceil); null otherwise. */
    countdownSeconds: number | null;
};

/** @deprecated Prefer CardCandidateResult. */
export type CardFrameScore = CardCandidateResult;

export type CardDetectorOptions = {
    sampleIntervalMs?: number;
    readyStableMs?: number;
    timeoutMs?: number;
    roi?: { x: number; y: number; w: number; h: number };
};

export function guidanceMessage(guidance: CardDetectGuidance, countdownSeconds?: number | null): string {
    switch (guidance) {
        case 'hold_steady':
            return '✓ Card detected';
        case 'hold_countdown':
            return countdownSeconds != null && countdownSeconds > 0
                ? `Hold steady for ${countdownSeconds} second${countdownSeconds === 1 ? '' : 's'}`
                : 'Hold steady for 5 seconds';
        case 'card_moved':
            return 'Card moved — please hold steady and try again';
        case 'scanning':
            return 'Scanning card…';
        case 'adjust_card':
            return 'Adjust your card';
        case 'move_closer':
            return 'Move closer';
        case 'move_back':
            return 'Move the card slightly back';
        case 'move_left':
            return 'Move left';
        case 'move_right':
            return 'Move right';
        case 'move_up':
            return 'Move up';
        case 'move_down':
            return 'Move down';
        case 'show_entire':
            return 'Keep the card fully inside the frame';
        case 'reduce_glare':
            return 'Reduce glare';
        case 'improve_light':
            return 'Improve lighting';
        case 'failed':
            return "We couldn't detect a card";
        default:
            return 'Place your card inside the frame';
    }
}

function bandEdgeEnergy(
    gray: Float32Array,
    width: number,
    height: number,
    x0: number,
    y0: number,
    x1: number,
    y1: number,
): number {
    const left = Math.max(1, Math.floor(x0));
    const top = Math.max(1, Math.floor(y0));
    const right = Math.min(width - 2, Math.ceil(x1));
    const bottom = Math.min(height - 2, Math.ceil(y1));
    if (right <= left || bottom <= top) {
        return 0;
    }

    let edge = 0;
    let count = 0;
    for (let y = top; y <= bottom; y += 1) {
        for (let x = left; x <= right; x += 1) {
            const i = y * width + x;
            const gx = gray[i + 1] - gray[i - 1];
            const gy = gray[i + width] - gray[i - width];
            edge += Math.abs(gx) + Math.abs(gy);
            count += 1;
        }
    }
    return count > 0 ? edge / (count * 255) : 0;
}

/**
 * Pure CardCandidateValidator — L1–L4 on a downscaled ROI ImageData.
 * L5 tracking is applied by the live controller / upload wrapper.
 */
export function validateCardCandidate(data: ImageData, config: CardScanConfig = CARD_SCAN_CONFIG): CardCandidateResult {
    const { width, height, data: px } = data;
    const total = width * height;

    const empty = (): CardCandidateResult => ({
        uiState: 'searching',
        isValidCard: false,
        isCandidate: false,
        readyCandidate: false,
        cardLike: false,
        contrast: 0,
        edgeEnergy: 0,
        perimeterEdge: 0,
        fillRatio: 0,
        blurScore: 0,
        glareRatio: 0,
        meanLuminance: 0,
        centerOffsetX: 0,
        centerOffsetY: 0,
        contentAspect: 0,
        panBandEnergy: 0,
        expiryBandEnergy: 0,
        qualityScore: 0,
        guidance: 'place_card',
        trackKey: null,
        countdownSeconds: null,
    });

    if (total === 0) {
        return empty();
    }

    const gray = new Float32Array(total);
    let sum = 0;
    let sumSq = 0;
    let glare = 0;

    for (let i = 0, p = 0; i < px.length; i += 4, p++) {
        const g = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
        gray[p] = g;
        sum += g;
        sumSq += g * g;
        if (g > 230) {
            glare += 1;
        }
    }

    const meanLuminance = sum / total;
    const variance = Math.max(0, sumSq / total - meanLuminance * meanLuminance);
    const contrast = Math.sqrt(variance) / 128;
    const glareRatio = glare / total;

    // L1 — foreground vs corner-sampled background (not mid-tone boost; dark desks ≠ card).
    const cornerPts: number[] = [];
    const c = Math.max(2, Math.floor(Math.min(width, height) * 0.06));
    for (let y = 0; y < c; y++) {
        for (let x = 0; x < c; x++) {
            cornerPts.push(gray[y * width + x]);
            cornerPts.push(gray[y * width + (width - 1 - x)]);
            cornerPts.push(gray[(height - 1 - y) * width + x]);
            cornerPts.push(gray[(height - 1 - y) * width + (width - 1 - x)]);
        }
    }
    const bgMean = cornerPts.reduce((a, b) => a + b, 0) / Math.max(1, cornerPts.length);
    const fgDelta = Math.max(22, contrast * 40);

    let mass = 0;
    let massX = 0;
    let massY = 0;
    let borderMass = 0;
    let minX = width;
    let minY = height;
    let maxX = 0;
    let maxY = 0;
    const border = Math.max(2, Math.floor(Math.min(width, height) * 0.08));

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = y * width + x;
            const g = gray[i];
            const isFg = Math.abs(g - bgMean) > fgDelta;
            if (!isFg) {
                continue;
            }
            mass += 1;
            massX += x;
            massY += y;
            if (x < minX) {
                minX = x;
            }
            if (y < minY) {
                minY = y;
            }
            if (x > maxX) {
                maxX = x;
            }
            if (y > maxY) {
                maxY = y;
            }
            if (x < border || y < border || x >= width - border || y >= height - border) {
                borderMass += 1;
            }
        }
    }

    const fillRatio = mass / total;
    const centerOffsetX = mass > 0 ? massX / mass / width - 0.5 : 0;
    const centerOffsetY = mass > 0 ? massY / mass / height - 0.5 : 0;
    const borderMassRatio = mass > 0 ? borderMass / mass : 0;

    const bboxW = mass > 0 ? maxX - minX + 1 : 0;
    const bboxH = mass > 0 ? maxY - minY + 1 : 0;
    const contentAspect = bboxH > 0 ? bboxW / bboxH : 0;
    const bboxArea = bboxW * bboxH;
    const bboxDensity = bboxArea > 0 ? mass / bboxArea : 0;

    // Interior + perimeter edge energy.
    let edge = 0;
    let edgeCount = 0;
    for (let y = 1; y < height - 1; y += 2) {
        for (let x = 1; x < width - 1; x += 2) {
            const i = y * width + x;
            edge += Math.abs(gray[i + 1] - gray[i - 1]) + Math.abs(gray[i + width] - gray[i - width]);
            edgeCount += 1;
        }
    }
    const edgeEnergy = edgeCount > 0 ? edge / (edgeCount * 255) : 0;

    let periEdge = 0;
    let periCount = 0;
    if (bboxW > 8 && bboxH > 8) {
        const band = Math.max(2, Math.floor(Math.min(bboxW, bboxH) * 0.14));
        for (let y = Math.max(1, minY); y <= Math.min(height - 2, maxY); y += 1) {
            for (let x = Math.max(1, minX); x <= Math.min(width - 2, maxX); x += 1) {
                const onBand = x < minX + band || y < minY + band || x > maxX - band || y > maxY - band;
                if (!onBand) {
                    continue;
                }
                const i = y * width + x;
                periEdge += Math.abs(gray[i + 1] - gray[i - 1]) + Math.abs(gray[i + width] - gray[i - width]);
                periCount += 1;
            }
        }
    }
    const perimeterEdge = periCount > 0 ? periEdge / (periCount * 255) : 0;

    // Laplacian variance (sharpness).
    let lapSum = 0;
    let lapSumSq = 0;
    let lapN = 0;
    for (let y = 1; y < height - 1; y += 2) {
        for (let x = 1; x < width - 1; x += 2) {
            const i = y * width + x;
            const lap = gray[i - 1] + gray[i + 1] + gray[i - width] + gray[i + width] - 4 * gray[i];
            lapSum += lap;
            lapSumSq += lap * lap;
            lapN += 1;
        }
    }
    const lapMean = lapN > 0 ? lapSum / lapN : 0;
    const blurScore = lapN > 0 ? Math.max(0, lapSumSq / lapN - lapMean * lapMean) / 1000 : 0;

    // L3 — information-region bands relative to content bbox (not OCR).
    let panBandEnergy = 0;
    let expiryBandEnergy = 0;
    if (bboxW > 8 && bboxH > 8) {
        const panX0 = minX + bboxW * 0.08;
        const panX1 = minX + bboxW * 0.92;
        const panY0 = minY + bboxH * 0.52;
        const panY1 = minY + bboxH * 0.78;
        panBandEnergy = bandEdgeEnergy(gray, width, height, panX0, panY0, panX1, panY1);

        const expX0 = minX + bboxW * 0.52;
        const expX1 = minX + bboxW * 0.95;
        const expY0 = minY + bboxH * 0.68;
        const expY1 = minY + bboxH * 0.9;
        expiryBandEnergy = bandEdgeEnergy(gray, width, height, expX0, expY0, expX1, expY1);
    }

    // --- Layer gates ---
    const isCandidate =
        fillRatio >= config.minDetectFillRatio && contrast >= config.minContrastDetect && edgeEnergy >= config.minEdgeEnergyDetect;

    const aspectOk = contentAspect >= config.minContentAspect && contentAspect <= config.maxContentAspect;
    const sizeOk = fillRatio >= config.minFillRatio && fillRatio <= config.maxFillRatio;
    const densityOk = bboxDensity >= config.minBboxDensity;
    const positionOk = Math.abs(centerOffsetX) <= config.positionTolerance && Math.abs(centerOffsetY) <= config.positionTolerance;
    const fullCardOk = borderMassRatio < config.borderMassWarn;
    const geometryOk = aspectOk && sizeOk && densityOk && positionOk && fullCardOk;

    const patternOk = panBandEnergy >= config.minPanBandEnergy && expiryBandEnergy >= config.minExpiryBandEnergy;

    const sharpOk = blurScore >= config.minBlurScoreReady;
    const glareOk = glareRatio <= config.maxGlareRatio;
    const contrastOk = contrast >= config.minContrastReady;
    const edgeOk = edgeEnergy >= config.minEdgeEnergyReady && perimeterEdge >= config.minPerimeterEdgeReady;
    const lightOk = meanLuminance >= config.minMeanLuminance;
    const usabilityOk = sharpOk && glareOk && contrastOk && edgeOk && lightOk;

    const isValidCard = isCandidate && geometryOk && patternOk && usabilityOk;

    const tooSmall = fillRatio < config.minFillRatio;
    const tooLarge = fillRatio > config.maxFillRatio;

    let guidance: CardDetectGuidance = 'place_card';
    let uiState: CardUiState = 'searching';

    if (!isCandidate) {
        guidance = meanLuminance < config.minMeanLuminance ? 'improve_light' : 'place_card';
        uiState = 'searching';
    } else if (!aspectOk || !densityOk || !patternOk) {
        // Object present but not payment-card-like — stay red/searching for random rectangles.
        guidance = 'place_card';
        uiState = 'searching';
    } else if (!glareOk) {
        guidance = 'reduce_glare';
        uiState = 'adjusting';
    } else if (!lightOk) {
        guidance = 'improve_light';
        uiState = 'adjusting';
    } else if (tooSmall) {
        guidance = 'move_closer';
        uiState = 'adjusting';
    } else if (tooLarge) {
        guidance = 'move_back';
        uiState = 'adjusting';
    } else if (!fullCardOk) {
        guidance = 'show_entire';
        uiState = 'adjusting';
    } else if (!positionOk) {
        if (Math.abs(centerOffsetX) >= Math.abs(centerOffsetY)) {
            guidance = centerOffsetX < 0 ? 'move_right' : 'move_left';
        } else {
            guidance = centerOffsetY < 0 ? 'move_down' : 'move_up';
        }
        uiState = 'adjusting';
    } else if (!sharpOk || !contrastOk || !edgeOk) {
        guidance = 'adjust_card';
        uiState = 'adjusting';
    } else if (isValidCard) {
        guidance = 'hold_steady';
        uiState = 'ready';
    } else {
        guidance = 'adjust_card';
        uiState = 'candidate';
    }

    const fillIdeal = (config.minFillRatio + config.maxFillRatio) / 2;
    const fillCloseness = 1 - Math.min(1, Math.abs(fillRatio - fillIdeal) / 0.4);
    const centerCloseness = 1 - Math.min(1, (Math.abs(centerOffsetX) + Math.abs(centerOffsetY)) / 0.5);
    const qualityScore =
        blurScore * 2.2 +
        fillCloseness * 1.4 +
        centerCloseness * 1.1 +
        (1 - glareRatio) * 0.8 +
        contrast * 0.6 +
        perimeterEdge * 0.5 +
        panBandEnergy * 1.2 +
        expiryBandEnergy * 0.9;

    const trackKey: CardTrackKey | null =
        mass > 0
            ? {
                  cx: centerOffsetX,
                  cy: centerOffsetY,
                  w: bboxW / width,
                  h: bboxH / height,
                  aspect: contentAspect,
                  fill: fillRatio,
              }
            : null;

    return {
        uiState,
        isValidCard,
        isCandidate,
        readyCandidate: isValidCard,
        cardLike: isCandidate,
        contrast,
        edgeEnergy,
        perimeterEdge,
        fillRatio,
        blurScore,
        glareRatio,
        meanLuminance,
        centerOffsetX,
        centerOffsetY,
        contentAspect,
        panBandEnergy,
        expiryBandEnergy,
        qualityScore,
        guidance,
        trackKey,
        countdownSeconds: null,
    };
}

/** @deprecated Prefer validateCardCandidate. */
export function analyzeCardFrame(data: ImageData, config = CARD_SCAN_CONFIG): CardCandidateResult {
    return validateCardCandidate(data, config);
}

export function trackKeysConsistent(a: CardTrackKey, b: CardTrackKey, config: CardScanConfig = CARD_SCAN_CONFIG): boolean {
    const centerDelta = Math.hypot(a.cx - b.cx, a.cy - b.cy);
    const sizeDelta = Math.abs(a.w * a.h - b.w * b.h) / Math.max(0.01, a.w * a.h);
    const aspectDelta = Math.abs(a.aspect - b.aspect);
    return centerDelta <= config.maxCenterDelta && sizeDelta <= config.maxSizeDelta && aspectDelta <= config.maxAspectDelta;
}

/**
 * Sample an image Blob through the same ROI + validator used by the live camera.
 */
export async function validateCardImageBlob(blob: Blob, config: CardScanConfig = CARD_SCAN_CONFIG): Promise<CardCandidateResult> {
    const sampleW = config.sampleWidth;
    const sampleH = Math.round(sampleW / config.cardAspect);
    const canvas = document.createElement('canvas');
    canvas.width = sampleW;
    canvas.height = sampleH;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return validateCardCandidate(new ImageData(sampleW, sampleH), config);
    }

    const roi = config.roi;
    let width = 0;
    let height = 0;

    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(blob);
        try {
            width = bitmap.width;
            height = bitmap.height;
            const sx = Math.floor(width * roi.x);
            const sy = Math.floor(height * roi.y);
            const sw = Math.max(1, Math.floor(width * roi.w));
            const sh = Math.max(1, Math.floor(height * roi.h));
            ctx.drawImage(bitmap, sx, sy, sw, sh, 0, 0, sampleW, sampleH);
        } finally {
            bitmap.close();
        }
    } else {
        const url = URL.createObjectURL(blob);
        try {
            const img = await new Promise<HTMLImageElement>((resolve, reject) => {
                const el = new Image();
                el.onload = () => resolve(el);
                el.onerror = () => reject(new Error('image_load_failed'));
                el.src = url;
            });
            width = img.naturalWidth;
            height = img.naturalHeight;
            const sx = Math.floor(width * roi.x);
            const sy = Math.floor(height * roi.y);
            const sw = Math.max(1, Math.floor(width * roi.w));
            const sh = Math.max(1, Math.floor(height * roi.h));
            ctx.drawImage(img, sx, sy, sw, sh, 0, 0, sampleW, sampleH);
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    return validateCardCandidate(ctx.getImageData(0, 0, sampleW, sampleH), config);
}

export type CardDetectorController = {
    stop: () => void;
};

/**
 * Live loop: validate every sample → green only when valid → 5s continuous hold with track → best frame → one lock.
 */
export function startCardFrameDetector(
    video: HTMLVideoElement,
    onUpdate: (score: CardCandidateResult) => void,
    onLock: (blob: Blob) => void,
    onTimeout: () => void,
    options: CardDetectorOptions = {},
): CardDetectorController {
    const cfg = CARD_SCAN_CONFIG;
    const sampleIntervalMs = options.sampleIntervalMs ?? cfg.sampleIntervalMs;
    const readyStableMs = options.readyStableMs ?? cfg.readyStableMs;
    const timeoutMs = options.timeoutMs ?? cfg.timeoutMs;
    const roi = options.roi ?? cfg.roi;

    let stopped = false;
    let capturing = false;
    let raf = 0;
    let lastSample = 0;
    let holdSince: number | null = null;
    let lockedTrack: CardTrackKey | null = null;
    let movedFlashUntil = 0;
    const startedAt = performance.now();

    const analysisCanvas = document.createElement('canvas');
    const sampleW = cfg.sampleWidth;
    const sampleH = Math.round(sampleW / cfg.cardAspect);

    let bestScore = -1;
    let bestCanvas: HTMLCanvasElement | null = null;
    let bestValidation: CardCandidateResult | null = null;

    const captureFullFrame = (): HTMLCanvasElement | null => {
        const vw = video.videoWidth;
        const vh = video.videoHeight;
        if (vw <= 0 || vh <= 0) {
            return null;
        }
        const full = document.createElement('canvas');
        full.width = vw;
        full.height = vh;
        const fctx = full.getContext('2d');
        if (!fctx) {
            return null;
        }
        fctx.drawImage(video, 0, 0);
        return full;
    };

    const resetHold = () => {
        holdSince = null;
        lockedTrack = null;
        bestScore = -1;
        bestCanvas = null;
        bestValidation = null;
    };

    const emitBestBlob = (finalScore: CardCandidateResult) => {
        const source = bestCanvas ?? captureFullFrame();
        if (!source) {
            onTimeout();
            return;
        }
        source.toBlob(
            (blob) => {
                if (blob) {
                    onLock(blob);
                } else {
                    onTimeout();
                }
            },
            'image/jpeg',
            0.92,
        );
        void finalScore;
    };

    const tick = (now: number) => {
        if (stopped || capturing) {
            return;
        }

        if (now - startedAt > timeoutMs) {
            stopped = true;
            onTimeout();
            return;
        }

        if (now - lastSample >= sampleIntervalMs && video.readyState >= 2 && video.videoWidth > 0) {
            lastSample = now;
            const vw = video.videoWidth;
            const vh = video.videoHeight;
            const sx = Math.floor(vw * roi.x);
            const sy = Math.floor(vh * roi.y);
            const sw = Math.floor(vw * roi.w);
            const sh = Math.floor(vh * roi.h);

            analysisCanvas.width = sampleW;
            analysisCanvas.height = sampleH;
            const ctx = analysisCanvas.getContext('2d', { willReadFrequently: true });
            if (ctx) {
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sampleW, sampleH);
                const imageData = ctx.getImageData(0, 0, sampleW, sampleH);
                let score = validateCardCandidate(imageData);

                if (score.isValidCard && score.trackKey) {
                    if (holdSince === null) {
                        holdSince = now;
                        lockedTrack = score.trackKey;
                    } else if (lockedTrack && !trackKeysConsistent(lockedTrack, score.trackKey)) {
                        resetHold();
                        movedFlashUntil = now + 1200;
                        score = {
                            ...score,
                            isValidCard: false,
                            readyCandidate: false,
                            uiState: 'adjusting',
                            guidance: 'card_moved',
                            countdownSeconds: null,
                        };
                        onUpdate(score);
                        raf = requestAnimationFrame(tick);
                        return;
                    }

                    if (holdSince !== null) {
                        if (score.qualityScore >= bestScore) {
                            bestScore = score.qualityScore;
                            bestCanvas = captureFullFrame();
                            bestValidation = score;
                        }

                        const elapsed = now - holdSince;
                        const remainingMs = Math.max(0, readyStableMs - elapsed);
                        const countdownSeconds = Math.max(1, Math.ceil(remainingMs / 1000));

                        if (elapsed >= readyStableMs) {
                            // Final validation — require still valid.
                            if (!score.isValidCard) {
                                resetHold();
                                movedFlashUntil = now + 1200;
                                onUpdate({
                                    ...score,
                                    uiState: 'adjusting',
                                    guidance: 'card_moved',
                                    countdownSeconds: null,
                                });
                                raf = requestAnimationFrame(tick);
                                return;
                            }

                            capturing = true;
                            stopped = true;
                            onUpdate({ ...score, uiState: 'capturing', guidance: 'scanning', countdownSeconds: null });
                            emitBestBlob(bestValidation ?? score);
                            return;
                        }

                        score = {
                            ...score,
                            uiState: 'countdown',
                            guidance: 'hold_countdown',
                            countdownSeconds,
                        };
                    }
                } else {
                    if (holdSince !== null) {
                        resetHold();
                        movedFlashUntil = now + 1200;
                        score = {
                            ...score,
                            uiState: score.uiState === 'searching' ? 'searching' : 'adjusting',
                            guidance: score.isCandidate ? 'card_moved' : score.guidance,
                        };
                    } else if (now < movedFlashUntil && score.guidance === 'place_card') {
                        score = { ...score, uiState: 'adjusting', guidance: 'card_moved' };
                    }
                }

                onUpdate(score);
            }
        }

        raf = requestAnimationFrame(tick);
    };

    raf = requestAnimationFrame(tick);

    return {
        stop: () => {
            stopped = true;
            capturing = true;
            cancelAnimationFrame(raf);
        },
    };
}
