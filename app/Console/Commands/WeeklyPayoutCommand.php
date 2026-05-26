<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Transfer;
use App\Models\ProviderPayout; // Or your model
use App\Models\PaymentGateway;
use App\Models\HandymanPayout; // Or your model
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeeklyPayoutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payout:weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and record weekly payouts for providers and handymen';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Starting provider payout processing...');
        $setting = PaymentGateway::where('type', 'stripe')->first();
        if($setting){
            // Decode JSON stored in test_values or live_values
            $val = $setting->is_test == '1' ? $setting->value : $setting->live_value;

            // Ensure it's a string before decoding
            if (!is_array($val)) {
                $val = json_decode($val, true);
            }
                // Extract API keys
            $secretKey = $val['stripe_key'] ?? null;
        }
        if (!$secretKey) {
            dd('Stripe Key is missing');
        }
             
        Stripe::setApiKey($secretKey);
        Log::info('Payout is enabled. Starting payout processing...');

        $providers = ProviderPayout::where('status', 'Pending')->get();
        Log::info("providers payout processing - Found {$providers->count()} drivers");

        foreach ($providers as $provider) {
            $amount = $provider->amount * 100; // cents
            $currency = $provider->providers->country->currency_code ?? 'EUR';
            $connectedAccount = $provider->providers->stripe_account_id;

            try {
                $transfer =  Transfer::create([
                    'amount' => $amount,
                    'currency' => $currency,
                    'destination' => $connectedAccount,
                    // 'transfer_group' => 'ORDER_' . now()->format('Y-m-d'),
                    'metadata' => [
                        // 'order_id' => $order->id, // Order ID
                        'disbursement_id' => $provider->id, // Disbursement ID
                    ],
                ]);
                Log::info("Transfer created successfully", [
                    'provider_id' => $provider->id,
                    'transfer_id' => $transfer->id,
                    'amount' => $amount,
                    'currency' => $currency
                ]);

                $provider->status = 'transferred';
                $provider->save();

                $payout = \Stripe\Payout::create([
                    'amount' => $amount,
                    'currency' => $currency,
                    'method' => 'standard', // Use 'instant' if available
                    'metadata' => [
                    //   'order_id' => $order->id,
                      'disbursement_id' => $provider->id
                    ],
                  ], [
                    'stripe_account' => $connectedAccount, //  Use `stripe_account` instead of `destination`
                  ]);
                  Log::info("Driver payout successful", [
                  
                    'amount' => $amount,
                    'currency' => $currency,
                    'transfer_id' => $transfer->id,
                    'payout_id' => $payout->id,
                    'transfer_status' => 'created',
                    'payout_status' => $payout->status
                ]);
                  $provider->status = 'completed';
                  $provider->paid_date =  Carbon::createFromTimestamp($payout->created);
                  $provider->save();
                // $this->info("Transferred $amount $currency to $connectedAccount");
            } catch (\Exception $e) {
                \Log::error('Transfer failed: ' . $e->getMessage());
                $this->error('Transfer failed for ' . $e->getMessage());
            }
        }

        // $handyman = HandymanPayout::where('paid_date','')->get();

        // foreach ($handyman as $disbursement) {
        //     $amount = $disbursement->amount * 100; // cents
        //     // $currency = $detail->delivery_man->restaurant->currency;
        //     $connectedAccount = $disbursement->handymans->stripe_account_id;
        //     $account = \Stripe\Account::retrieve($connectedAccount);
        //     $currency = $account->default_currency;
        //     try {
        //         \Stripe\Transfer::create([
        //             'amount' => $amount,
        //             'currency' => $currency,
        //             'destination' => $connectedAccount,
        //             // 'transfer_group' => 'ORDER_' . now()->format('Y-m-d'),
        //             'metadata' => [
        //                 // 'order_id' => $order->id, // Order ID
        //                 'disbursement_id' => $disbursement->id, // Disbursement ID
        //             ],
        //         ]);

            

        //         $payout = \Stripe\Payout::create([
        //             'amount' => $amount,
        //             'currency' => $currency,
        //             'method' => 'standard', // Use 'instant' if available
        //             'metadata' => [
        //             //   'order_id' => $order->id,
        //               'disbursement_id' => $disbursement->id
        //             ],
        //           ], [
        //             'stripe_account' => $connectedAccount, //  Use `stripe_account` instead of `destination`
        //           ]);

        //           $disbursement->paid_date = Carbon::createFromTimestamp($payout->created);;
        //           $disbursement->save();
        //         // $this->info("Transferred $amount $currency to $connectedAccount");
        //     } catch (\Exception $e) {
        //         \Log::error('Transfer failed: ' . $e->getMessage());
        //         $this->error('Transfer failed for ' . $e->getMessage());
        //     }
        // }


    }
}
