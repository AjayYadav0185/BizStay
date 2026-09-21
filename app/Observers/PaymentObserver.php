<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * A payment is an immutable ledger row; the invoice balance is re-derived from
 * the set of settled payments after every write. This makes the flow safe for
 * partial payments, refunds, reversals and concurrent cash collection.
 */
final class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        $this->recalculate($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->recalculate($payment);
    }

    public function restored(Payment $payment): void
    {
        $this->recalculate($payment);
    }

    private function recalculate(Payment $payment): void
    {
        $invoice = Invoice::query()->find($payment->invoice_id);

        if (! $invoice) {
            return;
        }

        // Sum only inflows: refunds must not cancel out collected rent.
        $received = round(
            (float) Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Success->value)
                ->where('payment_type', '!=', PaymentType::Refund->value)
                ->sum('amount'),
            2,
        );

        if ((float) $invoice->amount_paid === $received) {
            return;
        }

        $invoice->amount_paid = $received;
        $invoice->save();
    }
}
