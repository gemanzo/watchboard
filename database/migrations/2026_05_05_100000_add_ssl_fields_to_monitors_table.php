<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('ssl_check_enabled')->default(false)->after('response_time_threshold_ms');
            $table->unsignedTinyInteger('ssl_expiry_alert_days')->default(14)->after('ssl_check_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['ssl_check_enabled', 'ssl_expiry_alert_days']);
        });
    }
};
