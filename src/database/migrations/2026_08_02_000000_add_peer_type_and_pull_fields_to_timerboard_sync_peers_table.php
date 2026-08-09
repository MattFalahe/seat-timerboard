<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPeerTypeAndPullFieldsToTimerboardSyncPeersTable extends Migration
{
    public function up()
    {
        Schema::table('seat_timerboard_sync_peers', function (Blueprint $table) {
            $table->string('peer_type')->default('seat')->after('name');
            $table->text('excluded_tag_ids')->nullable()->after('sync_tag_ids');
            $table->boolean('pull_remote_timers')->default(false)->after('allow_remote_delete');
        });

        DB::table('seat_timerboard_sync_peers')
            ->whereNull('peer_type')
            ->update(['peer_type' => 'seat']);
    }

    public function down()
    {
        Schema::table('seat_timerboard_sync_peers', function (Blueprint $table) {
            $table->dropColumn([
                'peer_type',
                'excluded_tag_ids',
                'pull_remote_timers',
            ]);
        });
    }
}
