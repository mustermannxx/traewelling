<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('event_suggestions', static function(Blueprint $table) {
            $table->renameColumn('train_station_id', 'station_id');
        });
    }

    public function down(): void {
        Schema::table('event_suggestions', static function(Blueprint $table) {
            $table->renameColumn('station_id', 'train_station_id');
        });
    }
};
