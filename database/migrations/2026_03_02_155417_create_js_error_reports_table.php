<?php

declare(strict_types=1);

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
        Schema::create('js_error_reports', function (Blueprint $table) {
            $table->id();
            $table->text('user_note');
            $table->json('errors');
            $table->string('first_error_message', 500);
            $table->unsignedSmallInteger('error_count');
            $table->string('fingerprint', 64)->nullable()->index();
            $table->string('page_url', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('client_language', 32)->nullable();
            $table->string('runtime_platform', 32)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('first_occurred_at')->nullable();
            $table->timestamp('last_occurred_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('js_error_reports');
    }
};
