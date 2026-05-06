<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->enum('check_type', ['http', 'tcp', 'ping'])->default('http')->after('method');
            $table->unsignedSmallInteger('port')->nullable()->after('check_type');
        });

        DB::table('monitors')->whereNull('check_type')->update(['check_type' => 'http']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['check_type', 'port']);
        });
    }
};
