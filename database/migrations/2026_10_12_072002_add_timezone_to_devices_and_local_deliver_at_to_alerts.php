<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('timezone')->nullable();
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->string('local_deliver_at', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('local_deliver_at');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
