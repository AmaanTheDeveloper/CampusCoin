export type ScanDocumentType = 'receipt' | 'bill' | 'card' | 'unknown';

export type ScanMode = 'card' | 'document';

export type ScanErrorCode =
    | 'CAMERA_CAPTURE_FAILED'
    | 'INVALID_FILE'
    | 'UPLOAD_FAILED'
    | 'IMAGE_PROCESSING_FAILED'
    | 'OCR_PROVIDER_UNAVAILABLE'
    | 'OCR_TIMEOUT'
    | 'OCR_RATE_LIMITED'
    | 'OCR_INVALID_RESPONSE'
    | 'DOCUMENT_NOT_RECOGNIZED'
    | 'INSUFFICIENT_DATA'
    | 'UNKNOWN_PROCESSING_ERROR';

export type ScanExtraction = {
    document_type: ScanDocumentType;
    merchant: string | null;
    amount: string | null;
    currency: string | null;
    date: string | null;
    description: string | null;
    suggested_category_name: string | null;
    suggested_category_id: number | null;
    suggested_type: 'income' | 'expense' | null;
    confidence: number;
    field_confidence: Record<string, number>;
    needs_review: string[];
    card_last4: string | null;
    cardholder_name: string | null;
    card_brand: string | null;
    card_kind: 'debit' | 'credit' | 'unknown' | null;
    card_expiry_masked: string | null;
    has_card_number?: boolean | null;
    has_expiry?: boolean | null;
    has_cvv_field?: boolean | null;
};

export type ScanCategoryOption = {
    id: number;
    name: string;
    type: 'income' | 'expense';
    is_default: boolean;
};

export type ProcessScanResponse = {
    ok: boolean;
    scan_token?: string;
    extraction?: ScanExtraction;
    categories?: ScanCategoryOption[];
    today?: string;
    available_balance?: string;
    message?: string;
    error_code?: ScanErrorCode | string;
    errors?: Record<string, string[]>;
};

export type ConfirmScanResponse = {
    ok: boolean;
    message?: string;
    transaction_id?: number;
    possible_duplicate?: boolean;
    budget_overrun?: boolean;
    budget_impact?: Record<string, unknown> | null;
    matches?: Array<{
        id: number;
        description: string | null;
        amount: string;
        transaction_date: string;
    }>;
    spending_alert?: Record<string, unknown> | null;
    errors?: Record<string, string[]>;
};

export function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function firstError(errors?: Record<string, string[]>): string | null {
    if (!errors) {
        return null;
    }
    for (const messages of Object.values(errors)) {
        if (messages?.[0]) {
            return messages[0];
        }
    }
    return null;
}

export function messageForScanError(code: string | undefined, mode: ScanMode): string | null {
    switch (code) {
        case 'CAMERA_CAPTURE_FAILED':
            return "Couldn't capture the image. Please try again.";
        case 'INVALID_FILE':
            return 'This image could not be read. Please try another image.';
        case 'OCR_RATE_LIMITED':
        case 'OCR_PROVIDER_UNAVAILABLE':
            return 'Document scanning is temporarily unavailable. Please try again in a few minutes.';
        case 'OCR_TIMEOUT':
            return 'Document scanning timed out. Please try again.';
        case 'OCR_INVALID_RESPONSE':
        case 'DOCUMENT_NOT_RECOGNIZED':
        case 'INSUFFICIENT_DATA':
            return mode === 'card'
                ? "We couldn't read the card details. Please try again with the card closer and reduce glare."
                : "We couldn't read this document. Please try another image or add the transaction manually.";
        case 'UNKNOWN_PROCESSING_ERROR':
            return 'Document scanning is temporarily unavailable. Please try again.';
        default:
            return null;
    }
}

export function resolveProcessErrorMessage(data: ProcessScanResponse, mode: ScanMode): string {
    return (
        messageForScanError(data.error_code, mode) ??
        firstError(data.errors) ??
        data.message ??
        (mode === 'card'
            ? "We couldn't read the card details. Please try again with the card closer and reduce glare."
            : "We couldn't read this document. Please try another image or add the transaction manually.")
    );
}
