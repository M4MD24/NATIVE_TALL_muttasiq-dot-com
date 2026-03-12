<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_SETTING_KEY = 'does_enable_main_text_shimmering';

    private const CURRENT_SETTING_KEY = 'does_enable_visual_enhancements';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $legacyExists = DB::table('settings')
            ->where('name', self::LEGACY_SETTING_KEY)
            ->exists();

        if (! $legacyExists) {
            return;
        }

        $currentExists = DB::table('settings')
            ->where('name', self::CURRENT_SETTING_KEY)
            ->exists();

        if ($currentExists) {
            DB::table('settings')
                ->where('name', self::LEGACY_SETTING_KEY)
                ->delete();

            return;
        }

        DB::table('settings')
            ->where('name', self::LEGACY_SETTING_KEY)
            ->update([
                'name' => self::CURRENT_SETTING_KEY,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $currentExists = DB::table('settings')
            ->where('name', self::CURRENT_SETTING_KEY)
            ->exists();

        if (! $currentExists) {
            return;
        }

        $legacyExists = DB::table('settings')
            ->where('name', self::LEGACY_SETTING_KEY)
            ->exists();

        if ($legacyExists) {
            DB::table('settings')
                ->where('name', self::CURRENT_SETTING_KEY)
                ->delete();

            return;
        }

        DB::table('settings')
            ->where('name', self::CURRENT_SETTING_KEY)
            ->update([
                'name' => self::LEGACY_SETTING_KEY,
                'updated_at' => now(),
            ]);
    }
};
