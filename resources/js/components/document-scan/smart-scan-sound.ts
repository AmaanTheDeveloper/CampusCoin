/**
 * Short professional confirmation tone for card lock.
 * Fail-soft: never throws; never blocks scanning.
 */

let sharedCtx: AudioContext | null = null;
let unlocked = false;

function getCtx(): AudioContext | null {
    try {
        const Ctx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!Ctx) {
            return null;
        }
        if (!sharedCtx) {
            sharedCtx = new Ctx();
        }
        return sharedCtx;
    } catch {
        return null;
    }
}

/** Call from a user gesture (Smart Scan open / mode click). */
export async function unlockSmartScanAudio(): Promise<void> {
    const ctx = getCtx();
    if (!ctx) {
        return;
    }
    try {
        if (ctx.state === 'suspended') {
            await ctx.resume();
        }
        unlocked = true;
    } catch {
        unlocked = false;
    }
}

/** Play one soft beep. Safe to call repeatedly; failures are silent. */
export function playCardScanConfirmSound(): void {
    try {
        const ctx = getCtx();
        if (!ctx || !unlocked) {
            // Best-effort resume without gesture — may still fail silently.
            void ctx?.resume().catch(() => undefined);
        }
        if (!ctx) {
            return;
        }

        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, now);
        osc.frequency.exponentialRampToValueAtTime(660, now + 0.09);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.08, now + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.11);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.12);
    } catch {
        // Sound is an enhancement only.
    }
}
