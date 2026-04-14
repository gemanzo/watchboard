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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('url', 2048);
            $table->enum('method', ['GET', 'HEAD']);
            $table->unsignedTinyInteger('interval_minutes');
            $table->enum('current_status', ['unknown', 'up', 'down'])->default('unknown');
            $table->boolean('is_paused')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
