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

        if (!empty($conn)) {
            Schema::connection($conn)->table('http_request_logs', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('http_request_logs', 'headers')) {
                    $table->json('headers')->nullable()->after('payload');
                }
            });
        } else {
            Schema::table('http_request_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('http_request_logs', 'headers')) {
                    $table->json('headers')->nullable()->after('payload');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = config('http-service.logging_connection');

        if (!empty($conn)) {
            Schema::connection($conn)->table('http_request_logs', function (Blueprint $table) use ($conn) {
                if (Schema::connection($conn)->hasColumn('http_request_logs', 'headers')) {
                    $table->dropColumn('headers');
                }
            });
        } else {
            Schema::table('http_request_logs', function (Blueprint $table) {
                if (Schema::hasColumn('http_request_logs', 'headers')) {
                    $table->dropColumn('headers');
                }
            });
        }
    }
};
