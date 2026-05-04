<?php

namespace Database\Seeders;

use App\Models\DanceType;
use Illuminate\Database\Seeder;

class DanceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $danceTypes = [
            ['name' => 'Salsa Casino', 'slug' => 'salsa-casino', 'description' => 'Casino and rueda foundations', 'sort_order' => 1],
            ['name' => 'Son Cubano', 'slug' => 'son-cubano', 'description' => 'Classic son timing and movement', 'sort_order' => 2],
            ['name' => 'Rueda de Casino', 'slug' => 'rueda-de-casino', 'description' => 'Group figures and calls', 'sort_order' => 3],
        ];

        foreach ($danceTypes as $danceType) {
            DanceType::query()->updateOrCreate(
                ['slug' => $danceType['slug']],
                $danceType
            );
        }
    }
}
