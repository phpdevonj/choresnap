<?php

namespace App\Console\Commands;

use App\Models\ProviderPayout;
use App\Support\BookingSplit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Brings pending payouts onto the tax inclusive basis.
 *
 * Providers are now paid their price plus VAT, but rows created before that
 * change hold the amount excluding VAT. None of them have been sent yet, so
 * they are corrected in place rather than adjusted afterwards.
 */
class RecalculatePayoutVat extends Command
{
    protected $signature = 'payout:recalculate-vat
                            {--dry-run : Show what would change without saving}
                            {--id=* : Only these provider_payout ids}';

    protected $description = 'Recalculate pending provider payouts so they include VAT';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $query = ProviderPayout::where('status', 'Pending');
        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }
        $payouts = $query->orderBy('id')->get();

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$payouts->count()} pending payout(s)");

        $changed = $unchanged = $skipped = 0;
        $backup = [];
        $totalDifference = 0;

        foreach ($payouts as $payout) {
            $booking = $payout->booking;

            // Without a booking there is nothing to derive the VAT from, so the
            // row is reported and left exactly as it is.
            if ($booking === null) {
                $this->warn("  #{$payout->id} skipped: no linked booking (amount {$payout->amount})");
                $skipped++;
                continue;
            }

            $split = BookingSplit::for($booking);

            if (!$split->reconciles()) {
                $this->warn("  #{$payout->id} skipped: booking {$booking->id} does not reconcile");
                $skipped++;
                continue;
            }

            $old = (float) $payout->amount;
            $new = $split->providerAmount();

            if (abs($new - $old) < 0.005) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '  #%s booking=%s  %s -> %s  (+%s)',
                $payout->id,
                $booking->id,
                number_format($old, 2),
                number_format($new, 2),
                number_format($new - $old, 2)
            ));

            $backup[] = ['id' => $payout->id, 'old_amount' => $old, 'new_amount' => $new];
            $totalDifference += ($new - $old);
            $changed++;

            if (!$dryRun) {
                $payout->amount = $new;
                $payout->save();
            }
        }

        if (!$dryRun && !empty($backup)) {
            $path = storage_path('app/payout-vat-backup-' . now()->format('Ymd-His') . '.json');
            File::put($path, json_encode($backup, JSON_PRETTY_PRINT));
            $this->info('Previous amounts saved to ' . $path);
        }

        $this->info(sprintf(
            'Updated: %d, unchanged: %d, skipped: %d, total increase: %s',
            $changed,
            $unchanged,
            $skipped,
            number_format($totalDifference, 2)
        ));

        return 0;
    }
}
