<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = ['Park', 'Shrine', 'Shopping Street', 'Amusement Park', 'Museum', 'Historical Site', 'Nature', 'Food', 'Nightlife', 'Cultural Experience'];

        foreach ($tags as $name) {
            Tag::create(['name' => $name]);
        }
    }
}
