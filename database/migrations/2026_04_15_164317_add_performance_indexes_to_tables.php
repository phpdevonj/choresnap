<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexesToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->addIndexSafely('bookings', 'status');
        $this->addIndexSafely('bookings', 'provider_id');
        $this->addIndexSafely('bookings', 'customer_id');

        $this->addIndexSafely('services', 'status');
        $this->addIndexSafely('services', 'provider_id');

        $this->addIndexSafely('users', 'user_type');
        $this->addIndexSafely('users', 'status');

        $this->addIndexSafely('payments', 'booking_id');
        $this->addIndexSafely('payments', 'payment_status');
    }

    private function addIndexSafely($table, $column) {
        try {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->index($column);
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // For safely dropping
        $this->dropIndexSafely('bookings', 'status');
        $this->dropIndexSafely('bookings', 'provider_id');
        $this->dropIndexSafely('bookings', 'customer_id');

        $this->dropIndexSafely('services', 'status');
        $this->dropIndexSafely('services', 'provider_id');

        $this->dropIndexSafely('users', 'user_type');
        $this->dropIndexSafely('users', 'status');

        $this->dropIndexSafely('payments', 'booking_id');
        $this->dropIndexSafely('payments', 'payment_status');
    }

    private function dropIndexSafely($table, $column) {
        try {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropIndex([$column]);
            });
        } catch (\Exception $e) {
            // Index might not exist
        }
    }
}
