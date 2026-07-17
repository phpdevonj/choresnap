<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Console\Command;
use Stripe\Stripe;

class SyncStripeAccountDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:sync-account-details
                            {--user= : Only sync a single user id}
                            {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill Stripe verification status and name for users that already have a connected account id';

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
        $paymentGateway = PaymentGateway::where('type', 'stripe')->first();
        if (!$paymentGateway) {
            $this->error('Stripe payment gateway is not configured.');
            return 1;
        }

        $val = $paymentGateway->is_test == '1' ? $paymentGateway->value : $paymentGateway->live_value;
        $val = is_array($val) ? $val : json_decode($val, true);
        $secretKey = $val['stripe_key'] ?? null;

        if (empty($secretKey)) {
            $this->error('Stripe API key is not configured.');
            return 1;
        }

        Stripe::setApiKey($secretKey);

        $query = User::whereNotNull('stripe_account_id')->where('stripe_account_id', '!=', '');
        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }
        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users with a Stripe account id found.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Syncing ' . $users->count() . ' user(s) from Stripe...');

        $synced = $failed = 0;

        foreach ($users as $user) {
            try {
                $account = \Stripe\Account::retrieve($user->stripe_account_id);

                if ($dryRun) {
                    $name = trim(($account->individual->first_name ?? '') . ' ' . ($account->individual->last_name ?? ''));
                    $this->line(sprintf(
                        '  #%s %s -> charges=%s payouts=%s disabled=%s name=%s',
                        $user->id,
                        $user->stripe_account_id,
                        var_export($account->charges_enabled, true),
                        var_export($account->payouts_enabled, true),
                        var_export($account->requirements->disabled_reason ?? null, true),
                        $name !== '' ? $name : '(empty)'
                    ));
                } else {
                    syncStripeAccountDetails($user, $account);
                    $fresh = $user->fresh();
                    $this->line(sprintf(
                        '  #%s %s -> %s / %s %s',
                        $user->id,
                        $user->stripe_account_id,
                        $fresh->stripe_verification_status,
                        $fresh->stripe_first_name,
                        $fresh->stripe_last_name
                    ));
                }

                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $this->warn('  #' . $user->id . ' (' . $user->stripe_account_id . ') failed: ' . $e->getMessage());
            }
        }

        $this->info('Done. Synced: ' . $synced . ', Failed: ' . $failed);

        return 0;
    }
}
