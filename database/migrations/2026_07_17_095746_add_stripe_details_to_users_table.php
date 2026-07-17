<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeDetailsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_verification_status')->nullable()->after('stripe_account_id');
            $table->string('stripe_first_name')->nullable()->after('stripe_verification_status');
            $table->string('stripe_last_name')->nullable()->after('stripe_first_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_verification_status', 'stripe_first_name', 'stripe_last_name']);
        });
    }
}
