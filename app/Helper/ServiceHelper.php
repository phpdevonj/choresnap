<?php

namespace App\Helper;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Service;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;

class ServiceHelper {

    public static function serviceFinalPrice(int $id): float {
        $service = Service::find($id);

        // Base Price
        $basePrice = $service->price ?? 0;

        // Service Discount
        $baseServiceDiscount = $service->discount ?? 0;
        $serviceDiscount = ($baseServiceDiscount / 100) * $basePrice;
        $amount = $basePrice - $serviceDiscount;

        // Coupon Subtract

        // Service Price
        $taxes = Tax::where('status', 1)->get();
        foreach ($taxes as $tax) {
            if ($tax->type == 'percent') {
                $amount = $amount + ($amount * ($tax->value / 100));
            } else {
                $amount = $amount + $tax->value;
            }
        }

        return $amount;

    }

    public static function stripeRefund(Booking $booking): void {

        $setting = PaymentGateway::where('type', 'stripe')->first();
        if (!$setting) {
            logger('Stripe gateway not configured');
            return;
        }
        $val = $setting->is_test == '1' ? $setting->value : $setting->live_value;
        if (!is_array($val)) {
            $val = json_decode($val, true);
        }
        $secretKey = $val['stripe_key'] ?? null;
        if (!$secretKey) {
            logger('Stripe not configured');
            return;
        };

        Stripe::setApiKey($secretKey);

        $payments = $booking->payment()->whereIn('payment_status', ['advanced_paid', 'paid'])->get();

        foreach ($payments as $payment) {
            try {
                DB::beginTransaction();
                $amountInCents = (int)round($payment->total_amount * 100);
                $refund = Refund::create([
                    'payment_intent' => $payment->txn_id,
                    'amount' => $amountInCents, // Amount in cents
                    'reason' => 'requested_by_customer' // order_cancelled_by_caterer
                ]);

                if ($refund->id) {
                    $payment->update([
                        'payment_status' => 'refunded',
                    ]);
                }
                DB::commit();
            } catch (ApiErrorException $e) {
                DB::rollback();

                // Catch Stripe-specific errors (declined, already refunded, etc.)
                logger('Stripe Refund Error: ' . $e->getMessage());
            } catch (\Exception $e) {
                DB::rollback();

                // Catch general exceptions
                logger('General Refund Error: ' . $e->getMessage());
            }
        }
    }

}
