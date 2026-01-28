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
        $conn = config('http-service.logging_connection');

        $create = function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048)->index();
            $table->string('method', 10)->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->integer('status_code')->nullable()->index();
            $table->float('response_time')->nullable()->comment('Response time in seconds');
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Índices para otimizar consultas
            $table->index('created_at');
        };

        if (!empty($conn)) {
            Schema::connection($conn)->create('http_request_logs', $create);
        } else {
            Schema::create('http_request_logs', $create);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = config('http-service.logging_connection');
        if (!empty($conn)) {
            Schema::connection($conn)->dropIfExists('http_request_logs');
        } else {
            Schema::dropIfExists('http_request_logs');
        }
    }
};
