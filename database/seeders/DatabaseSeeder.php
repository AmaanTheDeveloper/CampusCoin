<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DefaultCategorySeeder::class);

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Optional local lending demo (idempotent). Prefer artisan campuscoin:seed-lending-demo.
        if (app()->environment(['local', 'development'])) {
            $this->call(LendingDemoSeeder::class);
        }
    }
}
