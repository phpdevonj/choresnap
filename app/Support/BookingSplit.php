<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Splits what a customer paid for a booking into the provider's share and
 * ChoreSnap's share.
 *
 * The provider is paid their price plus VAT and declares that VAT themselves,
 * so the split has to be exact: the two shares must always add back up to the
 * amount the customer was charged, to the cent.
 *
 * Everything is worked out in integer cents. In floating point
 * 32.50 * 1.21 lands on 39.324999..., which rounds down to 39.32 in MySQL and
 * up to 39.33 in PHP - a one cent difference on real money.
 *
 * The stored booking columns are used as the source rather than recalculating,
 * because those are what the customer was actually charged.
 */
class BookingSplit
{
    /** Tax entry that applies to the provider's price and to the service fee. */
    private const SALES_TAX_TITLE = 'Sales Tax';

    private const SERVICE_FEE_TITLE = 'Service Fee';

    private $booking;

    private function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public static function for(Booking $booking): self
    {
        return new self($booking);
    }

    /** The provider's price before VAT, in cents. */
    public function providerNetCents(): int
    {
        return (int) round(((float) $this->booking->final_sub_total) * 100);
    }

    /** The provider's price plus VAT, in cents. This is what they are paid. */
    public function providerCents(): int
    {
        $rate = $this->salesTaxRate();

        return (int) round(((float) $this->booking->final_sub_total) * (1 + $rate / 100) * 100);
    }

    /** VAT on the provider's price, in cents. */
    public function providerTaxCents(): int
    {
        return $this->providerCents() - $this->providerNetCents();
    }

    /** ChoreSnap's service fee before VAT, in cents. */
    public function serviceFeeCents(): int
    {
        return (int) round(((float) $this->booking->total_admin_fee) * 100);
    }

    /**
     * ChoreSnap's share is whatever is left of the customer's total. Taking the
     * remainder rather than calculating it separately is what guarantees the
     * two shares always reconcile, and it keeps the customer's total unchanged.
     */
    public function choresnapCents(): int
    {
        return $this->totalCents() - $this->providerCents();
    }

    /** VAT on the service fee, in cents. */
    public function choresnapTaxCents(): int
    {
        return $this->choresnapCents() - $this->serviceFeeCents();
    }

    /** What the customer paid, in cents. */
    public function totalCents(): int
    {
        return (int) round(((float) $this->booking->total_amount) * 100);
    }

    /** The provider's payout amount as a decimal, for storing on the payout row. */
    public function providerAmount(): float
    {
        return $this->providerCents() / 100;
    }

    public function providerNet(): float
    {
        return $this->providerNetCents() / 100;
    }

    public function providerTax(): float
    {
        return $this->providerTaxCents() / 100;
    }

    public function serviceFee(): float
    {
        return $this->serviceFeeCents() / 100;
    }

    public function choresnapAmount(): float
    {
        return $this->choresnapCents() / 100;
    }

    public function choresnapTax(): float
    {
        return $this->choresnapTaxCents() / 100;
    }

    public function total(): float
    {
        return $this->totalCents() / 100;
    }

    /** The VAT percentage that applied to this booking. */
    public function salesTaxRate(): float
    {
        return $this->taxRate(self::SALES_TAX_TITLE);
    }

    /** The service fee percentage that applied to this booking. */
    public function serviceFeeRate(): float
    {
        return $this->taxRate(self::SERVICE_FEE_TITLE);
    }

    /** True when the two shares add back up to what the customer paid. */
    public function reconciles(): bool
    {
        return $this->providerCents() + $this->choresnapCents() === $this->totalCents();
    }

    private function taxRate(string $title): float
    {
        $taxes = $this->booking->tax;
        $taxes = is_array($taxes) ? $taxes : json_decode((string) $taxes, true);

        if (!is_array($taxes)) {
            return 0.0;
        }

        foreach ($taxes as $tax) {
            if (($tax['title'] ?? null) === $title && ($tax['type'] ?? null) === 'percent') {
                return (float) ($tax['value'] ?? 0);
            }
        }

        return 0.0;
    }
}
