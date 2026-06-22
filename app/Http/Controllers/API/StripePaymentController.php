<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Setting;
use App\Models\Payment;
use App\Models\Country;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Stripe\StripeClient;

class StripePaymentController extends Controller {
    public function createIdealPayment(Request $request) {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'booking_id' => 'required',
            'guest_id' => $user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 403,
                'message' => $validator->errors()->first()
            ], 403);
        }


        $paymentGateway = PaymentGateway::where('type', 'stripe')->first();
        if ($paymentGateway) {
            // Decode JSON stored in test_values or live_values
            $val = $paymentGateway->is_test == '1' ? $paymentGateway->value : $paymentGateway->live_value;

            // Ensure it's a string before decoding
            if (!is_array($val)) {
                $val = json_decode($val, true);
            }
            // Extract API keys
            $secretKey = $val['stripe_key'] ?? null;
            $publishedKey = $val['stripe_publickey'] ?? null;

            Stripe::setApiKey($secretKey);
            $sitesetup = Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
            $sitesetupdata = $sitesetup ? json_decode($sitesetup->value) : null;
            $currencyId = optional($sitesetupdata)->default_currency;
            $currencySymbol = Country::whereId($currencyId)->first();
            try {
                $paymentIntent = PaymentIntent::create([
                    'amount' => $request->total_amount,  // amount in cents
                    'currency' => $currencySymbol->currency_code ?? 'EUR',
//                    'payment_method_types' => ['card'],
                    'automatic_payment_methods' => [ 'enabled' => true ],
                    'description' => 'card Payment',
                    'metadata' => [
                        'booking_id' => $request->booking_id ?? 'N/A',
                        'user_id' => $user->id
                    ],
                ]);

                $paymentData = new Payment;
                $paymentData->customer_id = $user->id;
                $paymentData->booking_id = $request->booking_id;
                $paymentData->datetime = now();
                $paymentData->total_amount = $request->amount;
                $paymentData->payment_type = 'stripe';

                $paymentData->payment_status = 'pending';
                $paymentData->txn_id = $paymentIntent->id;
                $paymentData->save();

                return response()->json([
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_id' => $paymentData->id

                ]);
            } catch (\Exception $e) {
                Log::error('Stripe createIdealPayment error: ' . $e->getMessage());
                return response()->json(['error' => 'Payment processing failed'], 500);
            }
        }

    }

    public function handleWebhook(Request $request) {
        // $payload = $request->getContent();
        // $sig_header = $request->header('Stripe-Signature');
        // $endpoint_secret = config('stripe.webhook_secret');
        $payload = $request->all();
        $eventType = $payload['type'];
        $eventData = $payload['data']['object'];
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($eventData);
                return response()->json(['status' => 'success']);
            case 'payment_intent.payment_failed':
            case 'payment_intent.canceled':
                Log::error('Stripe Webhook: Payment Failed/Canceled', ['event_type' => $eventType, 'data' => $eventData]);
                $this->handlePaymentFailure($eventData);
                return response()->json(['status' => 'failed']);
            default:
                Log::info('Stripe Webhook: Event Ignored', ['event_type' => $eventType]);
                return response()->json(['status' => 'ignored']);
        }

        // try {
        //     $event = \Stripe\Webhook::constructEvent(
        //         $payload, $sig_header, $endpoint_secret
        //     );

        //     if ($event->type === 'payment_intent.succeeded') {
        //         $paymentIntent = $event->data->object;
        //         Log::info('Payment succeeded for order: ' . $paymentIntent->metadata->order_id);

        //         // TODO: Update order status in your DB.
        //     }

        //     if ($event->type === 'payment_intent.payment_failed') {
        //         $paymentIntent = $event->data->object;
        //         Log::warning('Payment failed for order: ' . $paymentIntent->metadata->order_id);

        //         // TODO: Mark order as failed in DB.
        //     }

        //     return response()->json(['status' => 'success']);
        // } catch (\Exception $e) {
        //     Log::error('Stripe webhook error: ' . $e->getMessage());
        //     return response()->json(['error' => 'Webhook error'], 400);
        // }
    }

    private function handlePaymentSuccess($paymentIntent) {
        $paymentIntentId = $paymentIntent['id'];
//        $payment = Payment::where('booking_id', $paymentIntent['metadata']['booking_id'])->first();
        $payment = Payment::where('txn_id', $paymentIntentId)->first();
        if ($payment) {
            $payment->update(['payment_status' => 'paid']);
        }
    }

    private function handlePaymentFailure($paymentIntent) {
        $paymentIntentId = $paymentIntent['id'];
        $payment = Payment::where('txn_id', $paymentIntentId)->first();
//        $payment = Payment::where('booking_id', $paymentIntent['metadata']['booking_id'])->first();
        if ($payment) {
            $payment->update(['payment_status' => 'failed']);
            $booking = \App\Models\Booking::find($payment->booking_id);
            if ($booking) {
                $booking->status = 'cancelled';
                $booking->save();
            }
        }
    }

    public function connectLogin() {

        $auth = auth()->user();
        if (!$auth->stripe_account_id) {
            return response()->json([
                'message' => 'Stripe account id not set'
            ], 403);
        }

        $setting = PaymentGateway::where('type', 'stripe')->first();
        if (!$setting) {
            return response()->json([
                'message' => 'Stripe gateway not configured'
            ], 403);
        }

        $val = $setting->is_test == '1' ? $setting->value : $setting->live_value;
        if (!is_array($val)) {
            $val = json_decode($val, true);
        }
        $secretKey = $val['stripe_key'] ?? null;

        if (!$secretKey) {
            return response()->json([
                'message' => 'Stripe not configured'
            ], 403);
        };


        Stripe::setApiKey($secretKey);
        try {
            $accountLink = Account::createLoginLink($auth->stripe_account_id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'unable to create login link: ' . $e->getMessage()
            ], 403);
        }

        if (!$accountLink->url) {
            return response()->json([
                'message' => 'unable to create login link'
            ], 403);
        }

        return response()->json([
            'link' => $accountLink->url,
        ], 200);

    }

}
