<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_MAIN_TEXT_SIZE_KEY = 'maximum_main_text_size';

    private const MAX_MAIN_TEXT_SIZE_DEFAULT = 22;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['name' => self::MAX_MAIN_TEXT_SIZE_KEY],
            [
                'value' => self::MAX_MAIN_TEXT_SIZE_DEFAULT,
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
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('name', self::MAX_MAIN_TEXT_SIZE_KEY)
            ->delete();
    }
};
