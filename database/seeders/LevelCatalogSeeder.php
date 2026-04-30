<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\LevelContent;
use Illuminate\Database\Seeder;

class LevelCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = [
            ['name' => 'Básico 1', 'slug' => 'basico_1', 'sort_order' => 1],
            ['name' => 'Básico 2', 'slug' => 'basico_2', 'sort_order' => 2],
            ['name' => 'Básico 3', 'slug' => 'basico_3', 'sort_order' => 3],
            ['name' => 'Básico 4', 'slug' => 'basico_4', 'sort_order' => 4],
            ['name' => 'Intermedio 1', 'slug' => 'intermedio_1', 'sort_order' => 5],
            ['name' => 'Intermedio 2', 'slug' => 'intermedio_2', 'sort_order' => 6],
            ['name' => 'Intermedio 3', 'slug' => 'intermedio_3', 'sort_order' => 7],
            ['name' => 'Avanzado 1', 'slug' => 'avanzado_1', 'sort_order' => 8],
            ['name' => 'Avanzado 2', 'slug' => 'avanzado_2', 'sort_order' => 9],
            ['name' => 'Avanzado 3', 'slug' => 'avanzado_3', 'sort_order' => 10],
            ['name' => 'Avanzado 4', 'slug' => 'avanzado_4', 'sort_order' => 11],
            ['name' => 'Avanzado 5', 'slug' => 'avanzado_5', 'sort_order' => 12],
            ['name' => 'Avanzado 6', 'slug' => 'avanzado_6', 'sort_order' => 13],
        ];

        foreach ($catalog as $row) {
            Level::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'description' => null,
                ]
            );
        }

        $basico1 = Level::query()->where('slug', 'basico_1')->firstOrFail();

        $figures = [
            ['name' => 'Vamos para arriba', 'sort_order' => 1],
            ['name' => 'Dame un cachito', 'sort_order' => 2],
        ];

        foreach ($figures as $figure) {
            LevelContent::query()->updateOrCreate(
                [
                    'level_id' => $basico1->id,
                    'name' => $figure['name'],
                ],
                [
                    'description' => null,
                    'sort_order' => $figure['sort_order'],
                ]
            );
        }
    }
}
