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
    protected $signature = 'payout:weekly
                            {--dry-run : Show what would be transferred without calling Stripe}
                            {--id=* : Only process these provider_payout ids}
                            {--limit= : Process at most this many payouts}';

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

        // Payouts move real money, so the gateway's is_test toggle is deliberately
        // ignored here: this command always runs on the live key and decides per
        // account whether that account belongs to live mode. A test-mode provider
        // is skipped rather than paid, whatever the toggle happens to be set to.
        $liveVal = $testVal = null;
        if ($setting) {
            $liveVal = is_array($setting->live_value) ? $setting->live_value : json_decode($setting->live_value, true);
            $testVal = is_array($setting->value) ? $setting->value : json_decode($setting->value, true);
        }
        $secretKey = $liveVal['stripe_key'] ?? null;
        $testKey = $testVal['stripe_key'] ?? null;

        if (!$secretKey) {
            // Was dd() before, which dies silently when run from cron.
            Log::error('Payout aborted: live Stripe key is missing');
            $this->error('Live Stripe key is missing');
            return 1;
        }

        Stripe::setApiKey($secretKey);
        Log::info('Payout is enabled. Starting payout processing...');

        $dryRun = $this->option('dry-run');

        // Log the platform balance up front: a transfer draws from the general
        // available balance, so an empty balance is the usual reason these fail.
        try {
            $balance = \Stripe\Balance::retrieve();
            foreach ($balance->available as $available) {
                Log::info('Platform available balance', [
                    'amount' => $available->amount,
                    'currency' => $available->currency,
                ]);
                $this->line(sprintf('Available balance: %s %s', $available->amount / 100, strtoupper($available->currency)));
            }
        } catch (\Exception $e) {
            Log::warning('Could not retrieve platform balance: ' . $e->getMessage());
        }

        $query = ProviderPayout::where('status', 'Pending');
        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }
        if ($limit = $this->option('limit')) {
            $query->orderBy('id')->limit((int) $limit);
        }
        $providers = $query->get();
        Log::info("providers payout processing - Found {$providers->count()} drivers");
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$providers->count()} pending payout(s)");

        foreach ($providers as $provider) {
            if ($dryRun) {
                $account = $provider->providers->stripe_account_id ?? null;
                $mode = 'no account';
                if ($account) {
                    $mode = 'SKIP (not live)';
                    try {
                        Stripe::setApiKey($secretKey);
                        \Stripe\Account::retrieve($account);
                        $mode = 'LIVE -> would pay';
                    } catch (\Exception $e) {
                        // stays a skip
                    }
                }
                $this->line(sprintf(
                    '  #%s provider=%s amount=%s %s destination=%s  [%s]',
                    $provider->id,
                    $provider->provider_id,
                    $provider->amount,
                    $provider->providers->country->currency_code ?? 'EUR',
                    $account ?? '(MISSING)',
                    $mode
                ));
                continue;
            }

            $amount = $provider->amount * 100; // cents
            $currency = $provider->providers->country->currency_code ?? 'EUR';
            $connectedAccount = $provider->providers->stripe_account_id;

            if (empty($connectedAccount)) {
                Log::warning('Payout skipped: provider has no connected account', [
                    'provider_payout_id' => $provider->id,
                    'provider_id' => $provider->provider_id,
                ]);
                $this->warn("  #{$provider->id} skipped: no stripe_account_id");
                continue;
            }

            // A key can only reach accounts created in its own Stripe mode, so
            // retrieving the account with the live key is what proves it is a live
            // account. Anything the live key cannot see is skipped, never paid.
            try {
                Stripe::setApiKey($secretKey);
                \Stripe\Account::retrieve($connectedAccount);
            } catch (\Exception $e) {
                $reason = 'not reachable with the live key';

                // Classify it for the log: reachable by the test key means this is
                // a test-mode provider, anything else is a genuinely broken id.
                if ($testKey) {
                    try {
                        Stripe::setApiKey($testKey);
                        \Stripe\Account::retrieve($connectedAccount);
                        $reason = 'test-mode account';
                    } catch (\Exception $inner) {
                        $reason = 'unknown to both live and test keys';
                    }
                    Stripe::setApiKey($secretKey);
                }

                Log::warning('Payout skipped: ' . $reason, [
                    'provider_payout_id' => $provider->id,
                    'provider_id' => $provider->provider_id,
                    'stripe_account_id' => $connectedAccount,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  #{$provider->id} skipped ({$connectedAccount}): {$reason}");
                continue;
            }

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
