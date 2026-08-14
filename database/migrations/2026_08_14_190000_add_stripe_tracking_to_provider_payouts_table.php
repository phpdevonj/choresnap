<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeTrackingToProviderPayoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('provider_payouts', function (Blueprint $table) {
            // Links the payout back to the booking it was earned on, which is what
            // lets the transfer be funded by that booking's own charge.
            $table->unsignedBigInteger('booking_id')->nullable()->after('provider_id')->index();
            $table->string('stripe_transfer_id')->nullable()->after('bank_id');
            $table->string('stripe_payout_id')->nullable()->after('stripe_transfer_id');
            $table->text('failure_reason')->nullable()->after('stripe_payout_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('provider_payouts', function (Blueprint $table) {
            $table->dropColumn(['booking_id', 'stripe_transfer_id', 'stripe_payout_id', 'failure_reason']);
        });
    }
}
