<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Online rent collection through Razorpay Payment Links.
 *
 * Flow: the office (or the tenant app) asks for a link for an invoice's
 * balance → the tenant pays by UPI/card/netbanking → Razorpay calls our
 * webhook → the payment is posted to the ledger with the Razorpay payment id
 * as the UTR. The unique transaction_id index makes the webhook idempotent:
 * a retried delivery can never post the same rupee twice.
 */
final class RazorpayService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function isEnabled(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    /**
     * Creates (or reuses) a payment link for the invoice's current balance.
     */
    public function paymentLinkFor(Invoice $invoice): string
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Online payments are not configured. Add the Razorpay keys to .env.');
        }

        $balance = $invoice->balanceDue();

        if ($balance <= 0.0) {
            throw new RuntimeException('Invoice '.$invoice->invoice_number.' has nothing left to pay.');
        }

        $invoice->loadMissing('booking.guest');
        $guest = $invoice->booking?->guest;

        $response = Http::withBasicAuth(
            (string) config('services.razorpay.key_id'),
            (string) config('services.razorpay.key_secret'),
        )
            ->acceptJson()
            ->timeout(15)
            ->post(rtrim((string) config('services.razorpay.base_url'), '/').'/payment_links', [
                'amount' => (int) round($balance * 100),
                'currency' => 'INR',
                'accept_partial' => false,
                'reference_id' => $invoice->invoice_number.'-'.now()->format('YmdHis'),
                'description' => 'Rent '.$invoice->invoice_number.' ('.$invoice->billing_cycle_start->format('M Y').')',
                'customer' => array_filter([
                    'name' => $guest?->full_name,
                    'contact' => $guest?->phone ? '+'.($guest->whatsappNumber() ?? $guest->phone) : null,
                    'email' => $guest?->email,
                ]),
                'notify' => ['sms' => false, 'email' => false],
                'reminder_enable' => false,
                'notes' => ['invoice_id' => (string) $invoice->id, 'invoice_number' => $invoice->invoice_number],
            ]);

        if ($response->failed()) {
            Log::warning('bizstay.razorpay.link_failed', ['invoice' => $invoice->invoice_number, 'status' => $response->status(), 'body' => $response->json()]);

            throw new RuntimeException('Razorpay refused the payment link: '.($response->json('error.description') ?? 'HTTP '.$response->status()));
        }

        $invoice->forceFill([
            'payment_link_id' => $response->json('id'),
            'payment_link_url' => $response->json('short_url'),
        ])->save();

        return (string) $response->json('short_url');
    }

    /**
     * Razorpay signs the raw request body with the webhook secret (HMAC-SHA256).
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Posts a paid link to the ledger. Returns the payment, or null when the
     * event is not relevant or was already processed.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleWebhook(array $event): ?Payment
    {
        if (($event['event'] ?? null) !== 'payment_link.paid') {
            return null;
        }

        $link = data_get($event, 'payload.payment_link.entity', []);
        $payment = data_get($event, 'payload.payment.entity', []);
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId === '' || Payment::withTrashed()->where('transaction_id', $paymentId)->exists()) {
            return null;
        }

        $invoice = Invoice::query()->where('payment_link_id', $link['id'] ?? '')->first()
            ?? Invoice::query()->find((int) data_get($link, 'notes.invoice_id', 0));

        if (! $invoice instanceof Invoice) {
            Log::warning('bizstay.razorpay.unknown_link', ['link' => $link['id'] ?? null, 'payment' => $paymentId]);

            return null;
        }

        $recorded = $this->invoices->recordPayment($invoice, [
            'amount' => round(((int) ($payment['amount'] ?? 0)) / 100, 2),
            'payment_method' => $this->mapMethod((string) ($payment['method'] ?? 'upi'))->value,
            'transaction_id' => $paymentId,
            'paid_on' => now()->toDateString(),
            'notes' => 'Paid online via Razorpay link '.($link['id'] ?? ''),
        ]);

        $invoice->forceFill(['payment_link_id' => null, 'payment_link_url' => null])->save();

        Log::notice('bizstay.razorpay.payment_posted', ['invoice' => $invoice->invoice_number, 'payment' => $paymentId]);

        return $recorded;
    }

    private function mapMethod(string $method): PaymentMethod
    {
        return match ($method) {
            'card' => PaymentMethod::Card,
            'netbanking' => PaymentMethod::NetBanking,
            default => PaymentMethod::Upi,
        };
    }
}
