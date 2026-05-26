<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->tinyInteger('is_visible')->default(1)->after('status');
        });

        // Set all existing records to is_visible = 1
        DB::table('notification_templates')->update(['is_visible' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
