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
        User::factory()->createMany([
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ],
            [
                'first_name' => 'Test',
                'last_name' => 'User 2',
                'email' => 'test2@example.com',
            ],
        ]);
    }
}
