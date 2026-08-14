<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Transfer;
use App\Models\ProviderPayout; // Or your model
use App\Models\Payment;
use App\Models\PaymentGateway;
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
                $charge = null;
                if ($account) {
                    $mode = 'SKIP (not live)';
                    try {
                        Stripe::setApiKey($secretKey);
                        \Stripe\Account::retrieve($account);
                        $mode = 'LIVE -> would pay';
                        $charge = $this->resolveCharge($provider);
                    } catch (\Exception $e) {
                        // stays a skip
                    }
                }
                $this->line(sprintf(
                    '  #%s provider=%s amount=%s %s destination=%s  [%s]%s',
                    $provider->id,
                    $provider->provider_id,
                    $provider->amount,
                    $charge['currency'] ?? ($provider->providers->country->currency_code ?: 'EUR'),
                    $account ?? '(MISSING)',
                    $mode,
                    $mode === 'LIVE -> would pay'
                        ? (isset($charge['id'])
                            ? ' funded by ' . $charge['id']
                            : ' WARNING: no charge found, would draw on platform balance')
                        : ''
                ));
                continue;
            }

            $amount = (int) round($provider->amount * 100); // cents
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

            // Fund the transfer from the booking's own charge. Without this the
            // transfer draws on the platform's general balance, which Stripe has
            // usually already paid out to the platform bank account by the time
            // this runs - the reason these transfers were failing.
            $charge = $this->resolveCharge($provider);

            // The currency has to match the charge. It used to be taken from the
            // provider's country, so any provider outside the eurozone produced a
            // currency the platform holds no balance in and could never be paid.
            $currency = $charge['currency'] ?? null;
            if (!$currency) {
                $currency = $provider->providers->country->currency_code ?? null;
                $currency = $currency !== '' && $currency !== null ? $currency : 'EUR';
            }

            try {
                $transferPayload = [
                    'amount' => $amount,
                    'currency' => strtolower($currency),
                    'destination' => $connectedAccount,
                    'metadata' => [
                        'disbursement_id' => $provider->id, // Disbursement ID
                        'booking_id' => $provider->booking_id,
                    ],
                ];
                if (!empty($charge['id'])) {
                    $transferPayload['source_transaction'] = $charge['id'];
                } else {
                    $transferPayload['transfer_group'] = 'PAYOUT_' . $provider->id;
                }

                $transfer = Transfer::create($transferPayload);
                Log::info("Transfer created successfully", [
                    'provider_id' => $provider->id,
                    'transfer_id' => $transfer->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'source_transaction' => $charge['id'] ?? null,
                ]);

                $provider->status = 'transferred';
                $provider->stripe_transfer_id = $transfer->id;
                $provider->failure_reason = null;
                $provider->save();

                // Connected accounts on an automatic schedule are paid out by
                // Stripe itself; creating a manual payout on top of that fails and
                // used to strand the row in 'transferred' forever.
                $payout = null;
                if ($this->payoutScheduleIsManual($connectedAccount)) {
                    $payout = \Stripe\Payout::create([
                        'amount' => $amount,
                        'currency' => strtolower($currency),
                        'method' => 'standard',
                        'metadata' => [
                            'disbursement_id' => $provider->id,
                        ],
                    ], [
                        'stripe_account' => $connectedAccount,
                    ]);
                    $provider->stripe_payout_id = $payout->id;
                }

                Log::info("Provider payout successful", [
                    'provider_payout_id' => $provider->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'transfer_id' => $transfer->id,
                    'payout_id' => $payout->id ?? '(automatic schedule)',
                    'payout_status' => $payout->status ?? 'handled by Stripe',
                ]);

                $provider->status = 'completed';
                $provider->paid_date = $payout
                    ? Carbon::createFromTimestamp($payout->created)
                    : Carbon::createFromTimestamp($transfer->created);
                $provider->save();

                $this->info("  #{$provider->id} transferred {$provider->amount} {$currency} to {$connectedAccount}");
            } catch (\Throwable $e) {
                // Was \Exception, which let a PHP Error abort the whole run and
                // skip every remaining provider.
                Log::error('Transfer failed: ' . $e->getMessage(), [
                    'provider_payout_id' => $provider->id,
                    'stripe_account_id' => $connectedAccount,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
                $provider->failure_reason = substr($e->getMessage(), 0, 1000);
                $provider->save();
                $this->error("  #{$provider->id} transfer failed: " . $e->getMessage());
            }
        }

        return 0;
    }

    /**
     * Find the Stripe charge that funded this payout, so the transfer can be
     * drawn from it rather than from the platform's general balance.
     *
     * @return array{id: string, currency: string}|null
     */
    private function resolveCharge(ProviderPayout $providerPayout)
    {
        if (empty($providerPayout->booking_id)) {
            return null;
        }

        $payment = Payment::where('booking_id', $providerPayout->booking_id)
            ->whereIn('payment_status', ['paid', 'advanced_paid'])
            ->whereNotNull('txn_id')
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            return null;
        }

        try {
            $txnId = $payment->txn_id;

            // Older rows stored a Checkout Session id instead of a PaymentIntent.
            if (strpos($txnId, 'cs_') === 0) {
                $session = \Stripe\Checkout\Session::retrieve($txnId);
                $txnId = $session->payment_intent;
            }

            if (strpos($txnId, 'pi_') === 0) {
                $intent = \Stripe\PaymentIntent::retrieve($txnId);
                $chargeId = $intent->latest_charge;
                if (!$chargeId) {
                    return null;
                }
                $chargeObject = \Stripe\Charge::retrieve($chargeId);
            } else {
                $chargeObject = \Stripe\Charge::retrieve($txnId);
            }

            // A charge that is refunded or not yet captured cannot fund a transfer.
            if (!$chargeObject->captured || $chargeObject->refunded) {
                Log::warning('Charge cannot fund a transfer', [
                    'provider_payout_id' => $providerPayout->id,
                    'charge_id' => $chargeObject->id,
                    'captured' => $chargeObject->captured,
                    'refunded' => $chargeObject->refunded,
                ]);
                return null;
            }

            return ['id' => $chargeObject->id, 'currency' => $chargeObject->currency];
        } catch (\Throwable $e) {
            Log::warning('Could not resolve the charge for a payout: ' . $e->getMessage(), [
                'provider_payout_id' => $providerPayout->id,
                'booking_id' => $providerPayout->booking_id,
            ]);
            return null;
        }
    }

    /**
     * Stripe pays out automatically unless the connected account is explicitly on
     * a manual schedule, in which case the payout has to be created by hand.
     */
    private function payoutScheduleIsManual($connectedAccount)
    {
        try {
            $account = \Stripe\Account::retrieve($connectedAccount);
            return ($account->settings->payouts->schedule->interval ?? null) === 'manual';
        } catch (\Throwable $e) {
            Log::warning('Could not read the payout schedule, leaving it to Stripe: ' . $e->getMessage(), [
                'stripe_account_id' => $connectedAccount,
            ]);
            return false;
        }
    }
}
