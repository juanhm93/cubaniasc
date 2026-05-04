<?php

namespace Database\Seeders;

use App\Models\PreRegistration;
use Illuminate\Database\Seeder;

class PreRegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PreRegistration::factory()->count(12)->create();
    }
}
