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
        Schema::table('js_error_reports', function (Blueprint $table) {
            $table->string('screen_breakpoint', 8)->nullable()->after('runtime_platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('js_error_reports', function (Blueprint $table) {
            $table->dropColumn('screen_breakpoint');
        });
    }
};
