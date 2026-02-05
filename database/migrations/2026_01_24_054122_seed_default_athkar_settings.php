<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settingDefaults = [];

        foreach (Setting::defaults() as $key => $value) {
            $settingDefaults[] = ['name' => $key, 'value' => $value];
        }

        $now = now();

        foreach ($settingDefaults as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                [
                    'value' => $setting['value'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('name', array_keys(Setting::defaults()))
            ->delete();
    }
};
