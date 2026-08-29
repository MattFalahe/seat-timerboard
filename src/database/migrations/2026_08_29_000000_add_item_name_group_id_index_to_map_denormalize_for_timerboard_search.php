<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'tb_mapdenormalize_itemname_groupid_idx';

    public function up(): void
    {
        Schema::table('mapDenormalize', function (Blueprint $table) {
            $table->index(['itemName', 'groupID'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('mapDenormalize', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
