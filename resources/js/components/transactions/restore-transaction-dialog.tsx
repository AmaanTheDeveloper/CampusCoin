import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type RestoreTransactionDialogProps = {
    transactionId: number;
    description?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function RestoreTransactionDialog({ transactionId, description, open, onOpenChange }: RestoreTransactionDialogProps) {
    const [processing, setProcessing] = useState(false);

    const confirmRestore = () => {
        if (processing) {
            return;
        }

        setProcessing(true);
        router.post(
            route('transactions.restore', transactionId),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onOpenChange(false);
                },
                onError: () => {
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restore this transaction?</DialogTitle>
                    <DialogDescription>
                        It will return to your active records and be included in balances and totals again.
                        {description ? (
                            <>
                                {' '}
                                <span className="text-foreground font-medium">“{description}”</span>
                            </>
                        ) : null}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={confirmRestore} disabled={processing}>
                        {processing ? 'Restoring…' : 'Restore'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
