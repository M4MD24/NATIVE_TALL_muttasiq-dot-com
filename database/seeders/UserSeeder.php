<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => config('app.custom.user.email'),
            ],
            [
                'name' => config('app.custom.user.name'),
                'password' => config('app.custom.user.password'),
            ]
        );
    }
}
