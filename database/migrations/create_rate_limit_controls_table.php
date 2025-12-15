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
        Schema::create('rate_limit_controls', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->timestamp('blocked_at');
            $table->integer('wait_time_minutes')->default(15);
            $table->timestamp('unblock_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limit_controls');
    }
};
