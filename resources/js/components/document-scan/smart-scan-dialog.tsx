import { guidanceMessage, validateCardImageBlob } from '@/components/document-scan/card-frame-detector';
import { SmartScanCamera } from '@/components/document-scan/smart-scan-camera';
import { SmartScanReview, type ReviewFormState } from '@/components/document-scan/smart-scan-review';
import { unlockSmartScanAudio } from '@/components/document-scan/smart-scan-sound';
import {
    csrfToken,
    firstError,
    resolveProcessErrorMessage,
    type ConfirmScanResponse,
    type ProcessScanResponse,
    type ScanCategoryOption,
    type ScanExtraction,
    type ScanMode,
} from '@/components/document-scan/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { Camera, CheckCircle2, CreditCard, FileText, ImageUp, Loader2, ScanLine } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';

type Step = 'mode' | 'document_choose' | 'card_camera' | 'document_camera' | 'processing' | 'review' | 'success' | 'error';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function todayLocal(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export function SmartScanDialog({ open, onOpenChange }: Props) {
    const fileRef = useRef<HTMLInputElement | null>(null);
    const processingLockRef = useRef(false);
    const [step, setStep] = useState<Step>('mode');
    const [scanMode, setScanMode] = useState<ScanMode | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [scanToken, setScanToken] = useState<string | null>(null);
    const [extraction, setExtraction] = useState<ScanExtraction | null>(null);
    const [categories, setCategories] = useState<ScanCategoryOption[]>([]);
    const [today, setToday] = useState(todayLocal());
    const [form, setForm] = useState<ReviewFormState>({
        type: 'expense',
        category_id: '',
        amount: '',
        description: '',
        transaction_date: todayLocal(),
    });
    const [submitting, setSubmitting] = useState(false);
    const [duplicateWarning, setDuplicateWarning] = useState<string | null>(null);
    const [budgetWarning, setBudgetWarning] = useState<string | null>(null);
    const [acknowledgeDuplicate, setAcknowledgeDuplicate] = useState(false);
    const [confirmBudgetOverrun, setConfirmBudgetOverrun] = useState(false);
    const [successMessage, setSuccessMessage] = useState('Transaction added successfully');

    const reset = useCallback(() => {
        processingLockRef.current = false;
        setStep('mode');
        setScanMode(null);
        setError(null);
        setScanToken(null);
        setExtraction(null);
        setCategories([]);
        setToday(todayLocal());
        setForm({
            type: 'expense',
            category_id: '',
            amount: '',
            description: '',
            transaction_date: todayLocal(),
        });
        setSubmitting(false);
        setDuplicateWarning(null);
        setBudgetWarning(null);
        setAcknowledgeDuplicate(false);
        setConfirmBudgetOverrun(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }, []);

    const handleOpenChange = (next: boolean) => {
        if (!next) {
            reset();
        } else {
            void unlockSmartScanAudio();
        }
        onOpenChange(next);
    };

    const selectCardMode = () => {
        void unlockSmartScanAudio();
        setScanMode('card');
        setError(null);
        setStep('card_camera');
    };

    const selectDocumentMode = () => {
        void unlockSmartScanAudio();
        setScanMode('document');
        setError(null);
        setStep('document_choose');
    };

    const processBlob = async (blob: Blob, filename: string, mode: ScanMode) => {
        if (processingLockRef.current) {
            return;
        }

        if (!blob || blob.size <= 0) {
            setError("Couldn't capture the image. Please try again.");
            setStep(mode === 'card' ? 'card_camera' : 'error');
            return;
        }

        processingLockRef.current = true;
        setStep('processing');
        setError(null);

        const body = new FormData();
        body.append('image', blob, filename);
        body.append('mode', mode);

        try {
            const res = await fetch(route('document-scan.process'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body,
            });

            let data: ProcessScanResponse;
            try {
                data = (await res.json()) as ProcessScanResponse;
            } catch {
                setError(
                    mode === 'card'
                        ? "We couldn't read the card details. Please try again with the card closer and reduce glare."
                        : 'Document scanning is temporarily unavailable. Please try again.',
                );
                setStep(mode === 'card' ? 'card_camera' : 'error');
                return;
            }

            if (!res.ok || !data.ok || !data.extraction || !data.scan_token) {
                const message = resolveProcessErrorMessage(data, mode);
                setError(message);
                // Card failures: return to camera with specific guidance (not a dead-end clear-photo screen).
                if (mode === 'card') {
                    setStep('card_camera');
                } else {
                    setStep('error');
                }
                return;
            }

            setScanToken(data.scan_token);
            setExtraction(data.extraction);
            setCategories(data.categories ?? []);
            setToday(data.today ?? todayLocal());

            const type = data.extraction.suggested_type === 'income' ? 'income' : 'expense';
            const categoryId = data.extraction.suggested_category_id != null ? String(data.extraction.suggested_category_id) : '';

            setForm({
                type,
                category_id: categoryId,
                amount: data.extraction.amount ?? '',
                description: data.extraction.description ?? data.extraction.merchant ?? '',
                transaction_date: data.extraction.date ?? data.today ?? todayLocal(),
            });
            setStep('review');
        } catch {
            setError('Document scanning is temporarily unavailable. Check your connection and try again.');
            setStep(mode === 'card' ? 'card_camera' : 'error');
        } finally {
            processingLockRef.current = false;
        }
    };

    const onFileSelected = async (file: File | null) => {
        if (!file || !scanMode) {
            return;
        }

        // Card upload shares the same client CardCandidateValidator as the live camera.
        if (scanMode === 'card') {
            try {
                const candidate = await validateCardImageBlob(file);
                if (!candidate.isValidCard) {
                    setError(
                        candidate.guidance === 'place_card'
                            ? 'Invalid card, try again'
                            : guidanceMessage(candidate.guidance) || 'Invalid card, try again',
                    );
                    setStep('card_camera');
                    return;
                }
            } catch {
                setError('Invalid card, try again');
                setStep('card_camera');
                return;
            }
        }

        await processBlob(file, file.name || (scanMode === 'card' ? 'card.jpg' : 'receipt.jpg'), scanMode);
    };

    const confirm = async () => {
        if (!scanToken || submitting) {
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const res = await fetch(route('document-scan.confirm'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    scan_token: scanToken,
                    type: form.type,
                    category_id: Number(form.category_id),
                    amount: form.amount,
                    description: form.description || null,
                    transaction_date: form.transaction_date,
                    acknowledge_duplicate: acknowledgeDuplicate,
                    confirm_budget_overrun: confirmBudgetOverrun,
                }),
            });

            const data = (await res.json()) as ConfirmScanResponse;

            if (res.status === 409 && data.possible_duplicate) {
                setDuplicateWarning(data.message ?? 'A similar expense may already exist. Confirm to add anyway.');
                setAcknowledgeDuplicate(true);
                setSubmitting(false);
                return;
            }

            if (res.status === 409 && data.budget_overrun) {
                setBudgetWarning(data.message ?? 'Budget limit exceeded. Confirm to continue.');
                setConfirmBudgetOverrun(true);
                setSubmitting(false);
                return;
            }

            if (!res.ok || !data.ok) {
                setError(firstError(data.errors) ?? data.message ?? 'Could not add this transaction.');
                setSubmitting(false);
                return;
            }

            setSuccessMessage(data.message ?? 'Transaction added successfully');
            setStep('success');
            setSubmitting(false);

            window.setTimeout(() => {
                handleOpenChange(false);
                router.reload();
            }, 900);
        } catch {
            setError('Could not add this transaction. Please try again.');
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="cc-card max-h-[90vh] overflow-y-auto border-white/10 bg-[#121212] text-white sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-white">
                        <ScanLine className="size-5 text-[#C5A028]" aria-hidden />
                        Smart Scan
                    </DialogTitle>
                    <DialogDescription className="cc-text-secondary">
                        Scan a card automatically, or capture a bill or receipt to add a transaction.
                    </DialogDescription>
                </DialogHeader>

                {step === 'mode' ? (
                    <div className="flex flex-col gap-3">
                        <Button
                            type="button"
                            data-testid="smart-scan-mode-card"
                            className="cc-dash-btn-primary h-auto flex-col items-start gap-1 px-4 py-5 text-left"
                            onClick={selectCardMode}
                        >
                            <span className="flex items-center gap-2 text-base font-semibold">
                                <CreditCard className="size-5" aria-hidden />
                                Scan Card
                            </span>
                            <span className="text-xs font-normal text-white/80">
                                Automatically detect your debit or credit card using your camera.
                            </span>
                        </Button>
                        <Button
                            type="button"
                            data-testid="smart-scan-mode-document"
                            variant="secondary"
                            className="cc-dash-btn-secondary h-auto flex-col items-start gap-1 px-4 py-5 text-left"
                            onClick={selectDocumentMode}
                        >
                            <span className="flex items-center gap-2 text-base font-semibold">
                                <FileText className="size-5" aria-hidden />
                                Scan Bill or Receipt
                            </span>
                            <span className="text-xs font-normal opacity-80">Capture or upload a bill, receipt, or financial document.</span>
                        </Button>
                        <p className="cc-text-secondary text-xs">Camera access is used only to scan your card or document.</p>
                        {error ? <p className="text-sm text-amber-200">{error}</p> : null}
                    </div>
                ) : null}

                {step === 'document_choose' ? (
                    <div className="flex flex-col gap-3">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Button
                                type="button"
                                className="cc-dash-btn-primary h-auto flex-col gap-2 py-6"
                                onClick={() => setStep('document_camera')}
                            >
                                <Camera className="size-6" aria-hidden />
                                Use camera
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                className="cc-dash-btn-secondary h-auto flex-col gap-2 py-6"
                                onClick={() => fileRef.current?.click()}
                            >
                                <ImageUp className="size-6" aria-hidden />
                                Upload image
                            </Button>
                        </div>
                        <Button type="button" variant="ghost" className="cc-dash-btn-ghost self-start" onClick={() => setStep('mode')}>
                            Back
                        </Button>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            className="hidden"
                            onChange={(e) => void onFileSelected(e.target.files?.[0] ?? null)}
                        />
                    </div>
                ) : null}

                {step === 'card_camera' ? (
                    <>
                        {error ? (
                            <p
                                className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100"
                                role="alert"
                                data-testid="smart-scan-card-error"
                            >
                                {error}
                            </p>
                        ) : null}
                        <SmartScanCamera
                            mode="card"
                            onCapture={(blob) => void processBlob(blob, 'card-capture.jpg', 'card')}
                            onCancel={() => {
                                setError(null);
                                setStep('mode');
                            }}
                            onUnavailable={() => {
                                setError('Camera unavailable — upload a card image instead.');
                            }}
                            onUploadFallback={() => fileRef.current?.click()}
                        />
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            className="hidden"
                            data-testid="smart-scan-card-upload"
                            onChange={(e) => void onFileSelected(e.target.files?.[0] ?? null)}
                        />
                    </>
                ) : null}

                {step === 'document_camera' ? (
                    <SmartScanCamera
                        mode="document"
                        onCapture={(blob) => void processBlob(blob, 'capture.jpg', 'document')}
                        onCancel={() => setStep('document_choose')}
                        onUnavailable={() => {
                            setStep('document_choose');
                            setError('Camera unavailable — upload a photo instead.');
                        }}
                    />
                ) : null}

                {step === 'processing' ? (
                    <div className="flex flex-col items-center gap-3 py-10 text-center">
                        <Loader2 className="size-8 animate-spin text-[#C5A028]" aria-hidden />
                        <p className="text-sm text-white">{scanMode === 'card' ? 'Reading your card…' : 'Reading your document…'}</p>
                        <p className="cc-text-secondary text-xs">
                            {scanMode === 'card' ? 'No transaction is created from a card scan.' : 'No transaction is created until you confirm.'}
                        </p>
                    </div>
                ) : null}

                {step === 'review' && extraction ? (
                    <SmartScanReview
                        extraction={extraction}
                        categories={categories}
                        today={today}
                        form={form}
                        onChange={setForm}
                        onSubmit={() => void confirm()}
                        onCancel={() => handleOpenChange(false)}
                        submitting={submitting}
                        error={error}
                        duplicateWarning={duplicateWarning}
                        budgetWarning={budgetWarning}
                    />
                ) : null}

                {step === 'success' ? (
                    <div className="flex flex-col items-center gap-3 py-8 text-center">
                        <CheckCircle2 className="size-10 text-emerald-400" aria-hidden />
                        <p className="font-medium text-white">{successMessage}</p>
                    </div>
                ) : null}

                {step === 'error' ? (
                    <div className="flex flex-col gap-4 py-2">
                        <p className="text-sm text-red-300">{error}</p>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="ghost" className="cc-dash-btn-ghost" onClick={() => handleOpenChange(false)}>
                                Close
                            </Button>
                            <Button type="button" className="cc-dash-btn-primary" onClick={reset}>
                                Try again
                            </Button>
                        </div>
                    </div>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
