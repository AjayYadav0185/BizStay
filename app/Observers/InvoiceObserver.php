<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * The invoice is the accounting document, so its arithmetic is never left to
 * the caller: total_due is always recomposed from its components and status is
 * always derived from payments + due date.
 */
final class InvoiceObserver
{
    public function saving(Invoice $invoice): void
    {
        if (blank($invoice->invoice_number)) {
            $invoice->invoice_number = Invoice::nextNumber();
        }

        $invoice->total_due = Invoice::composeTotal(
            (float) $invoice->rent_amount,
            (float) $invoice->utility_amount,
            (float) $invoice->maintenance_charges,
            (float) $invoice->food_deduction,
            (float) $invoice->other_charges,
            (float) $invoice->previous_balance,
        );

        $invoice->generated_at ??= now();

        $this->syncStatus($invoice);
    }

    private function syncStatus(Invoice $invoice): void
    {
        $total = (float) $invoice->total_due;
        $paid = round((float) $invoice->amount_paid, 2);
        $dueDate = $invoice->due_date;

        $status = match (true) {
            $total <= 0.0 => InvoiceStatus::Paid,
            $paid >= $total => InvoiceStatus::Paid,
            $paid > 0.0 && $dueDate && $dueDate->isPast() => InvoiceStatus::Overdue,
            $paid > 0.0 => InvoiceStatus::PartiallyPaid,
            $dueDate && $dueDate->isPast() => InvoiceStatus::Overdue,
            default => InvoiceStatus::Unpaid,
        };

        $invoice->status = $status;
        $invoice->paid_at = $status === InvoiceStatus::Paid
            ? ($invoice->paid_at ?? now())
            : null;
    }
}
