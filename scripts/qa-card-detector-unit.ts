/**
 * Unit fixtures for CardCandidateValidator (no browser).
 * Run: npx --yes tsx scripts/qa-card-detector-unit.ts
 */
import {
    analyzeCardFrame,
    guidanceMessage,
    trackKeysConsistent,
    validateCardCandidate,
} from '../resources/js/components/document-scan/card-frame-detector';
import { CARD_SCAN_CONFIG } from '../resources/js/components/document-scan/card-scan-config';

function makeImageData(w: number, h: number, fill: number | ((x: number, y: number) => number)): ImageData {
    const data = new Uint8ClampedArray(w * h * 4);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4;
            const g = typeof fill === 'function' ? fill(x, y) : fill;
            data[i] = g;
            data[i + 1] = g;
            data[i + 2] = g;
            data[i + 3] = 255;
        }
    }
    return { width: w, height: h, data, colorSpace: 'srgb' } as ImageData;
}

/** Payment-card-like: ISO aspect mass + horizontal PAN/expiry band texture. */
function cardLikeFill(w: number, h: number): (x: number, y: number) => number {
    const insetX = Math.floor(w * 0.14);
    const insetY = Math.floor(h * 0.16);
    return (x, y) => {
        const inside = x >= insetX && x < w - insetX && y >= insetY && y < h - insetY;
        if (!inside) {
            return 18;
        }
        const bx = x - insetX;
        const by = y - insetY;
        const bw = w - insetX * 2;
        const bh = h - insetY * 2;
        const relY = by / bh;
        const relX = bx / bw;
        // Perimeter contrast
        const nearEdge = bx < 3 || by < 3 || bx >= bw - 3 || by >= bh - 3;
        if (nearEdge) {
            return bx % 2 === 0 ? 200 : 35;
        }
        // PAN-like horizontal band
        if (relY > 0.52 && relY < 0.76) {
            return 40 + ((bx * 13) % 160) + (by % 3) * 20;
        }
        // Expiry-like band (lower-right)
        if (relY > 0.68 && relY < 0.9 && relX > 0.52) {
            return 50 + ((bx * 17 + by * 3) % 140);
        }
        // Body texture
        return 55 + ((x * 7 + y * 11) % 90);
    };
}

let failed = 0;
function assert(name: string, cond: boolean, detail = ''): void {
    if (!cond) {
        failed += 1;
        console.log(`[FAIL] ${name}${detail ? ` — ${detail}` : ''}`);
    } else {
        console.log(`[PASS] ${name}`);
    }
}

const W = CARD_SCAN_CONFIG.sampleWidth;
const H = Math.round(W / CARD_SCAN_CONFIG.cardAspect);

assert('config readyStableMs is 5000', CARD_SCAN_CONFIG.readyStableMs === 5000);
assert('config minFill stricter than detect', CARD_SCAN_CONFIG.minFillRatio > CARD_SCAN_CONFIG.minDetectFillRatio);
assert('config blur still tolerant', CARD_SCAN_CONFIG.minBlurScoreReady < 0.1);

// Empty / uniform dark → searching, not valid
const empty = validateCardCandidate(makeImageData(W, H, 12));
assert('empty not valid', empty.isValidCard === false);
assert('empty searching', empty.uiState === 'searching');
assert('empty guidance place or light', empty.guidance === 'place_card' || empty.guidance === 'improve_light');

// Tiny corner blob → not valid
const tiny = validateCardCandidate(
    makeImageData(W, H, (x, y) => {
        if (x > 8 && x < 28 && y > 8 && y < 22) {
            return 40 + ((x + y) % 80);
        }
        return 20;
    }),
);
assert('tiny not valid', tiny.isValidCard === false);

// Severe glare → not valid
const glare = validateCardCandidate(makeImageData(W, H, () => 245));
assert('glare not valid', glare.isValidCard === false);

// Flat mid desk → not valid (no mid-tone fill boost)
const desk = validateCardCandidate(makeImageData(W, H, 110));
assert('desk mid not valid', desk.isValidCard === false);
assert('desk not countdown-eligible', desk.readyCandidate === false);

// Paper-like weak bands (soft rectangle, little structured texture) → NOT valid
const paper = validateCardCandidate(
    makeImageData(W, H, (x, y) => {
        const inset = 8;
        if (x >= inset && x < W - inset && y >= inset && y < H - inset) {
            return 140 + ((x + y) % 6);
        }
        return 30;
    }),
);
assert('paper not valid card', paper.isValidCard === false);

// Phone-like tall mass (portrait aspect) → NOT valid
const phone = validateCardCandidate(
    makeImageData(W, H, (x, y) => {
        const left = Math.floor(W * 0.35);
        const right = Math.floor(W * 0.65);
        const top = Math.floor(H * 0.05);
        const bottom = Math.floor(H * 0.95);
        if (x >= left && x < right && y >= top && y < bottom) {
            return 40 + ((x * 9 + y * 5) % 120);
        }
        return 20;
    }),
);
assert('phone tall mass not valid', phone.isValidCard === false, `aspect=${phone.contentAspect.toFixed(2)}`);

// Left-small bias → not valid
const leftBias = validateCardCandidate(
    makeImageData(W, H, (x, y) => {
        if (x > 2 && x < W * 0.28 && y > 8 && y < H - 8) {
            return 50 + ((x * 9 + y * 5) % 120);
        }
        return 22;
    }),
);
assert('left-small bias not valid', leftBias.isValidCard === false);

// Actual card-like textured frame → can become valid
const card = validateCardCandidate(makeImageData(W, H, cardLikeFill(W, H)));
assert(
    'card-like isValidCard',
    card.isValidCard === true,
    `state=${card.uiState} fill=${card.fillRatio.toFixed(2)} aspect=${card.contentAspect.toFixed(2)} pan=${card.panBandEnergy.toFixed(3)} exp=${card.expiryBandEnergy.toFixed(3)} blur=${card.blurScore.toFixed(3)}`,
);
if (card.isValidCard) {
    assert('card-like ready state', card.uiState === 'ready');
    assert('card-like hold_steady guidance', card.guidance === 'hold_steady');
    assert('card-like has trackKey', card.trackKey != null);
}

// analyzeCardFrame alias
const alias = analyzeCardFrame(makeImageData(W, H, 12));
assert('analyzeCardFrame alias works', alias.isValidCard === false);

assert('guidance countdown copy', guidanceMessage('hold_countdown', 3).includes('3'));
assert('guidance card detected', guidanceMessage('hold_steady').includes('Card detected'));
assert('guidance scanning', guidanceMessage('scanning') === 'Scanning card…');

// Track consistency
if (card.trackKey) {
    const stable = { ...card.trackKey, cx: card.trackKey.cx + 0.01 };
    const moved = { ...card.trackKey, cx: card.trackKey.cx + 0.25 };
    assert('track small move ok', trackKeysConsistent(card.trackKey, stable));
    assert('track large move resets', trackKeysConsistent(card.trackKey, moved) === false);
}

console.log(failed === 0 ? '\nCard detector unit: OK' : `\nCard detector unit: ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
