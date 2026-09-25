<?php

namespace Database\Seeders;

use App\Models\DanceType;
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
        $salsaCasinoId = DanceType::query()
            ->where('slug', 'salsa-casino')
            ->value('id') ?? DanceType::query()->create([
                'name' => 'Salsa Casino',
                'slug' => 'salsa-casino',
                'description' => 'Casino and rueda foundations',
                'sort_order' => 1,
            ])->id;

        foreach ($this->salsaCasinoCatalog() as $levelRow) {
            $level = Level::query()->updateOrCreate(
                ['slug' => $levelRow['slug']],
                [
                    'name' => $levelRow['name'],
                    'sort_order' => $levelRow['sort_order'],
                    'description' => null,
                    'dance_type_id' => $salsaCasinoId,
                ]
            );

            $keepNames = [];

            foreach ($levelRow['figures'] as $index => $figureName) {
                $keepNames[] = $figureName;

                LevelContent::query()->updateOrCreate(
                    [
                        'level_id' => $level->id,
                        'name' => $figureName,
                    ],
                    [
                        'description' => null,
                        'sort_order' => $index + 1,
                    ]
                );
            }

            LevelContent::query()
                ->where('level_id', $level->id)
                ->whereNotIn('name', $keepNames)
                ->delete();
        }
    }

    /**
     * @return list<array{name: string, slug: string, sort_order: int, figures: list<string>}>
     */
    private function salsaCasinoCatalog(): array
    {
        return [
            [
                'name' => 'Básico 1',
                'slug' => 'basico_1',
                'sort_order' => 1,
                'figures' => [
                    'Ángulos',
                    'Al Centro',
                    'Jocosidades',
                    'Arriba',
                    'Pégale un Cacho',
                    'Cacho doble y triple',
                    'Cacho de Jamón',
                    'Cachito hasta la tuya/hasta el tuyo',
                    'Cachito Complicado',
                    'Cacho con lager',
                    'Para abajo',
                    'Exhíbela',
                    'Exhíbela (Doble y N…)',
                ],
            ],
            [
                'name' => 'Básico 2',
                'slug' => 'basico_2',
                'sort_order' => 2,
                'figures' => [
                    'El espejo (Guapea)',
                    'Enchufla (doble y triple)',
                    'Enchufla y pa`rriba',
                    'Dile que no',
                    'Yogurt',
                    'Vacúnala',
                    'Dame una',
                    'Dame dos',
                    'Enchufla y dame una',
                    'La prima',
                    'Prima con la hermana',
                    'Prima con la tía',
                    'Vacílala',
                    'Vacíla con ella',
                    'Vacíla y dame una',
                    'Ponle el sombrero',
                    'Sombrero con una',
                    'Torniquete',
                    'Torniquete con túnel',
                    'New York',
                    'New York Complicado',
                    'Strike',
                    'Métele el dedo',
                    'Ping Pong',
                ],
            ],
            [
                'name' => 'Básico 3',
                'slug' => 'basico_3',
                'sort_order' => 3,
                'figures' => [
                    'Sesenta y nueve',
                    'Setenta',
                    'Enchufla y Cásate Con la Suegra',
                    'Prima con toda la familia',
                    'Abrázala',
                    'El Uno',
                    'El dos',
                    'Cadeneta',
                    'Evelyn al centro',
                    'La flor',
                    'Carrusel',
                    'Llévala al cielo',
                    'Va y Ven',
                    'Panque (fly, centro y rolling)',
                    'Ochenta y cuatro',
                    'Pelota 1, 2 y 3',
                    'Doble play',
                    'Cuatro Con Cuatro',
                    'Despréciala',
                    'Balsero',
                    'Paséala',
                    'Tres pulpitos',
                    'Croqueta',
                    'Una para abajo y una para arriba',
                ],
            ],
            [
                'name' => 'Básico 4',
                'slug' => 'basico_4',
                'sort_order' => 4,
                'figures' => [
                    'Montaña',
                    'Enchufla con raulín',
                    'Vacila con engaño',
                    'Ochenta y cuatro con escalera',
                    'Setenta y uno',
                    'El dedo con una',
                    'Abanico',
                    'Evelyn',
                    'Enchufla con el trompo',
                    'Exhíbela hasta afuera',
                    'Croqueta con Alarde',
                    'El siete',
                    'Coca Cola',
                    'Setenta y dos',
                    'Montaña rusa',
                    'Dame una con una, con dos',
                    'Setenta pa`ti',
                    'Dame dos con cuba',
                    'Brazalete',
                    'Molinete',
                    'Arco',
                ],
            ],
            [
                'name' => 'Intermedio 1',
                'slug' => 'intermedio_1',
                'sort_order' => 5,
                'figures' => [
                    'Por las manos y tranca',
                    'Besito',
                    'Setenta y cuatro',
                    'Enchufla con engaño',
                    'Media',
                    'Combo 1',
                    'La tralla',
                    'Sombréala',
                    'Setenta y cinco',
                    'Dedo, guarapo y bota',
                    'Dame una con Raulín',
                    'Enchufla arriba',
                    'Prima arriba',
                    'Exhíbela con el cero',
                    'Setenta y cinco con engaño',
                    'Siete setenta',
                    'Torniquete con túnel y alarde',
                    'Esmeralda',
                    'Kentucky',
                    'Siete moderno',
                    'Dame una y no le llegues',
                    'Dame una y paséala',
                    'Paséala arriba',
                    'Pásatela por el filo',
                ],
            ],
            [
                'name' => 'Intermedio 2',
                'slug' => 'intermedio_2',
                'sort_order' => 6,
                'figures' => [
                    'Juana la cubana',
                    'Sombreo doble',
                    'Arco Doble',
                    'Las jimaguas',
                    'Dame, coca-cola por detrás',
                    'Enchufla y escóndela',
                    'Zambuca',
                    'Exhíbela hasta afuera no le llegues',
                    'Mimi-mimi',
                    'El doce (12)',
                    'Tropicana',
                    'Tornillo',
                    'El cun cun',
                    'Vacila y florea',
                    'Dedo por debajo',
                    'Sombrero de mami',
                    'Paséala y complícate',
                    '82',
                ],
            ],
            [
                'name' => 'Intermedio 3',
                'slug' => 'intermedio_3',
                'sort_order' => 7,
                'figures' => [
                    'El bébe',
                    'Ponle sabor',
                    'El dedo saboreado',
                    'Tirabuzón',
                    'Arco triple',
                    'El muñequito',
                    'Setenta y tres (73)',
                    'El laso',
                    'Pelota loca',
                    'Doble play con adorno',
                    'Avioneta',
                    '70 complicado',
                    'Pedrito',
                    'Croqueta con Vuelta',
                    'Siete loco',
                    'Paséala por el parque',
                    'Exhíbela como yo',
                    '75 con gancho complicado',
                ],
            ],
            [
                'name' => 'Avanzado 1',
                'slug' => 'avanzado_1',
                'sort_order' => 8,
                'figures' => [
                    '84 Complicado',
                    'La habana',
                    'Titanic',
                    'Candado',
                    'Noventa (90)',
                    'Para arriba invertido',
                    'Rubenada',
                    'Siete loco complicado',
                    'Adalberto al centro',
                    'Exhíbete',
                    'Three way stop',
                    'Enchufla moderno',
                    'La cuadra',
                    'Setenta nuevo',
                    'Abanico complicado',
                    'La Jenny',
                    'Vacílate',
                    '70 por las manos',
                    'Molinete Complicado',
                    'Casino en línea (1ra parte)',
                ],
            ],
            [
                'name' => 'Avanzado 2',
                'slug' => 'avanzado_2',
                'sort_order' => 9,
                'figures' => [
                    'Casino en línea (2da parte)',
                    'Quiébrala',
                    'Agamenón',
                    'Enchufla y bikini',
                    'La holandesa',
                    'Sombrero al centro',
                    'El Terminal',
                    'Consorte',
                    'Limón',
                    'Melao',
                    'La ensambladora',
                    'Sabrosura',
                    '70 doble',
                    'Copelia',
                    'Sombrero largo por debajo',
                    'Micaela',
                    'Peluquero',
                    'Medio sombrero',
                ],
            ],
            [
                'name' => 'Avanzado 3',
                'slug' => 'avanzado_3',
                'sort_order' => 10,
                'figures' => [
                    'Sombrero doble complicado',
                    'Setenta de Yanae',
                    'Azuquita',
                    'Bacardi limón',
                    'El kiwi',
                    'Cuba libre',
                    'La mariposa',
                    'Media noche',
                    'Enredadera',
                    'Remolino',
                    'Fernando',
                    'La Julie',
                    'Copelia complicado',
                    'Sombrero por debajo complicado',
                ],
            ],
            [
                'name' => 'Avanzado 4',
                'slug' => 'avanzado_4',
                'sort_order' => 11,
                'figures' => [
                    'Limón Doble',
                    '7 unisex / 7 unisex complicado',
                    'Leoncio',
                    'Sabadazo',
                    'Candado complicado',
                    'Ajiaco 2000',
                    'Chequendengue',
                    'Cun Cun cubano',
                    'Carnaval',
                    'Melón',
                    'Las tijeras',
                ],
            ],
            [
                'name' => 'Avanzado 5',
                'slug' => 'avanzado_5',
                'sort_order' => 12,
                'figures' => [
                    'Sabor y caché',
                    'Episodio III',
                    'Niágara',
                    'Serpiente',
                    'La Francesa',
                    'Miacaela Complicada',
                    'Saoco',
                    'Sabor unisex',
                    'La presa',
                    'La tuya',
                    'Mascarita',
                    'Setenta y pescao',
                    'Bacardi complicado',
                ],
            ],
            [
                'name' => 'Avanzado 6',
                'slug' => 'avanzado_6',
                'sort_order' => 13,
                'figures' => [
                    'La estrella',
                    'Leoncio complicado',
                    'Bayamo en coche',
                    'Prismas',
                    'Pedro navaja',
                    'La presa complicada',
                    'Durdo el loco',
                    'Carnaval unisex',
                ],
            ],
            [
                'name' => 'Máster 1',
                'slug' => 'master_1',
                'sort_order' => 14,
                'figures' => [
                    'Sabor con Gusto y Caramelo',
                    'Sombrero de Guano',
                    '70 Nuevo Complicado',
                    'El Camaguey',
                    'La Mayi',
                    'El Mostro',
                    'Lazo Complicado',
                    'Lazo Rumbero',
                    'Dame Abajo',
                    'Dame Abajo Enchufla Arriba',
                ],
            ],
            [
                'name' => 'Máster 2',
                'slug' => 'master_2',
                'sort_order' => 15,
                'figures' => [
                    'Micaela Complicada',
                    'Sombrero de Lucy',
                    'Infanta',
                    'Thalia',
                    'Mona Lisa',
                    'El Calvo',
                    'Sombrero Enganchado',
                    'Tusacutusa',
                    'Dame Abajo y Escalera',
                    'Montaña Rusa Complicada',
                ],
            ],
        ];
    }
}
