<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedTinyInteger('notification_cooldown_minutes')->default(15)->after('last_checked_at');
            $table->timestamp('last_notified_at')->nullable()->after('notification_cooldown_minutes');
            $table->boolean('recovery_bypass_cooldown')->default(true)->after('last_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['notification_cooldown_minutes', 'last_notified_at', 'recovery_bypass_cooldown']);
        });
    }
};
