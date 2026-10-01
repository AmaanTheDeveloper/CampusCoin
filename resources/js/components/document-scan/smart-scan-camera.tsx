import {
    guidanceMessage,
    startCardFrameDetector,
    type CardDetectGuidance,
    type CardDetectorController,
    type CardUiState,
} from '@/components/document-scan/card-frame-detector';
import { playCardScanConfirmSound } from '@/components/document-scan/smart-scan-sound';
import { Button } from '@/components/ui/button';
import { usePrefersReducedMotion } from '@/hooks/use-dashboard-motion';
import { cn } from '@/lib/utils';
import { Camera, ImageUp, RefreshCw, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export type SmartScanCameraMode = 'card' | 'document';

type Props = {
    mode: SmartScanCameraMode;
    onCapture: (blob: Blob) => void;
    onCancel: () => void;
    onUnavailable: () => void;
    onUploadFallback?: () => void;
};

function frameColor(uiState: CardUiState): string {
    switch (uiState) {
        case 'ready':
        case 'countdown':
        case 'capturing':
            return 'border-emerald-400 shadow-[0_0_0_1px_rgba(52,211,153,0.35)]';
        case 'candidate':
        case 'adjusting':
            return 'border-orange-400 shadow-[0_0_0_1px_rgba(251,146,60,0.3)]';
        case 'failed':
            return 'border-red-400/80';
        default:
            return 'border-red-500/70';
    }
}

function cornerColor(uiState: CardUiState): string {
    switch (uiState) {
        case 'ready':
        case 'countdown':
        case 'capturing':
            return 'border-emerald-400';
        case 'candidate':
        case 'adjusting':
            return 'border-orange-400';
        default:
            return 'border-red-500';
    }
}

function statusTone(uiState: CardUiState): string {
    if (uiState === 'ready' || uiState === 'countdown' || uiState === 'capturing') {
        return 'text-emerald-300';
    }
    if (uiState === 'candidate' || uiState === 'adjusting') {
        return 'text-orange-300';
    }
    if (uiState === 'failed') {
        return 'text-red-300';
    }
    return 'text-white';
}

export function SmartScanCamera({ mode, onCapture, onCancel, onUnavailable, onUploadFallback }: Props) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const detectorRef = useRef<CardDetectorController | null>(null);
    const captureDelayRef = useRef<number | null>(null);
    const reducedMotion = usePrefersReducedMotion();

    const [error, setError] = useState<string | null>(null);
    const [ready, setReady] = useState(false);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [previewBlob, setPreviewBlob] = useState<Blob | null>(null);
    const [guidance, setGuidance] = useState<CardDetectGuidance>('place_card');
    const [uiState, setUiState] = useState<CardUiState>('searching');
    const [countdownSeconds, setCountdownSeconds] = useState<number | null>(null);
    const [detectFailed, setDetectFailed] = useState(false);
    const lockedRef = useRef(false);

    const stopStream = useCallback(() => {
        detectorRef.current?.stop();
        detectorRef.current = null;
        streamRef.current?.getTracks().forEach((t) => t.stop());
        streamRef.current = null;
        if (captureDelayRef.current != null) {
            window.clearTimeout(captureDelayRef.current);
            captureDelayRef.current = null;
        }
    }, []);

    const startDetector = useCallback(() => {
        const video = videoRef.current;
        if (!video || mode !== 'card' || lockedRef.current) {
            return;
        }

        detectorRef.current?.stop();
        detectorRef.current = startCardFrameDetector(
            video,
            (score) => {
                if (!lockedRef.current) {
                    setGuidance(score.guidance);
                    setUiState(score.uiState);
                    setCountdownSeconds(score.countdownSeconds);
                }
            },
            (blob) => {
                if (lockedRef.current) {
                    return;
                }
                lockedRef.current = true;
                setGuidance('scanning');
                setUiState('capturing');
                setCountdownSeconds(null);
                // Sound only after 5s hold + final validation + capture trigger.
                playCardScanConfirmSound();
                stopStream();
                captureDelayRef.current = window.setTimeout(
                    () => {
                        onCapture(blob);
                    },
                    reducedMotion ? 120 : 520,
                );
            },
            () => {
                if (!lockedRef.current) {
                    setDetectFailed(true);
                    setGuidance('failed');
                    setUiState('failed');
                    setCountdownSeconds(null);
                    stopStream();
                }
            },
        );
    }, [mode, onCapture, reducedMotion, stopStream]);

    useEffect(() => {
        let cancelled = false;
        lockedRef.current = false;
        setDetectFailed(false);
        setGuidance('place_card');
        setUiState('searching');
        setCountdownSeconds(null);

        async function start() {
            if (!navigator.mediaDevices?.getUserMedia) {
                setError('Camera is not available on this device.');
                if (mode === 'card') {
                    setDetectFailed(true);
                    setGuidance('failed');
                    setUiState('failed');
                }
                onUnavailable();
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                });

                if (cancelled) {
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }

                streamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                    setReady(true);
                    if (mode === 'card') {
                        startDetector();
                    }
                }
            } catch {
                setError('Camera permission denied or unavailable. You can upload a photo instead.');
                if (mode === 'card') {
                    setDetectFailed(true);
                    setGuidance('failed');
                    setUiState('failed');
                }
                onUnavailable();
            }
        }

        void start();

        return () => {
            cancelled = true;
            stopStream();
        };
    }, [mode, onUnavailable, startDetector, stopStream]);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const capture = () => {
        const video = videoRef.current;
        if (!video || video.videoWidth === 0) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        ctx.drawImage(video, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    return;
                }
                stopStream();
                const url = URL.createObjectURL(blob);
                setPreviewUrl(url);
                setPreviewBlob(blob);
            },
            'image/jpeg',
            0.92,
        );
    };

    const retake = async () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        setPreviewUrl(null);
        setPreviewBlob(null);
        setReady(false);
        setError(null);
        lockedRef.current = false;
        setDetectFailed(false);
        setGuidance('place_card');
        setUiState('searching');
        setCountdownSeconds(null);

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
                setReady(true);
                if (mode === 'card') {
                    startDetector();
                }
            }
        } catch {
            setError('Could not restart the camera.');
            onUnavailable();
        }
    };

    const usePhoto = () => {
        if (previewBlob) {
            onCapture(previewBlob);
        }
    };

    const retryDetect = () => {
        setDetectFailed(false);
        lockedRef.current = false;
        setGuidance('place_card');
        setUiState('searching');
        setCountdownSeconds(null);
        void retake();
    };

    const statusText =
        mode === 'card'
            ? detectFailed
                ? guidanceMessage('failed')
                : guidanceMessage(guidance, countdownSeconds)
            : previewUrl
              ? 'Review your photo'
              : 'Position the entire document inside the frame.';

    const showScanLine = !reducedMotion && mode === 'card' && (uiState === 'countdown' || uiState === 'capturing');
    const holdProgress =
        uiState === 'countdown' && countdownSeconds != null ? Math.max(0, Math.min(1, (5 - countdownSeconds) / 5 + 0.15)) : uiState === 'capturing' ? 1 : 0;

    return (
        <div className="flex flex-col gap-3">
            <p className="cc-text-secondary text-xs" role="status" aria-live="polite">
                Camera access is used only to scan your card or document.
            </p>

            <div className="relative overflow-hidden rounded-xl border border-white/10 bg-black">
                {previewUrl ? (
                    <img src={previewUrl} alt="Captured document" className="aspect-[4/3] w-full object-contain" />
                ) : (
                    <>
                        <video ref={videoRef} playsInline muted className="aspect-[4/3] w-full object-cover" />
                        {mode === 'card' ? (
                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden>
                                <div
                                    className={cn(
                                        'relative aspect-[1.586/1] w-[76%] rounded-md border-2 transition-colors duration-300',
                                        frameColor(uiState),
                                    )}
                                    data-testid="smart-scan-card-frame"
                                    data-ui-state={uiState}
                                    data-countdown={countdownSeconds ?? ''}
                                >
                                    <span className={cn('absolute -top-0.5 -left-0.5 size-4 border-t-2 border-l-2', cornerColor(uiState))} />
                                    <span className={cn('absolute -top-0.5 -right-0.5 size-4 border-t-2 border-r-2', cornerColor(uiState))} />
                                    <span className={cn('absolute -bottom-0.5 -left-0.5 size-4 border-b-2 border-l-2', cornerColor(uiState))} />
                                    <span className={cn('absolute -right-0.5 -bottom-0.5 size-4 border-r-2 border-b-2', cornerColor(uiState))} />
                                    {uiState === 'countdown' && countdownSeconds != null ? (
                                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2">
                                            <span className="text-3xl font-semibold tabular-nums text-emerald-300 drop-shadow">{countdownSeconds}</span>
                                            <span
                                                className="h-1 w-[70%] overflow-hidden rounded-full bg-emerald-950/60"
                                                data-testid="smart-scan-hold-progress"
                                            >
                                                <span
                                                    className="block h-full rounded-full bg-emerald-400 transition-[width] duration-200"
                                                    style={{ width: `${Math.round(holdProgress * 100)}%` }}
                                                />
                                            </span>
                                        </div>
                                    ) : null}
                                    {showScanLine ? (
                                        <span
                                            className={cn(
                                                'cc-smart-scan-line absolute inset-x-2 top-0 h-0.5 bg-gradient-to-r from-transparent to-transparent',
                                                uiState === 'capturing' ? 'via-emerald-300' : 'via-emerald-400/80',
                                            )}
                                        />
                                    ) : null}
                                </div>
                            </div>
                        ) : (
                            <div className="pointer-events-none absolute inset-6 rounded-lg border-2 border-dashed border-[#C5A028]/40" aria-hidden />
                        )}
                    </>
                )}
            </div>

            <p
                className={cn('text-center text-sm font-medium', statusTone(uiState))}
                role="status"
                aria-live="polite"
                data-testid="smart-scan-card-status"
            >
                {statusText}
            </p>
            {detectFailed ? (
                <p className="cc-text-secondary text-center text-xs">Make sure the card is fully visible, well lit, and inside the frame.</p>
            ) : null}

            {error ? <p className="text-sm text-red-300">{error}</p> : null}

            <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="ghost" className="cc-dash-btn-ghost" onClick={onCancel}>
                    <X className="mr-2 size-4" aria-hidden />
                    {mode === 'card' ? 'Back' : 'Cancel'}
                </Button>

                {mode === 'card' && detectFailed ? (
                    <>
                        {onUploadFallback ? (
                            <Button type="button" variant="secondary" className="cc-dash-btn-secondary" onClick={onUploadFallback}>
                                <ImageUp className="mr-2 size-4" aria-hidden />
                                Upload card image
                            </Button>
                        ) : null}
                        <Button type="button" className="cc-dash-btn-primary" onClick={retryDetect}>
                            Try again
                        </Button>
                    </>
                ) : null}

                {mode === 'card' && !detectFailed && onUploadFallback ? (
                    <Button type="button" variant="secondary" className="cc-dash-btn-secondary" onClick={onUploadFallback}>
                        <ImageUp className="mr-2 size-4" aria-hidden />
                        Upload card image
                    </Button>
                ) : null}

                {mode === 'document' && previewUrl ? (
                    <>
                        <Button type="button" variant="secondary" className="cc-dash-btn-secondary" onClick={() => void retake()}>
                            <RefreshCw className="mr-2 size-4" aria-hidden />
                            Retake
                        </Button>
                        <Button type="button" className="cc-dash-btn-primary" onClick={usePhoto}>
                            Use photo
                        </Button>
                    </>
                ) : null}

                {mode === 'document' && !previewUrl ? (
                    <Button type="button" className="cc-dash-btn-primary" onClick={capture} disabled={!ready}>
                        <Camera className={cn('mr-2 size-4', !ready && 'opacity-50')} aria-hidden />
                        Capture
                    </Button>
                ) : null}
            </div>

            <style>{`
                @keyframes cc-smart-scan-line {
                    0% { transform: translateY(0); opacity: 0.35; }
                    50% { opacity: 1; }
                    100% { transform: translateY(220%); opacity: 0.35; }
                }
                .cc-smart-scan-line {
                    animation: cc-smart-scan-line 1.1s ease-in-out infinite;
                }
            `}</style>
        </div>
    );
}
