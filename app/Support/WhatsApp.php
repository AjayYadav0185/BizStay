<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Property;

/**
 * Click-to-chat WhatsApp messages (wa.me). No API account or per-message cost:
 * the manager's own WhatsApp opens with the message ready, they press send.
 */
final class WhatsApp
{
    public static function link(?string $phone, string $message): ?string
    {
        $number = Guest::toWhatsappNumber($phone);

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public static function rentReminder(Invoice $invoice, ?Property $property = null): string
    {
        $property ??= Property::current();
        $invoice->loadMissing('booking.guest', 'booking.bed');

        $firstName = strtok((string) $invoice->booking?->guest?->full_name, ' ') ?: 'there';
        $balance = number_format($invoice->balanceDue(), 0);
        $overdue = $invoice->due_date->isPast();

        $lines = [
            'Hi '.$firstName.' 👋',
            '',
            $overdue
                ? 'Gentle reminder: your rent of ₹'.$balance.' for '.$invoice->billing_cycle_start->format('M Y').' was due on '.$invoice->due_date->format('d M').'.'
                : 'Your rent of ₹'.$balance.' for '.$invoice->billing_cycle_start->format('M Y').' is due on '.$invoice->due_date->format('d M').'.',
            'Invoice: '.$invoice->invoice_number.' · Bed '.($invoice->booking?->bed?->bed_code ?? '—'),
        ];

        if (filled($invoice->payment_link_url)) {
            $lines[] = '';
            $lines[] = 'Pay online (UPI / card): '.$invoice->payment_link_url;
        } elseif (filled($property?->upi_id)) {
            $lines[] = '';
            $lines[] = 'Pay by UPI to: '.$property->upi_id;
            $lines[] = 'Please share the UTR / screenshot here after paying.';
        }

        $lines[] = '';
        $lines[] = 'Thank you — '.($property?->name ?? 'PG Office');

        return implode("\n", $lines);
    }

    public static function welcome(Guest $guest, ?Property $property = null): string
    {
        $property ??= Property::current();
        $booking = $guest->currentBooking;

        $lines = [
            'Welcome to '.($property?->name ?? 'our PG').', '.(strtok($guest->full_name, ' ') ?: $guest->full_name).'! 🏠',
            '',
            'Bed: '.($booking?->bed?->bed_code ?? '—').' · Rent ₹'.number_format((float) ($booking?->monthly_rent ?? 0), 0).'/month',
            'Rent is due by the '.self::ordinal((int) ($booking?->rent_due_day ?? 5)).' of every month.',
        ];

        if (filled($property?->upi_id)) {
            $lines[] = 'UPI: '.$property->upi_id;
        }

        $lines[] = 'Office: '.($property?->contact_phone ?? '—');
        $lines[] = '';
        $lines[] = 'Download the BizStay app and sign in with your mobile number to see bills and raise complaints.';

        return implode("\n", $lines);
    }

    private static function ordinal(int $day): string
    {
        $suffix = match (true) {
            in_array($day % 100, [11, 12, 13], true) => 'th',
            $day % 10 === 1 => 'st',
            $day % 10 === 2 => 'nd',
            $day % 10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix;
    }
}
