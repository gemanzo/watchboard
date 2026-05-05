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
            $table->string('keyword_check', 255)->nullable()->after('response_time_threshold_ms');
            $table->enum('keyword_check_type', ['contains', 'not_contains'])->nullable()->after('keyword_check');
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->boolean('keyword_matched')->nullable()->after('is_successful');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropColumn('keyword_matched');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['keyword_check', 'keyword_check_type']);
        });
    }
};
