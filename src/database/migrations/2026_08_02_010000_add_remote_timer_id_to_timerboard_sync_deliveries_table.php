<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRemoteTimerIdToTimerboardSyncDeliveriesTable extends Migration
{
    public function up()
    {
        Schema::table('seat_timerboard_sync_deliveries', function (Blueprint $table) {
            $table->string('remote_timer_id')->nullable()->after('peer_id');
        });
    }

    public function down()
    {
        Schema::table('seat_timerboard_sync_deliveries', function (Blueprint $table) {
            $table->dropColumn('remote_timer_id');
        });
    }
}
