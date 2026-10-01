import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export type BudgetOverrunPayload = {
    exceeds: boolean;
    budget_id: number;
    category_name: string;
    month_label: string;
    limit: string;
    spent_before: string;
    expense_amount: string;
    resulting_spend: string;
    over_amount: string;
    already_exceeded: boolean;
    prior_over_amount: string;
    balance_sufficient: boolean;
};

type BudgetOverrunDialogProps = {
    open: boolean;
    payload: BudgetOverrunPayload | null;
    processing?: boolean;
    onContinue: () => void;
    onReviewBudget: () => void;
    onOpenChange: (open: boolean) => void;
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

export function BudgetOverrunDialog({ open, payload, processing = false, onContinue, onReviewBudget, onOpenChange }: BudgetOverrunDialogProps) {
    if (!payload) {
        return null;
    }

    const title = payload.already_exceeded ? 'Budget Already Exceeded' : "You're Going Over Budget";

    const description = payload.already_exceeded
        ? `Your ${payload.category_name} budget is Rs ${formatRs(payload.limit)}. You're already Rs ${formatRs(payload.prior_over_amount)} over. This expense will increase the overrun to Rs ${formatRs(payload.over_amount)} (total spending Rs ${formatRs(payload.resulting_spend)}).`
        : `Your ${payload.category_name} budget is Rs ${formatRs(payload.limit)}. This expense will take your spending to Rs ${formatRs(payload.resulting_spend)}, which is Rs ${formatRs(payload.over_amount)} over your budget.`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md" role="alertdialog" aria-describedby="budget-overrun-description">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription id="budget-overrun-description" className="space-y-2 text-left">
                        <span className="block">{description}</span>
                        {payload.balance_sufficient ? (
                            <span className="text-muted-foreground block text-xs">Your available balance is still sufficient for this expense.</span>
                        ) : null}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" disabled={processing} onClick={onReviewBudget}>
                        Review Your Budget
                    </Button>
                    <Button type="button" disabled={processing} aria-busy={processing} onClick={onContinue} autoFocus>
                        {processing ? 'Saving…' : 'Continue Anyway'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
