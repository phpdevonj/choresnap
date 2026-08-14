<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ProviderPayout;
use Illuminate\Console\Command;

class BackfillPayoutBookingIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payout:backfill-booking-ids
                            {--dry-run : Show what would be linked without saving}
                            {--window=2 : Minutes either side of the payout row to match on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link existing provider payout rows to the booking they were created from';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $window = (int) $this->option('window');

        $payouts = ProviderPayout::whereNull('booking_id')->get();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$payouts->count()} payout row(s) without a booking link");

        $linked = $ambiguous = $unmatched = 0;

        foreach ($payouts as $payout) {
            // The payout row is written in the same request that completes the
            // booking, so the booking's amount and completion time identify it.
            // Anything that does not resolve to exactly one booking is left alone.
            $candidates = Booking::where('provider_id', $payout->provider_id)
                ->where('status', 'completed')
                ->whereRaw('abs(final_sub_total - ?) < 0.005', [$payout->amount])
                ->whereBetween('updated_at', [
                    $payout->created_at->copy()->subMinutes($window),
                    $payout->created_at->copy()->addMinutes($window),
                ])
                ->pluck('id');

            if ($candidates->count() === 1) {
                $this->line("  #{$payout->id} -> booking {$candidates->first()} ({$payout->amount})");
                if (!$dryRun) {
                    $payout->booking_id = $candidates->first();
                    $payout->save();
                }
                $linked++;
            } elseif ($candidates->count() > 1) {
                $this->warn("  #{$payout->id} ambiguous: " . $candidates->implode(', '));
                $ambiguous++;
            } else {
                $unmatched++;
            }
        }

        $this->info("Linked: {$linked}, ambiguous (skipped): {$ambiguous}, unmatched (skipped): {$unmatched}");

        return 0;
    }
}
