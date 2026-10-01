import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useRef } from 'react';

type InsufficientBalanceDialogProps = {
    open: boolean;
    availableBalance: string;
    expenseAmount: string;
    onOpenChange: (open: boolean) => void;
    /** Focus/correct the expense amount after the dialog closes (Review Amount). */
    onReviewAmount: () => void;
};

function formatRs(value: string): string {
    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return value;
    }

    return numeric.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function InsufficientBalanceDialog({ open, availableBalance, expenseAmount, onOpenChange, onReviewAmount }: InsufficientBalanceDialogProps) {
    const reviewRequestedRef = useRef(false);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-md"
                role="alertdialog"
                aria-describedby="insufficient-balance-description"
                onCloseAutoFocus={(event) => {
                    if (!reviewRequestedRef.current) {
                        return;
                    }

                    // Prevent Radix from restoring focus to the submit control; land on amount.
                    event.preventDefault();
                    reviewRequestedRef.current = false;
                    onReviewAmount();
                }}
            >
                <DialogHeader>
                    <DialogTitle>Insufficient Balance</DialogTitle>
                    <DialogDescription id="insufficient-balance-description">
                        Your available balance is Rs {formatRs(availableBalance)}, but this expense is Rs {formatRs(expenseAmount)}. Please reduce the
                        expense amount or add income first. This transaction has not been saved.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        autoFocus
                        onClick={() => {
                            reviewRequestedRef.current = true;
                            onOpenChange(false);
                        }}
                    >
                        Review Amount
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
