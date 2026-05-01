<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedTinyInteger('confirmation_threshold')->default(1)->after('is_paused');
            $table->unsignedTinyInteger('consecutive_failures')->default(0)->after('confirmation_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['confirmation_threshold', 'consecutive_failures']);
        });
    }
};
