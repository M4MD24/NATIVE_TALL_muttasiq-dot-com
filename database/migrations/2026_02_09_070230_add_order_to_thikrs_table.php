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
        Schema::table('thikrs', function (Blueprint $table) {
            $table->unsignedInteger('order')->default(0)->index();
        });

        $order = 1;

        DB::table('thikrs')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$order): void {
                DB::table('thikrs')
                    ->where('id', $id)
                    ->update(['order' => $order++]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thikrs', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
