import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type ArchiveTransactionDialogProps = {
    transactionId: number;
    description?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ArchiveTransactionDialog({ transactionId, description, open, onOpenChange }: ArchiveTransactionDialogProps) {
    const [processing, setProcessing] = useState(false);

    const confirmArchive = () => {
        if (processing) {
            return;
        }

        setProcessing(true);
        router.delete(route('transactions.destroy', transactionId), {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                onOpenChange(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive transaction?</DialogTitle>
                    <DialogDescription>
                        This transaction will be removed from your active records but preserved in your transaction history. You can restore it later.
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
                    <Button type="button" variant="destructive" onClick={confirmArchive} disabled={processing}>
                        {processing ? 'Archiving…' : 'Archive transaction'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
