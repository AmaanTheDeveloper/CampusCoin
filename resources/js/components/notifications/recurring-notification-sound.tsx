import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const STORAGE_KEY = 'cc_recurring_notification_sounds_played';

function playedIds(): Set<string> {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return new Set();
        }
        const parsed = JSON.parse(raw);
        return new Set(Array.isArray(parsed) ? parsed.map(String) : []);
    } catch {
        return new Set();
    }
}

function markPlayed(id: string): void {
    const next = playedIds();
    next.add(id);
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...next]));
}

/**
 * Plays a short soft chime once per new recurring approval notification id.
 * Gracefully no-ops when autoplay is blocked or audio is unavailable.
 */
export function RecurringNotificationSound() {
    const { recurring_notification_sound_id: soundId } = usePage<SharedData>().props;
    const lastAttempted = useRef<string | null>(null);

    useEffect(() => {
        if (!soundId || lastAttempted.current === soundId) {
            return;
        }

        if (playedIds().has(soundId)) {
            lastAttempted.current = soundId;
            return;
        }

        lastAttempted.current = soundId;

        const audio = new Audio('/sounds/recurring-approval.wav');
        audio.volume = 0.35;
        const play = audio.play();
        if (play && typeof play.then === 'function') {
            play.then(() => markPlayed(soundId)).catch(() => {
                // Autoplay policy — wait for a single user gesture, then try once.
                const unlock = () => {
                    audio
                        .play()
                        .then(() => markPlayed(soundId))
                        .catch(() => undefined);
                    window.removeEventListener('pointerdown', unlock);
                    window.removeEventListener('keydown', unlock);
                };
                window.addEventListener('pointerdown', unlock, { once: true });
                window.addEventListener('keydown', unlock, { once: true });
            });
        } else {
            markPlayed(soundId);
        }
    }, [soundId]);

    return null;
}
