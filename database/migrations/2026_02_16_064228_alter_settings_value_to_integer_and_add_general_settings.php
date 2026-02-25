<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MIN_MAIN_TEXT_SIZE_KEY = 'minimum_main_text_size';

    private const MIN_MAIN_TEXT_SIZE_DEFAULT = 21;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('value')->default(0)->change();
        });

        DB::table('settings')->updateOrInsert(
            ['name' => self::MIN_MAIN_TEXT_SIZE_KEY],
            [
                'value' => self::MIN_MAIN_TEXT_SIZE_DEFAULT,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('name', self::MIN_MAIN_TEXT_SIZE_KEY)
            ->delete();

        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('value')->default(false)->change();
        });
    }
};
