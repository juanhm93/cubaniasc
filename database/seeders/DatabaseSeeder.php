<?php

namespace Database\Seeders;

use App\Models\Company;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DanceTypeSeeder::class,
        ]);

        $company = Company::query()->firstOrCreate(
            ['slug' => 'cubania'],
            [
                'rif' => 'J-00000000-0',
                'name' => 'Cubania',
                'email' => 'admin@cubania.test',
                'phone' => '+580000000000',
                'address' => 'La Habana',
                'website' => 'https://cubania.test',
                'is_active' => true,

            ]
        );

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        User::factory()->create([
            'name' => 'Juan Hernandez',
            'email' => 'juanhm93@gmail.com',
            'password' => Hash::make('juan1234'),
            'status' => 'active',
            'company_id' => $company->id,
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);
        User::factory(3)->create();

        $this->call(LevelCatalogSeeder::class);
        $this->call(ReviewPanelSeeder::class);
        $this->call(PreRegistrationSeeder::class);
        $this->call(PaymentsDemoSeeder::class);
    }
}
