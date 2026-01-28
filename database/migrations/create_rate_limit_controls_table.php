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
        $conn = config('http-service.ratelimit_connection', config('http-service.logging_connection'));

        $create = function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->timestamp('blocked_at');
            $table->integer('wait_time_minutes')->default(15);
            $table->timestamp('unblock_at')->index();
            $table->timestamps();
        };

        if (!empty($conn)) {
            Schema::connection($conn)->create('rate_limit_controls', $create);
        } else {
            Schema::create('rate_limit_controls', $create);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = config('http-service.ratelimit_connection', config('http-service.logging_connection'));
        if (!empty($conn)) {
            Schema::connection($conn)->dropIfExists('rate_limit_controls');
        } else {
            Schema::dropIfExists('rate_limit_controls');
        }
    }
};
